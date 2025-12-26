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

      $artifactsInTemp = [];
      $finalLocalPaths = [];

      // 1. Generate Source Artifacts
      if (!empty($config['databasesToInclude'])) {
         $artifactsInTemp = $this->createDatabaseDumps($config['databasesToInclude'], $workingDirectory);
      } else {
         $artifactsInTemp = $this->findFiles($config['filesToInclude']);
      }

      // 2. Handle Compression (Optional / Smart)
      if ($config['shouldCompress']) {
         $timestamp = date('Y-m-d_H-i-s');
         $namePrefix = $job->getNamePrefix();

         // Temporary base path without extension
         $tempBasePath = $workingDirectory . DIRECTORY_SEPARATOR . "{$namePrefix}_{$timestamp}";

         // Default extension to use for the call (CreateArchive enforces logic internally but needs a target path)
         $initialExtension = $config['encryptionPassword'] ? 'zip' : 'tar';
         $tempArchivePath = "{$tempBasePath}.{$initialExtension}";

         $format = $this->createArchiveAction->execute(
            $tempArchivePath,
            $artifactsInTemp,
            $config['directoriesToInclude'],
            $config['encryptionPassword']
         );

         // Rename if the resulting format differs from our initial guess
         $correctPath = "{$tempBasePath}." . $format->getExtension();
         if ($tempArchivePath !== $correctPath) {
            File::move($tempArchivePath, $correctPath);
         }

         // Strict verification only for ZIPs
         if ($format === CompressionFormatEnum::ZIP) {
            $this->verifyBackup($correctPath);
         }

         $artifactsInTemp = [$correctPath];
      }

      // 3. Move to Local Storage (Robust Implementation)
      $targetDir = $config['localStorageDir'];
      File::ensureDirectoryExists($targetDir);

      foreach ($artifactsInTemp as $sourcePath) {
         $filename = basename($sourcePath);
         $finalPath = $targetDir . DIRECTORY_SEPARATOR . $filename;

         // Only move if source and destination are different paths
         if ($sourcePath !== $finalPath) {
            if (File::exists($finalPath)) {
               File::delete($finalPath); // Overwrite protection
            }
            File::move($sourcePath, $finalPath);
         }
         $finalLocalPaths[] = $finalPath;
      }

      // 4. Remote Upload
      if ($config['saveTo']) {
         foreach ($finalLocalPaths as $localPath) {
            $this->uploadBackupAction->execute($localPath, $config['saveTo'], $config['remoteStorageDir']);
         }
      }

      // 5. Cleanup
      $this->performCleanup($job, $finalLocalPaths);

      $totalSize = array_reduce($finalLocalPaths, fn($sum, $path) => $sum + (File::exists($path) ? File::size($path) : 0), 0);

      return [
         'paths' => $finalLocalPaths,
         'disk' => $config['saveTo'] ?? 'local',
         'size' => $totalSize,
      ];
   }

   private function performCleanup(BackupJob $job, array $finalLocalPaths): void
   {
      $config = $job->getConfig();

      if ($config['saveTo']) {
         $this->cleanupBackupsAction->execute($config['saveTo'], $config['remoteStorageDir'], $config['maxRemoteBackups']);
         if (!$config['keepLocal']) {
            File::delete($finalLocalPaths);
         }
      }

      if ($config['keepLocal'] || !$config['saveTo']) {
         $this->cleanupBackupsAction->execute('local', $config['localStorageDir'], $config['maxLocalBackups']);
      }
   }

   private function createDatabaseDumps(array $databases, string $workingDirectory): array
   {
      $dumpPaths = [];
      foreach ($databases as $dbName) {
         $dumper = DumperFactory::create($dbName);
         $timestamp = date('Y-m-d_H-i-s');
         $dumpPath = $workingDirectory . DIRECTORY_SEPARATOR . "db-dump_{$dbName}_{$timestamp}.sql";
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
