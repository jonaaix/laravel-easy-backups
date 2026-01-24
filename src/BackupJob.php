<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups;

use Aaix\LaravelEasyBackups\Events\BackupFailed;
use Aaix\LaravelEasyBackups\Events\BackupSucceeded;
use Aaix\LaravelEasyBackups\Notifications\BackupFailedNotification;
use Aaix\LaravelEasyBackups\Notifications\BackupSucceededNotification;
use Aaix\LaravelEasyBackups\Services\BackupProcessor;
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
use Illuminate\Support\Facades\Storage;
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

      if ($this->beforeHook && class_exists($this->beforeHook)) {
         app()->call($this->beforeHook);
      }

      try {
         $result = $processor->execute($this);
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
      ];
   }

   private function getLocalStorageDir(): string
   {
      if ($this->localStorageDir) {
         return $this->localStorageDir;
      }

      $pathGen = app(PathGenerator::class);
      $relativePath = !empty($this->databasesToInclude)
         ? $pathGen->getDatabaseLocalPath()
         : $pathGen->getFilesLocalPath();

      return Storage::disk($this->getLocalDisk())->path($relativePath);
   }

   private function getLocalDisk(): string
   {
      if (!empty($this->databasesToInclude)) {
         return config('easy-backups.defaults.database.local_disk', 'local');
      }
      return config('easy-backups.defaults.files.local_disk', 'local');
   }

   private function getRemoteStorageDir(): ?string
   {
      return $this->remoteStorageDir;
   }
}
