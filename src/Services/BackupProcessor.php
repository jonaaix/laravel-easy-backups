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

      // 1. Create database dumps with timestamps
      $sqlDumpPaths = $this->createDatabaseDumps($config['databasesToInclude'], $workingDirectory);
      $artifactsInTemp = array_merge($artifactsInTemp, $sqlDumpPaths);

      // 2. Handle compression if requested
      if ($config['shouldCompress']) {
         $includedFiles = $this->findFiles($config['filesToInclude']);
         $artifactsForArchive = array_merge($sqlDumpPaths, $includedFiles);

         $tempArchiveName = 'backup-' . date('Y-m-d_H-i-s') . '.zip';
         $tempArchivePath = $workingDirectory . DIRECTORY_SEPARATOR . $tempArchiveName;

         $this->createArchiveAction->execute(
            $tempArchivePath,
            $artifactsForArchive,
            $config['directoriesToInclude'],
            $config['encryptionPassword']
         );

         $this->verifyBackup($tempArchivePath);

         // The archive is now the only artifact we care about.
         $artifactsInTemp = [$tempArchivePath];
      }

      // 3. Move all final artifacts to their definitive local storage path
      File::ensureDirectoryExists($config['localStorageDir']);
      foreach ($artifactsInTemp as $artifactPath) {
         $finalPath = $config['localStorageDir'] . DIRECTORY_SEPARATOR . basename($artifactPath);
         File::move($artifactPath, $finalPath);
         $finalLocalPaths[] = $finalPath;
      }

      // 4. Upload to remote storage if configured
      if ($config['saveTo']) {
         foreach ($finalLocalPaths as $localPath) {
            $this->uploadBackupAction->execute($localPath, $config['saveTo'], $config['remoteStorageDir']);
         }
      }

      // 5. Cleanup local and remote backups
      if ($config['saveTo']) {
         $this->cleanupBackupsAction->execute($config['saveTo'], $config['remoteStorageDir'], $config['maxRemoteBackups']);
         if (!$config['keepLocal']) {
            File::delete($finalLocalPaths);
         }
      }

      if ($config['keepLocal'] || !$config['saveTo']) {
         $this->cleanupBackupsAction->execute('local', $config['localStorageDir'], $config['maxLocalBackups']);
      }

      // 6. Consolidate results for the job to handle
      $totalSize = array_reduce($finalLocalPaths, fn($sum, $path) => $sum + (File::exists($path) ? File::size($path) : 0), 0);

      return [
         'paths' => $finalLocalPaths,
         'disk' => $config['saveTo'] ?? 'local',
         'size' => $totalSize,
      ];
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
         throw new Exception('Backup verification failed: ' . $verificationResult . ', Path: ' . $archivePath);
      }
   }
}
