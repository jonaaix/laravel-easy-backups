<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups;

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

   public static function create(): self
   {
      return new self();
   }

   public function setTempDirectory(string $path): self
   {
      $this->tempDirectory = $path;
      return $this;
   }

   public function includeDatabases(array $databases): self
   {
      $this->databasesToInclude = $databases;
      return $this;
   }

   public function includeFiles(array $files): self
   {
      $this->filesToInclude = $files;
      return $this;
   }

   public function includeDirectories(array $directories): self
   {
      $this->directoriesToInclude = $directories;
      return $this;
   }

   public function saveTo(string $disk): self
   {
      $this->saveTo = $disk;
      return $this;
   }

   public function setLocalStorageDir(string $path): self
   {
      $this->localStorageDir = $path;
      return $this;
   }

   public function setRemoteStorageDir(string $path): self
   {
      $this->remoteStorageDir = $path;
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
      $this->shouldCompress = true; // Encryption implies compression
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
         shouldCompress: $this->shouldCompress
      );

      if (($this->connection ?? config('queue.default')) === 'sync') {
         return app()->call([$job, 'handle']);
      }

      $dispatch = dispatch($job)->onConnection($this->connection);
      if ($this->queue) {
         $dispatch->onQueue($this->queue);
      }

      return $dispatch;
   }
}
