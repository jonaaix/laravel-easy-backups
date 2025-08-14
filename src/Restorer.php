<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class Restorer
{
   private ?string $disk = null;
   private ?string $path = null;
   private ?string $databaseConnection = null;
   private ?string $password = null;
   private ?string $queue = null;
   private ?string $connection = null;
   private bool $shouldWipe = true;
   private bool $useLatest = false;

   public static function create(): self
   {
      return new self();
   }

   public function fromDisk(string $disk): self
   {
      $this->disk = $disk;
      return $this;
   }

   public function fromPath(string $path): self
   {
      $this->path = $path;
      return $this;
   }

   public function toDatabase(string $connection): self
   {
      $this->databaseConnection = $connection;
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
      $job = new RestoreJob(
         sourceDisk: $this->disk,
         sourcePath: $this->path,
         databaseConnection: $this->databaseConnection,
         password: $this->password,
         shouldWipe: $this->shouldWipe,
         useLatest: $this->useLatest,
      );

      if (is_null($this->connection) && is_null($this->queue)) {
         return app()->call([$job, 'handle']);
      }

      // onConnection(null) uses the default queue connection
      $dispatch = dispatch($job)->onConnection($this->connection);
      if ($this->queue) {
         $dispatch->onQueue($this->queue);
      }

      return $dispatch;
   }

   public static function getRecentBackups(string $disk, int $count = 30): \Illuminate\Support\Collection
   {
      $storageDisk = Storage::disk($disk);
      $path = config('easy-backups.defaults.database.remote_storage_path');

      return collect($storageDisk->files($path))
         ->filter(fn ($file) => Str::startsWith(basename($file), 'backup-') && Str::endsWith($file, '.zip'))
         ->mapWithKeys(fn ($file) => [$file => $storageDisk->lastModified($file)])
         ->sortDesc()
         ->take($count)
         ->map(function ($timestamp, $file) use ($storageDisk) {
            return [
               'path' => $file,
               'label' => sprintf('%s (%s, %s)',
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
      while ($bytes >= 1024 && $i < 4) {
         $bytes /= 1024;
         $i++;
      }
      return round($bytes, 2) . ' ' . $units[$i];
   }
}
