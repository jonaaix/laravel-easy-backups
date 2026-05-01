<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups;

use Aaix\LaravelEasyBackups\Events\BackupFailed;
use Aaix\LaravelEasyBackups\Events\BackupSucceeded;
use Aaix\LaravelEasyBackups\Notifications\BackupFailedNotification;
use Aaix\LaravelEasyBackups\Notifications\BackupSucceededNotification;
use Aaix\LaravelEasyBackups\Services\BackupProcessor;
use Aaix\LaravelEasyBackups\Services\ConsoleFeedback;
use Aaix\LaravelEasyBackups\Services\PathGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class BackupJob implements ShouldQueue
{
   use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

   private string $workingDirectory;
   private bool $isManagedTempDirectory = false;

   public function __construct(
      private readonly array $databasesToInclude,
      private readonly array $filesToInclude,
      private readonly array $directoriesToInclude,
      private readonly ?string $saveTo,
      private readonly int $maxRemoteBackups,
      private readonly int $maxRemoteDays,
      private readonly int $maxLocalBackups,
      private readonly int $maxLocalDays,
      private readonly bool $keepLocal,
      private readonly ?string $localStorageDir,
      private readonly ?string $remoteStorageDir,
      private readonly ?string $encryptionPassword,
      private readonly array $notifyOnSuccess,
      private readonly array $notifyOnFailure,
      private readonly ?string $beforeHook,
      private readonly ?string $afterHook,
      ?string $tempDirectory = null,
      private readonly bool $shouldCompress = false,
      private readonly string $namePrefix = 'backup',
      private readonly ?string $filenameSuffix = null,
      private readonly ?bool $enableEnvPathPrefix = null,
      private readonly array $excludeTables = [],
      private readonly array $excludeTableData = [],
      private readonly bool $dryRun = false,
   ) {
      if ($tempDirectory) {
         $this->workingDirectory = $tempDirectory;
         $this->isManagedTempDirectory = false;
      } else {
         $baseTempDir = app(PathGenerator::class)->getAbsoluteTempPath();

         $this->workingDirectory = $baseTempDir . DIRECTORY_SEPARATOR . 'backup_' . date('Y-m-d_H-i-s');
         $this->isManagedTempDirectory = true;
      }
   }

   public function getNamePrefix(): string
   {
      return $this->namePrefix;
   }

   /**
    * @return array{paths: string[], disk: string, size: int}
    */
   public function handle(BackupProcessor $processor): array
   {
      // 0. Pre-flight Validation
      $this->validateConfiguration();

      if ($this->dryRun) {
         return $this->executeDryRun();
      }

      if ($this->beforeHook && class_exists($this->beforeHook)) {
         app()->call($this->beforeHook);
      }

      // Feedback Start
      $type = !empty($this->databasesToInclude) ? 'database' : 'files';
      ConsoleFeedback::info("Starting {$type} backup process...");

      try {
         $result = $processor->execute($this);

         ConsoleFeedback::success("Backup process completed successfully.");

         if ($this->afterHook && class_exists($this->afterHook)) {
            app()->call($this->afterHook);
         }

         if (!empty($this->notifyOnSuccess['channels'])) {
            $this->sendSuccessNotification($result);
         }

         $primaryPath = $result['paths'][0] ?? null;
         if ($primaryPath) {
            event(new BackupSucceeded($primaryPath, $result['disk'], $result['size']));
         }

         return $result;
      } catch (Throwable $e) {
         ConsoleFeedback::error("Backup failed: " . $e->getMessage());

         if (!empty($this->notifyOnFailure['channels'])) {
            $this->sendFailureNotification($e);
         }

         event(new BackupFailed($this->getConfig(), $e));
         throw $e;
      } finally {
         if ($this->isManagedTempDirectory && File::isDirectory($this->workingDirectory)) {
            File::deleteDirectory($this->workingDirectory);
         }
      }
   }

   /**
    * Report what would happen without performing any side effects.
    * Resolves dumper commands per DB connection and returns them for display.
    */
   private function executeDryRun(): array
   {
      ConsoleFeedback::warning('DRY-RUN: no files will be written, no uploads will happen.');

      $commands = [];

      if (!empty($this->databasesToInclude)) {
         foreach ($this->databasesToInclude as $dbConnection) {
            $dumper = \Aaix\LaravelEasyBackups\DumperFactory::create(
               $dbConnection,
               $this->excludeTables,
               $this->excludeTableData,
            );
            $fakePath = $this->workingDirectory . DIRECTORY_SEPARATOR . "db-dump_{$dbConnection}.sql";
            $commands[] = [
               'connection' => $dbConnection,
               'target_path' => $fakePath,
               'command' => $dumper->getDumpCommand($fakePath),
            ];
         }
      }

      foreach ($commands as $entry) {
         ConsoleFeedback::step("Database: {$entry['connection']}");
         ConsoleFeedback::info("Would dump to: {$entry['target_path']}");
         ConsoleFeedback::info('Command: ' . $entry['command']);
      }

      if ($this->shouldCompress || $this->encryptionPassword) {
         $mode = $this->encryptionPassword ? 'zip (encrypted)' : 'auto-detected format';
         ConsoleFeedback::info("Would compress dump(s) as: {$mode}");
      }

      if ($this->saveTo) {
         ConsoleFeedback::info("Would upload to disk: {$this->saveTo}");
      } else {
         ConsoleFeedback::info('Would keep artifact(s) on local disk only.');
      }

      if ($this->maxRemoteBackups > 0) {
         ConsoleFeedback::info("Retention: keep {$this->maxRemoteBackups} most recent remote backups.");
      }
      if ($this->maxRemoteDays > 0) {
         ConsoleFeedback::info("Retention: delete remote backups older than {$this->maxRemoteDays} days.");
      }
      if ($this->maxLocalBackups > 0) {
         ConsoleFeedback::info("Retention: keep {$this->maxLocalBackups} most recent local backups.");
      }
      if ($this->maxLocalDays > 0) {
         ConsoleFeedback::info("Retention: delete local backups older than {$this->maxLocalDays} days.");
      }

      return [
         'dry_run' => true,
         'paths' => [],
         'disk' => $this->saveTo ?? $this->getLocalDisk(),
         'size' => 0,
         'commands' => $commands,
      ];
   }

   private function validateConfiguration(): void
   {
      // Validate Remote Disk if set
      if ($this->saveTo) {
         try {
            // We just check if the disk configuration exists to fail early
            if (config("filesystems.disks.{$this->saveTo}") === null) {
               throw new \InvalidArgumentException("Backup Disk '{$this->saveTo}' is not defined in filesystems.php.");
            }
         } catch (\Exception $e) {
            Log::error("Laravel Easy Backups: Invalid Storage Disk Configuration. " . $e->getMessage());
            throw $e;
         }
      }
   }

   private function sendSuccessNotification(array $result): void
   {
      $notifiable = $this->createNotifiable($this->notifyOnSuccess);
      Notification::send($notifiable, new BackupSucceededNotification($result['paths'], $result['disk'], $result['size']));
   }

   private function sendFailureNotification(Throwable $exception): void
   {
      $notifiable = $this->createNotifiable($this->notifyOnFailure);
      Notification::send($notifiable, new BackupFailedNotification($this->getConfig(), $exception));
   }

   private function createNotifiable(array $notificationConfig): object
   {
      return new class($notificationConfig) {
         use Notifiable;
         public array $routes;

         public function __construct(private readonly array $notificationConfig)
         {
            $this->routes = [];
            foreach ($this->notificationConfig['channels'] as $channel) {
               $this->routes[$channel] = $this->notificationConfig['to'];
            }
         }

         public function routeNotificationFor(string $driver): mixed
         {
            return $this->routes[$driver] ?? null;
         }

         public function getKey(): int
         {
            return 1;
         }
      };
   }

   public function getWorkingDirectory(): string
   {
      return $this->workingDirectory;
   }

   public function getConfig(): array
   {
      return [
         'databasesToInclude' => $this->databasesToInclude,
         'filesToInclude' => $this->filesToInclude,
         'directoriesToInclude' => $this->directoriesToInclude,
         'saveTo' => $this->saveTo,
         'maxRemoteBackups' => $this->maxRemoteBackups,
         'maxRemoteDays' => $this->maxRemoteDays,
         'maxLocalBackups' => $this->maxLocalBackups,
         'maxLocalDays' => $this->maxLocalDays,
         'keepLocal' => $this->keepLocal,
         'localDisk' => $this->getLocalDisk(),
         'localStorageDir' => $this->getLocalStorageDir(),
         'remoteStorageDir' => $this->getRemoteStorageDir(),
         'encryptionPassword' => $this->encryptionPassword,
         'notifyOnSuccess' => $this->notifyOnSuccess,
         'notifyOnFailure' => $this->notifyOnFailure,
         'beforeHook' => $this->beforeHook,
         'afterHook' => $this->afterHook,
         'shouldCompress' => $this->shouldCompress,
         'filenameSuffix' => $this->filenameSuffix,
         'enableEnvPathPrefix' => $this->enableEnvPathPrefix,
         'excludeTables' => $this->excludeTables,
         'excludeTableData' => $this->excludeTableData,
      ];
   }

   private function getLocalStorageDir(): string
   {
      if ($this->localStorageDir) {
         return $this->localStorageDir;
      }

      $pathGen = app(PathGenerator::class);

      return !empty($this->databasesToInclude)
         ? $pathGen->getAbsoluteDatabaseLocalPath()
         : $pathGen->getAbsoluteFilesLocalPath();
   }

   private function getLocalDisk(): string
   {
      $pathGen = app(PathGenerator::class);

      return !empty($this->databasesToInclude)
         ? $pathGen->getDatabaseLocalDisk()
         : $pathGen->getFilesLocalDisk();
   }

   private function getRemoteStorageDir(): ?string
   {
      return $this->remoteStorageDir;
   }
}
