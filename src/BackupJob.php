<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups;

use Aaix\LaravelEasyBackups\Events\BackupFailed;
use Aaix\LaravelEasyBackups\Events\BackupSucceeded;
use Aaix\LaravelEasyBackups\Notifications\BackupFailedNotification;
use Aaix\LaravelEasyBackups\Notifications\BackupSucceededNotification;
use Aaix\LaravelEasyBackups\Services\BackupProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
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
      private readonly bool $shouldCompress = false
   ) {
      if ($tempDirectory) {
         $this->workingDirectory = $tempDirectory;
         $this->isManagedTempDirectory = false;
      } else {
         $baseTempDir = storage_path('app/easy-backups-temp');
         $this->workingDirectory = $baseTempDir . DIRECTORY_SEPARATOR . Str::random(16);
         $this->isManagedTempDirectory = true;
      }
   }

   public function handle(BackupProcessor $processor): array
   {
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

         event(new BackupSucceeded($result['path'], $result['disk'], $result['size']));

         return [$result['path']];
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

   private function sendSuccessNotification(array $result): void
   {
       $notifiable = $this->createNotifiable($this->notifyOnSuccess);
       Notification::send($notifiable, new BackupSucceededNotification($result['path'], $result['disk'], $result['size']));
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
         'maxLocalBackups' => $this->maxLocalBackups,
         'keepLocal' => $this->keepLocal,
         'localStorageDir' => $this->getLocalStorageDir(),
         'remoteStorageDir' => $this->getRemoteStorageDir(),
         'encryptionPassword' => $this->encryptionPassword,
         'notifyOnSuccess' => $this->notifyOnSuccess,
         'notifyOnFailure' => $this->notifyOnFailure,
         'beforeHook' => $this->beforeHook,
         'afterHook' => $this->afterHook,
         'shouldCompress' => $this->shouldCompress,
      ];
   }

   private function getLocalStorageDir(): string
   {
      if ($this->localStorageDir) {
         return $this->localStorageDir;
      }

      $path = config('easy-backups.defaults.database.local_storage_path');
      return str_starts_with($path, '/') ? $path : storage_path($path);
   }

   private function getRemoteStorageDir(): string
   {
      return $this->remoteStorageDir ?? config('easy-backups.defaults.database.remote_storage_path');
   }
}
