<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Actions;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

final class UploadBackupAction
{
   public function execute(string $localPath, string $disk, string $remotePath): void
   {
      $remoteDisk = Storage::disk($disk);
      $remotePath = rtrim($remotePath, '/') . '/' . basename($localPath);
      $remoteDisk->put($remotePath, File::get($localPath), 'private');
   }
}
