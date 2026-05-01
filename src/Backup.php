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
   private int $maxRemoteDays = 0;
   private int $maxLocalBackups = 0;
   private int $maxLocalDays = 0;
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
   private ?string $filenameSuffix = null;
   private ?bool $enableEnvPathPrefix = null;
   private bool $onlyLocal = false;
   private array $excludeTables = [];
   private array $excludeTableData = [];
   private bool $dryRun = false;

   /** Start a database-specific backup. */
   public static function database(string $connection): self
   {
      $instance = new self();
      $instance->databasesToInclude = [$connection];
      $instance->namePrefix = "db-{$connection}";
      return $instance;
   }

   /** Start a custom file backup. */
   public static function files(): self
   {
      $instance = new self();
      $instance->namePrefix = 'files';
      $instance->shouldCompress = true;
      return $instance;
   }

   public function setName(string $name): self
   {
      $this->filenameSuffix = $name;
      return $this;
   }

   public function includeStorage(?string $path = null): self
   {
      $this->directoriesToInclude[] = $path ?? storage_path('app');
      return $this;
   }

   public function includeEnv(): self
   {
      $this->filesToInclude[] = base_path('.env');
      return $this;
   }

   public function enableEnvPathPrefix(bool $enabled = true): self
   {
      $this->enableEnvPathPrefix = $enabled;
      return $this;
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

   public function onlyLocal(): self
   {
      $this->onlyLocal = true;
      return $this;
   }

   public function setRemoteStorageDir(string $path): self
   {
      $this->remoteStorageDir = $path;
      return $this;
   }

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

   public function maxRemoteDays(int $days): self
   {
      $this->maxRemoteDays = $days;
      return $this;
   }

   public function maxLocalBackups(int $count): self
   {
      $this->maxLocalBackups = $count;
      return $this;
   }

   public function maxLocalDays(int $days): self
   {
      $this->maxLocalDays = $days;
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

   /**
    * Print what would be done without executing anything.
    * No dumps are created, no files are written, no uploads happen.
    */
   public function dryRun(bool $enabled = true): self
   {
      $this->dryRun = $enabled;
      return $this;
   }

   /** Exclude tables entirely (no structure, no data). */
   public function excludeTables(array $tables): self
   {
      $this->excludeTables = array_values(array_unique(array_merge($this->excludeTables, $tables)));
      return $this;
   }

   /** Export table structure only (skip row data). Useful for sensitive tables. */
   public function excludeTableData(array $tables): self
   {
      $this->excludeTableData = array_values(array_unique(array_merge($this->excludeTableData, $tables)));
      return $this;
   }

   public function setTempDirectory(string $path): self
   {
      $this->tempDirectory = $path;
      return $this;
   }

   public function run(): mixed
   {
      // Resolve Default Disk from Config if not explicitly set AND not explicitly Local Only
      $saveToDisk = $this->saveTo;

      if ($saveToDisk === null && !$this->onlyLocal) {
         if (!empty($this->databasesToInclude)) {
            $saveToDisk = config('easy-backups.defaults.database.remote_disk');
         } elseif (!empty($this->filesToInclude)) {
            $saveToDisk = config('easy-backups.defaults.files.remote_disk');
         }
      }

      // Merge config defaults with fluent-provided exclusions
      $configExcludeTables = (array) config('easy-backups.defaults.database.exclude_tables', []);
      $configExcludeTableData = (array) config('easy-backups.defaults.database.exclude_table_data', []);
      $excludeTables = array_values(array_unique(array_merge($configExcludeTables, $this->excludeTables)));
      $excludeTableData = array_values(array_unique(array_merge($configExcludeTableData, $this->excludeTableData)));

      $job = new BackupJob(
         databasesToInclude: $this->databasesToInclude,
         filesToInclude: $this->filesToInclude,
         directoriesToInclude: $this->directoriesToInclude,
         saveTo: $saveToDisk,
         maxRemoteBackups: $this->maxRemoteBackups,
         maxRemoteDays: $this->maxRemoteDays,
         maxLocalBackups: $this->maxLocalBackups,
         maxLocalDays: $this->maxLocalDays,
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
         namePrefix: $this->namePrefix,
         filenameSuffix: $this->filenameSuffix,
         enableEnvPathPrefix: $this->enableEnvPathPrefix,
         excludeTables: $excludeTables,
         excludeTableData: $excludeTableData,
         dryRun: $this->dryRun,
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
