<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Central place for discovering existing backup artifacts on any disk.
 * Used by the list command, cleanup action, and restore (find-latest).
 */
final class BackupInventoryService
{
   public const BACKUP_EXTENSIONS = ['.zip', '.tar', '.gz', '.zst', '.sql'];

   /**
    * List all backup files within a path on a given disk.
    * Returns a collection of entries sorted newest-first.
    *
    * Each entry: ['path' => string, 'filename' => string, 'size' => int, 'last_modified' => int]
    */
   public function list(string $disk, string $path, bool $recursive = false): Collection
   {
      $driver = config("filesystems.disks.{$disk}.driver");

      return $driver === 'local'
         ? $this->listLocal($disk, $path, $recursive)
         : $this->listRemote($disk, $path, $recursive);
   }

   public function findLatest(string $disk, string $path, bool $recursive = false): ?array
   {
      return $this->list($disk, $path, $recursive)->first();
   }

   public static function isBackupFile(string $filename): bool
   {
      return Str::endsWith($filename, self::BACKUP_EXTENSIONS);
   }

   private function listRemote(string $disk, string $path, bool $recursive): Collection
   {
      $remoteDisk = Storage::disk($disk);
      $files = $recursive ? $remoteDisk->allFiles($path) : $remoteDisk->files($path);

      return collect($files)
         ->filter(fn(string $file) => self::isBackupFile(basename($file)))
         ->map(fn(string $file) => [
            'path' => $file,
            'filename' => basename($file),
            'size' => $remoteDisk->size($file),
            'last_modified' => $remoteDisk->lastModified($file),
         ])
         ->sortByDesc('last_modified')
         ->values();
   }

   private function listLocal(string $disk, string $path, bool $recursive): Collection
   {
      // Prefer a disk-relative lookup so both absolute and disk-root-relative paths work.
      $absolutePath = Storage::disk($disk)->path($path);
      if (!File::isDirectory($absolutePath) && File::isDirectory($path)) {
         $absolutePath = $path;
      }

      if (!File::isDirectory($absolutePath)) {
         return collect();
      }

      $files = $recursive ? File::allFiles($absolutePath) : File::files($absolutePath);

      return collect($files)
         ->filter(fn(\SplFileInfo $file) => self::isBackupFile($file->getFilename()))
         ->map(fn(\SplFileInfo $file) => [
            'path' => $file->getPathname(),
            'filename' => $file->getFilename(),
            'size' => $file->getSize(),
            'last_modified' => $file->getMTime(),
         ])
         ->sortByDesc('last_modified')
         ->values();
   }
}
