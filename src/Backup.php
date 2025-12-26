<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups;

use Illuminate\Foundation\Bus\PendingDispatch;

final class Backup
{
   private array $databasesToInclude = [];
   private array $filesToInclude = [];
   private array $directoriesToInclude = [];
   private ?string $saveTo = null;
   private int $maxRemoteBackups = 0;
   private int $maxLocalBackups = 0;
   private bool $keepLocal = false;
   private ?string $queue = null;
   private ?string $connection = null;
   private ?string $localStorageDir = null;
   private ?string $remoteStorageDir = null;
   private ?string $encryptionPassword = null;
   private array $notifyOnSuccess = [];
   private array $notifyOnFailure = [];
   private ?string $beforeHook = null;
   private ?string $afterHook = null;
   private ?string $tempDirectory = null;
   private bool $shouldCompress = false;
   private string $namePrefix = 'backup';

   /**
    * Start a database-specific backup.
    */
   public static function database(string $connection): self
   {
      $instance = new self();
      $instance->databasesToInclude = [$connection];
      $instance->namePrefix = "db-{$connection}";
      return $instance;
   }

   /**
    * Start a custom file backup.
    */
   public static function files(): self
   {
      $instance = new self();
      $instance->namePrefix = 'files';
      $instance->shouldCompress = true;
      return $instance;
   }

   /**
    * Shortcut to backup the storage folder.
    */
   public static function storage(?string $path = null): self
   {
      $instance = self::files();
      $instance->directoriesToInclude = [$path ?? storage_path('app')];
      $instance->namePrefix = 'storage';
      return $instance;
   }

   /**
    * Shortcut to backup the .env file.
    */
   public static function env(): self
   {
      $instance = self::files();
      $instance->filesToInclude = [base_path('.env')];
      $instance->namePrefix = 'env';
      return $instance;
   }

   public function includeFiles(array $files): self
   {
      $this->filesToInclude = array_merge($this->filesToInclude, $files);
      return $this;
   }

   public function includeDirectories(array $directories): self
   {
      $this->directoriesToInclude = array_merge($this->directoriesToInclude, $directories);
      return $this;
   }

   public function saveTo(string $disk): self
   {
      $this->saveTo = $disk;
      return $this;
   }

   /**
    * Set a custom directory path for the remote storage.
    */
   public function setRemoteStorageDir(string $path): self
   {
      $this->remoteStorageDir = $path;
      return $this;
   }

   /**
    * Set a custom directory path for local storage before upload.
    */
   public function setLocalStorageDir(string $path): self
   {
      $this->localStorageDir = $path;
      return $this;
   }

   public function maxRemoteBackups(int $count): self
   {
      $this->maxRemoteBackups = $count;
      return $this;
   }

   public function maxLocalBackups(int $count): self
   {
      $this->maxLocalBackups = $count;
      return $this;
   }

   public function keepLocal(): self
   {
      $this->keepLocal = true;
      return $this;
   }

   public function compress(): self
   {
      $this->shouldCompress = true;
      return $this;
   }

   public function encryptWithPassword(string $password): self
   {
      $this->encryptionPassword = $password;
      $this->shouldCompress = true;
      return $this;
   }

   public function before(string $hookClass): self
   {
      $this->beforeHook = $hookClass;
      return $this;
   }

   public function after(string $hookClass): self
   {
      $this->afterHook = $hookClass;
      return $this;
   }

   public function notifyOnSuccess(string|array $channels, ?string $to = null): self
   {
      $this->notifyOnSuccess = ['channels' => (array)$channels, 'to' => $to];
      return $this;
   }

   public function notifyOnFailure(string|array $channels, ?string $to = null): self
   {
      $this->notifyOnFailure = ['channels' => (array)$channels, 'to' => $to];
      return $this;
   }

   public function onQueue(string $queue): self
   {
      $this->queue = $queue;
      return $this;
   }

   public function onConnection(string $connection): self
   {
      $this->connection = $connection;
      return $this;
   }

   public function setTempDirectory(string $path): self
   {
      $this->tempDirectory = $path;
      return $this;
   }

   public function run(): mixed
   {
      $job = new BackupJob(
         databasesToInclude: $this->databasesToInclude,
         filesToInclude: $this->filesToInclude,
         directoriesToInclude: $this->directoriesToInclude,
         saveTo: $this->saveTo,
         maxRemoteBackups: $this->maxRemoteBackups,
         maxLocalBackups: $this->maxLocalBackups,
         keepLocal: $this->keepLocal,
         localStorageDir: $this->localStorageDir,
         remoteStorageDir: $this->remoteStorageDir,
         encryptionPassword: $this->encryptionPassword,
         notifyOnSuccess: $this->notifyOnSuccess,
         notifyOnFailure: $this->notifyOnFailure,
         beforeHook: $this->beforeHook,
         afterHook: $this->afterHook,
         tempDirectory: $this->tempDirectory,
         shouldCompress: $this->shouldCompress,
         namePrefix: $this->namePrefix
      );

      if (is_null($this->connection) && is_null($this->queue)) {
         return app()->call([$job, 'handle']);
      }

      /** @var PendingDispatch $dispatch */
      $dispatch = dispatch($job)->onConnection($this->connection);

      if ($this->queue) {
         $dispatch->onQueue($this->queue);
      }

      return $dispatch;
   }
}
