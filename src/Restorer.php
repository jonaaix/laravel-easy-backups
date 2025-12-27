<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

   // Private constructor to enforce static entry points
   private function __construct() {}

   /**
    * Start a restore process for a database.
    */
   public static function database(): self
   {
      return new self();
   }

   // TODO: Other restore types

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

      $job = new RestoreJob(
         sourceDisk: $this->disk,
         sourcePath: $this->path,
         sourceDirectory: $this->directory ?? config('easy-backups.defaults.database.remote_storage_path'),
         databaseConnection: $this->databaseConnection,
         password: $this->password,
         shouldWipe: $this->shouldWipe,
         useLatest: $this->useLatest,
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

   public static function getRecentBackups(string $disk, ?string $directory = null, int $count = 30): Collection
   {
      $storageDisk = Storage::disk($disk);
      $searchPath = $directory ?? config('easy-backups.defaults.database.remote_storage_path');

      return collect($storageDisk->files($searchPath))
         ->filter(fn (string $file) => Str::endsWith($file, ['.zip', '.sql', '.tar', '.gz', '.zst']))
         ->mapWithKeys(fn (string $file) => [$file => $storageDisk->lastModified($file)])
         ->sortDesc()
         ->take($count)
         ->map(function (int $timestamp, string $file) use ($storageDisk): array {
            $extension = pathinfo($file, PATHINFO_EXTENSION);
            return [
               'path' => $file,
               'label' => sprintf(
                  '[%s] %s (%s, %s)',
                  strtoupper($extension),
                  basename($file),
                  self::formatSize($storageDisk->size($file)),
                  now()->createFromTimestamp($timestamp)->diffForHumans()
               ),
            ];
         });
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
