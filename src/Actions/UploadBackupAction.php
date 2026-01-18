<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Actions;

use Illuminate\Support\Facades\Storage;

final class UploadBackupAction
{
   public function execute(string $localPath, string $disk, string $remotePath): void
   {
      $remoteDisk = Storage::disk($disk);
      $fileName = basename($localPath);
      $targetPath = rtrim($remotePath, '/') . '/' . $fileName;

      // Use stream upload to prevent Out-Of-Memory on large files
      $fileStream = fopen($localPath, 'rb');

      try {
         $remoteDisk->put($targetPath, $fileStream, 'private');
      } finally {
         if (is_resource($fileStream)) {
            fclose($fileStream);
         }
      }
   }
}
