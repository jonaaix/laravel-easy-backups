<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Actions;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

final class CleanupBackupsAction
{
   public function execute(string $disk, string $path, int $maxBackups): void
   {
      if ($maxBackups <= 0) {
         return;
      }

      if ($disk === 'local') {
         $this->cleanupLocalBackups($path, $maxBackups);
      } else {
         $this->cleanupRemoteBackups($disk, $path, $maxBackups);
      }
   }

   private function cleanupRemoteBackups(string $disk, string $path, int $maxBackups): void
   {
      $remoteDisk = Storage::disk($disk);
      $allBackups = collect($remoteDisk->files($path))
         ->filter(fn($file) => str_starts_with(basename($file), 'backup-') && str_ends_with($file, '.zip'))
         ->mapWithKeys(fn($file) => [$file => $remoteDisk->lastModified($file)])
         ->sort();

      if ($allBackups->count() > $maxBackups) {
         $filesToDelete = $allBackups->keys()->slice(0, $allBackups->count() - $maxBackups);
         $remoteDisk->delete($filesToDelete->toArray());
      }
   }

   private function cleanupLocalBackups(string $path, int $maxBackups): void
   {
      $allBackups = collect(File::files($path))
         ->filter(fn(\SplFileInfo $file) => str_starts_with($file->getFilename(), 'backup-') && $file->getExtension() === 'zip')
         ->mapWithKeys(fn(\SplFileInfo $file) => [$file->getPathname() => $file->getMTime()])
         ->sort();

      if ($allBackups->count() > $maxBackups) {
         $filesToDelete = $allBackups->keys()->slice(0, $allBackups->count() - $maxBackups);
         File::delete($filesToDelete->toArray());
      }
   }
}
