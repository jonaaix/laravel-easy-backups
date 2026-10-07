<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups;

use Aaix\LaravelEasyBackups\Services\BackupInventoryService;
use Aaix\LaravelEasyBackups\Services\PathGenerator;
use Illuminate\Support\Collection;

final class Restorer
{
   private ?string $disk = null;
   private ?string $path = null;
   private ?string $directory = null;
   private ?string $databaseConnection = null;
   private ?string $password = null;
   private ?string $queue = null;
   private ?string $connection = null;
   private bool $shouldWipe = true;
   private bool $useLatest = false;
   private ?string $saveCopyDisk = null;

   // Private constructor to enforce static entry points
   public function __construct()
   {
   }

   /**
    * Start a restore process for a database.
    */
   public static function database(): self
   {
      return new self();
   }

   public function toDatabase(string $connection): self
   {
      $this->databaseConnection = $connection;
      return $this;
   }

   public function fromDisk(string $disk): self
   {
      $this->disk = $disk;
      return $this;
   }

   public function fromDir(string $directory): self
   {
      $this->directory = $directory;
      return $this;
   }

   public function fromPath(string $path): self
   {
      $this->path = $path;
      return $this;
   }

   public function withPassword(string $password): self
   {
      $this->password = $password;
      return $this;
   }

   public function disableWipe(): self
   {
      $this->shouldWipe = false;
      return $this;
   }

   public function latest(): self
   {
      $this->useLatest = true;
      return $this;
   }

   public function saveCopyTo(string $disk): self
   {
      $this->saveCopyDisk = $disk;
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
      if (is_null($this->databaseConnection)) {
         throw new \InvalidArgumentException('A target database connection must be defined using toDatabase().');
      }

      // Resolve default directory using PathGenerator if not manually overridden
      $sourceDirectory = $this->directory
         ?? app(PathGenerator::class)->getDatabaseRemotePath($this->databaseConnection);

      $job = new RestoreJob(
         sourceDisk: $this->disk,
         sourcePath: $this->path,
         sourceDirectory: $sourceDirectory,
         databaseConnection: $this->databaseConnection,
         password: $this->password,
         shouldWipe: $this->shouldWipe,
         useLatest: $this->useLatest,
         saveCopyDisk: $this->saveCopyDisk,
      );

      if (is_null($this->connection) && is_null($this->queue)) {
         return app()->call([$job, 'handle']);
      }

      $dispatch = dispatch($job)->onConnection($this->connection);
      if ($this->queue) {
         $dispatch->onQueue($this->queue);
      }

      return $dispatch;
   }

   public static function getRecentBackups(string $disk, string $directory, int $count = 30): Collection
   {
      $inventory = app(BackupInventoryService::class);

      return $inventory
         ->list($disk, $directory)
         ->take($count)
         ->map(fn(array $entry): array => [
            'path' => $inventory->diskRelativePath($disk, $entry['path']),
            'label' => sprintf(
               '[%s] %s (%s, %s)',
               strtoupper(pathinfo($entry['filename'], PATHINFO_EXTENSION)),
               $entry['filename'],
               self::formatSize($entry['size']),
               now()->createFromTimestamp($entry['last_modified'])->diffForHumans()
            ),
         ]);
   }

   private static function formatSize(int $bytes): string
   {
      $units = ['B', 'KB', 'MB', 'GB', 'TB'];
      $i = 0;
      $size = (float) $bytes;
      while ($size >= 1024 && $i < 4) {
         $size /= 1024;
         $i++;
      }
      return round($size, 2) . ' ' . $units[$i];
   }
}
