<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Actions;

use Aaix\LaravelEasyBackups\Services\ConsoleFeedback;
use Aaix\LaravelEasyBackups\Services\ProgressReportingStream;
use Aaix\LaravelEasyBackups\Services\TransferProgress;
use Illuminate\Support\Facades\Storage;

final class UploadBackupAction
{
   public function execute(string $localPath, string $disk, string $remotePath): void
   {
      $remoteDisk = Storage::disk($disk);
      $fileName = basename($localPath);
      $targetPath = rtrim($remotePath, '/') . '/' . $fileName;

      ConsoleFeedback::step("Uploading backup to disk '{$disk}'...");
      $progress = TransferProgress::start('Uploaded', (int) filesize($localPath));

      // Use stream upload to prevent Out-Of-Memory on large files
      $fileStream = ProgressReportingStream::open($localPath, $progress);

      try {
         $remoteDisk->put($targetPath, $fileStream, 'private');
      } finally {
         if (is_resource($fileStream)) {
            fclose($fileStream);
         }
      }

      $progress->finish();
   }
}
