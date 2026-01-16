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
      private readonly VerifyBackupAction $verifyBackupAction
   ) {
   }

   public function execute(BackupJob $job): array
   {
      $config = $job->getConfig();
      $workingDirectory = $job->getWorkingDirectory();
      File::ensureDirectoryExists($workingDirectory);

      // 'LocalDevTest3' -> 'local_dev_test_3'
      $suffix = isset($config['filenameSuffix']) && $config['filenameSuffix'] !== ''
         ? '_' . Str::slug(Str::snake($config['filenameSuffix']), '_')
         : '';

      $artifactsInTemp = [];
      $finalLocalPaths = [];

      if (!empty($config['databasesToInclude'])) {
         $artifactsInTemp = $this->createDatabaseDumps($config['databasesToInclude'], $workingDirectory, $suffix);
      } else {
         $artifactsInTemp = $this->findFiles($config['filesToInclude']);
      }

      if ($config['shouldCompress']) {
         $timestamp = date('Y-m-d_H-i-s');
         $namePrefix = $job->getNamePrefix();

         $tempBasePath = $workingDirectory . DIRECTORY_SEPARATOR . "{$namePrefix}_{$timestamp}{$suffix}";
         $initialExtension = $config['encryptionPassword'] ? 'zip' : 'tar';
         $tempArchivePath = "{$tempBasePath}.{$initialExtension}";

         $format = $this->createArchiveAction->execute(
            $tempArchivePath,
            $artifactsInTemp,
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

         $artifactsInTemp = [$correctPath];
      }

      $targetDir = $config['localStorageDir'];
      File::ensureDirectoryExists($targetDir);

      foreach ($artifactsInTemp as $sourcePath) {
         $filename = basename($sourcePath);
         $finalPath = $targetDir . DIRECTORY_SEPARATOR . $filename;

         if ($sourcePath !== $finalPath) {
            if (File::exists($finalPath)) {
               File::delete($finalPath);
            }
            File::move($sourcePath, $finalPath);
         }
         $finalLocalPaths[] = $finalPath;
      }

      $totalSize = array_reduce($finalLocalPaths, fn($sum, $path) => $sum + (File::exists($path) ? File::size($path) : 0), 0);

      if ($config['saveTo']) {
         foreach ($finalLocalPaths as $localPath) {
            $this->uploadBackupAction->execute($localPath, $config['saveTo'], $config['remoteStorageDir']);
         }
      }

      $this->performCleanup($job, $finalLocalPaths);

      $returnPaths = $finalLocalPaths;
      if ($config['saveTo'] && !$config['keepLocal']) {
         $returnPaths = array_map(fn($path) =>
            rtrim($config['remoteStorageDir'], '/') . '/' . basename($path),
            $finalLocalPaths
         );
      }

      return [
         'paths' => $returnPaths,
         'disk' => $config['saveTo'] ?? $config['localDisk'],
         'size' => $totalSize,
      ];
   }

   private function performCleanup(BackupJob $job, array $finalLocalPaths): void
   {
      $config = $job->getConfig();

      if ($config['saveTo']) {
         $this->cleanupBackupsAction->execute(
            disk: $config['saveTo'],
            path: $config['remoteStorageDir'],
            maxBackups: $config['maxRemoteBackups'],
            maxDays: $config['maxRemoteDays']
         );

         if (!$config['keepLocal']) {
            File::delete($finalLocalPaths);
         }
      }

      if ($config['keepLocal'] || !$config['saveTo']) {
         $disk = $config['localDisk'];
         $driver = config("filesystems.disks.{$disk}.driver");

         $cleanupPath = ($driver === 'local') ? $config['localStorageDir'] : $config['localStorageRelativePath'];

         $this->cleanupBackupsAction->execute(
            $disk,
            $cleanupPath,
            $config['maxLocalBackups']
         );
      }
   }

   private function createDatabaseDumps(array $databases, string $workingDirectory, string $suffix): array
   {
      $dumpPaths = [];
      foreach ($databases as $dbName) {
         $dumper = DumperFactory::create($dbName);
         $timestamp = date('Y-m-d_H-i-s');

         $dumpPath = $workingDirectory . DIRECTORY_SEPARATOR . "db-dump_{$dbName}_{$timestamp}{$suffix}.sql";

         $this->createDatabaseDumpAction->execute($dumper, $dumpPath);
         $dumpPaths[] = $dumpPath;
      }
      return $dumpPaths;
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
