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
use Aaix\LaravelEasyBackups\Events\BackupSucceeded;
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
      File::ensureDirectoryExists($job->getWorkingDirectory());

      $dumpPaths = $this->createDatabaseDumps($config['databasesToInclude'], $job->getWorkingDirectory());

      if (! $config['shouldCompress']) {
         return $dumpPaths;
      }

      $artifacts = array_merge($dumpPaths, $this->findFiles($config['filesToInclude']));

      $tempArchiveName = 'backup-' . date('Y-m-d_H-i-s') . '.zip';
      $tempArchivePath = $job->getWorkingDirectory() . DIRECTORY_SEPARATOR . $tempArchiveName;

      $this->createArchiveAction->execute(
         $tempArchivePath,
         $artifacts,
         $config['directoriesToInclude'],
         $config['encryptionPassword']
      );

      $this->verifyBackup($tempArchivePath);

      $finalBackupPath = $config['localStorageDir'] . DIRECTORY_SEPARATOR . $tempArchiveName;
      File::ensureDirectoryExists(dirname($finalBackupPath));
      File::move($tempArchivePath, $finalBackupPath);

      if ($config['saveTo']) {
         $this->uploadBackupAction->execute($finalBackupPath, $config['saveTo'], $config['remoteStorageDir']);
         $this->cleanupBackupsAction->execute($config['saveTo'], $config['remoteStorageDir'], $config['maxRemoteBackups']);
      }

      if (!$config['keepLocal'] && $config['saveTo']) {
         File::delete($finalBackupPath);
      } else {
         $this->cleanupBackupsAction->execute('local', $config['localStorageDir'], $config['maxLocalBackups']);
      }

      $sizeInBytes = File::exists($finalBackupPath) ? File::size($finalBackupPath) : 0;

      return [
          'path' => $finalBackupPath,
          'disk' => $config['saveTo'] ?? 'local',
          'size' => $sizeInBytes,
      ];
   }

   private function createDatabaseDumps(array $databases, string $workingDirectory): array
   {
      $dumpPaths = [];
      foreach ($databases as $dbName) {
         $dumper = DumperFactory::create($dbName);
         $dumpPath = $workingDirectory . DIRECTORY_SEPARATOR . "db-dump_{$dbName}.sql";
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
