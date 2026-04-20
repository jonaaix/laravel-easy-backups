<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Services;

use Aaix\LaravelEasyBackups\Actions\CleanupBackupsAction;
use Aaix\LaravelEasyBackups\Actions\CreateArchive;
use Aaix\LaravelEasyBackups\Actions\CreateDatabaseDumpAction;
use Aaix\LaravelEasyBackups\Actions\UploadBackupAction;
use Aaix\LaravelEasyBackups\Actions\VerifyBackupAction;
use Aaix\LaravelEasyBackups\BackupJob;
use Aaix\LaravelEasyBackups\DumperFactory;
use Aaix\LaravelEasyBackups\Enums\CompressionFormatEnum;
use Aaix\LaravelEasyBackups\Events\BackupInvalid;
use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class BackupProcessor
{
   public function __construct(
      private readonly CreateDatabaseDumpAction $createDatabaseDumpAction,
      private readonly CreateArchive $createArchiveAction,
      private readonly UploadBackupAction $uploadBackupAction,
      private readonly CleanupBackupsAction $cleanupBackupsAction,
      private readonly VerifyBackupAction $verifyBackupAction,
      private readonly PathGenerator $pathGenerator,
   ) {
   }

   public function execute(BackupJob $job): array
   {
      $config = $job->getConfig();
      $workingDirectory = $job->getWorkingDirectory();
      File::ensureDirectoryExists($workingDirectory);

      $suffix = isset($config['filenameSuffix']) && $config['filenameSuffix'] !== ''
         ? '_' . Str::slug(Str::snake($config['filenameSuffix']))
         : '';

      $artifacts = [];

      // ----- Step 1: Handle Databases -----
      if (!empty($config['databasesToInclude'])) {
         foreach ($config['databasesToInclude'] as $dbConnection) {
            $dumper = DumperFactory::create(
               $dbConnection,
               $config['excludeTables'] ?? [],
               $config['excludeTableData'] ?? [],
            );
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "db-dump_{$dbConnection}_{$timestamp}{$suffix}.sql";
            $dumpPath = $workingDirectory . DIRECTORY_SEPARATOR . $filename;

            $this->createDatabaseDumpAction->execute($dumper, $dumpPath);

            if ($config['shouldCompress'] || $config['encryptionPassword']) {
               $initialExtension = $config['encryptionPassword'] ? 'zip' : 'tar';
               $tempArchivePath = $dumpPath . '.' . $initialExtension;

               $format = $this->createArchiveAction->execute(
                  archivePath: $tempArchivePath,
                  files: [$dumpPath],
                  directories: [],
                  password: $config['encryptionPassword']
               );
               $finalArchivePath = $dumpPath . '.' . $format->getExtension();

               if ($tempArchivePath !== $finalArchivePath) {
                  File::move($tempArchivePath, $finalArchivePath);
               }

               if ($format === CompressionFormatEnum::ZIP) {
                  $this->verifyBackup($finalArchivePath);
               }

               File::delete($dumpPath);
               $dumpPath = $finalArchivePath;
            }

            $remoteDir = $this->pathGenerator->getDatabaseRemotePath(
               connectionName: $dbConnection,
               customBase: $config['remoteStorageDir'],
               enableEnvPathPrefix: $config['enableEnvPathPrefix']
            );

            $artifacts[] = [
               'local_path' => $dumpPath,
               'remote_dir' => $remoteDir,
            ];
         }
      }

      // ----- Step 2: Handle Files -----
      else {
         $foundFiles = $this->findFiles($config['filesToInclude']);
         $timestamp = date('Y-m-d_H-i-s');
         $namePrefix = $job->getNamePrefix();

         $tempBasePath = $workingDirectory . DIRECTORY_SEPARATOR . "{$namePrefix}_{$timestamp}{$suffix}";
         $initialExtension = $config['encryptionPassword'] ? 'zip' : 'tar';
         $tempArchivePath = "{$tempBasePath}.{$initialExtension}";

         $format = $this->createArchiveAction->execute(
            $tempArchivePath,
            $foundFiles,
            $config['directoriesToInclude'],
            $config['encryptionPassword']
         );
         $correctPath = "{$tempBasePath}." . $format->getExtension();
         if ($tempArchivePath !== $correctPath) {
            File::move($tempArchivePath, $correctPath);
         }

         if ($format === CompressionFormatEnum::ZIP) {
            $this->verifyBackup($correctPath);
         }

         $remoteDir = $this->pathGenerator->getFilesRemotePath(
            namePrefix: $namePrefix,
            customBase: $config['remoteStorageDir'],
            enableEnvPathPrefix: $config['enableEnvPathPrefix']
         );

         $artifacts[] = [
            'local_path' => $correctPath,
            'remote_dir' => $remoteDir,
         ];
      }

      // ----- Step 3: Post-Processing -----
      $finalResults = [];
      $targetLocalDir = $config['localStorageDir'];
      File::ensureDirectoryExists($targetLocalDir);

      foreach ($artifacts as $artifact) {
         $sourcePath = $artifact['local_path'];
         $filename = basename($sourcePath);
         $finalLocalPath = $targetLocalDir . DIRECTORY_SEPARATOR . $filename;

         if ($sourcePath !== $finalLocalPath) {
            if (File::exists($finalLocalPath)) {
               File::delete($finalLocalPath);
            }
            File::move($sourcePath, $finalLocalPath);
         }

         $fileSize = File::exists($finalLocalPath) ? File::size($finalLocalPath) : 0;

         if ($config['saveTo']) {
            $this->uploadBackupAction->execute(
               localPath: $finalLocalPath,
               disk: $config['saveTo'],
               remotePath: $artifact['remote_dir']
            );
            $this->cleanupBackupsAction->execute(
               disk: $config['saveTo'],
               path: $artifact['remote_dir'],
               maxBackups: $config['maxRemoteBackups'],
               maxDays: $config['maxRemoteDays']
            );
         }

         $finalResults[] = $config['saveTo']
            ? rtrim($artifact['remote_dir'], '/') . '/' . $filename
            : $finalLocalPath;
      }

      // ----- Step 4: Cleanup Local -----
      if ($config['saveTo'] && !$config['keepLocal']) {
         foreach ($artifacts as $artifact) {
            $path = $targetLocalDir . DIRECTORY_SEPARATOR . basename($artifact['local_path']);
            if (File::exists($path)) {
               File::delete($path);
            }
         }
      } else {
         $disk = $config['localDisk'];
         $driver = config("filesystems.disks.{$disk}.driver");

         if ($driver === 'local') {
            $cleanupPath = $config['localStorageDir'];
         } else {
            $cleanupPath = !empty($config['databasesToInclude'])
               ? $this->pathGenerator->getDatabaseLocalPath()
               : $this->pathGenerator->getFilesLocalPath();
         }

         $this->cleanupBackupsAction->execute(
            $disk,
            $cleanupPath,
            $config['maxLocalBackups']
         );
      }

      return [
         'paths' => $finalResults,
         'disk' => $config['saveTo'] ?? $config['localDisk'],
         'size' => isset($fileSize) ? $fileSize : 0,
      ];
   }

   private function findFiles(array $patterns): array
   {
      $files = [];
      foreach ($patterns as $pattern) {
         $files = array_merge($files, File::glob(base_path($pattern)));
      }
      return $files;
   }

   private function verifyBackup(string $archivePath): void
   {
      $verificationResult = $this->verifyBackupAction->execute($archivePath);
      if ($verificationResult !== 'ok') {
         event(new BackupInvalid($archivePath, 'local', $verificationResult));
         throw new Exception("Backup verification failed: {$verificationResult}");
      }
   }
}
