<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Actions;

use Aaix\LaravelEasyBackups\Events\CleanupSucceeded;
use Aaix\LaravelEasyBackups\Services\BackupInventoryService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

final class CleanupBackupsAction
{
   public function __construct(private readonly BackupInventoryService $inventory)
   {
   }

   public function execute(string $disk, string $path, int $maxBackups, int $maxDays = 0): void
   {
      if ($maxBackups <= 0 && $maxDays <= 0) {
         return;
      }

      $driver = config("filesystems.disks.{$disk}.driver");

      if ($driver === 'local') {
         $this->cleanupLocalBackups($disk, $path, $maxBackups, $maxDays);
      } else {
         $this->cleanupRemoteBackups($disk, $path, $maxBackups, $maxDays);
      }
   }

   private function cleanupRemoteBackups(string $disk, string $path, int $maxBackups, int $maxDays): void
   {
      // Inventory returns newest-first; flip so deletion logic operates oldest-first.
      $backups = $this->inventory->list($disk, $path)->reverse()->values();
      if ($backups->isEmpty()) {
         return;
      }

      $remoteDisk = Storage::disk($disk);
      $deletedCount = 0;

      if ($maxDays > 0) {
         $threshold = now()->subDays($maxDays)->getTimestamp();
         [$expired, $backups] = $backups->partition(fn(array $entry) => $entry['last_modified'] < $threshold);

         if ($expired->isNotEmpty()) {
            $remoteDisk->delete($expired->pluck('path')->all());
            $deletedCount += $expired->count();
            $backups = $backups->values();
         }
      }

      if ($maxBackups > 0 && $backups->count() > $maxBackups) {
         $toDelete = $backups->slice(0, $backups->count() - $maxBackups);
         $remoteDisk->delete($toDelete->pluck('path')->all());
         $deletedCount += $toDelete->count();
      }

      if ($deletedCount > 0) {
         event(new CleanupSucceeded($disk, $path, $deletedCount));
      }
   }

   private function cleanupLocalBackups(string $disk, string $path, int $maxBackups, int $maxDays): void
   {
      $backups = $this->inventory->list($disk, $path)->reverse()->values();
      if ($backups->isEmpty()) {
         return;
      }

      $deletedCount = 0;

      if ($maxDays > 0) {
         $threshold = now()->subDays($maxDays)->getTimestamp();
         [$expired, $backups] = $backups->partition(fn(array $entry) => $entry['last_modified'] < $threshold);

         if ($expired->isNotEmpty()) {
            File::delete($expired->pluck('path')->all());
            $deletedCount += $expired->count();
            $backups = $backups->values();
         }
      }

      if ($maxBackups > 0 && $backups->count() > $maxBackups) {
         $toDelete = $backups->slice(0, $backups->count() - $maxBackups);
         File::delete($toDelete->pluck('path')->all());
         $deletedCount += $toDelete->count();
      }

      if ($deletedCount > 0) {
         event(new CleanupSucceeded('local', $path, $deletedCount));
      }
   }
}
