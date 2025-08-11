<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Actions;

use Illuminate\Support\Facades\File;
use ZipArchive;

final class VerifyBackupAction
{
   public function execute(string $path): string
   {
      if (!File::exists($path)) {
         return 'Backup file does not exist.';
      }

      if (File::size($path) === 0) {
         return 'Backup file is empty.';
      }

      $zip = new ZipArchive();
      $res = $zip->open($path, ZipArchive::CHECKCONS);

      if ($res !== true) {
         return 'Backup archive is corrupted or invalid. ZipArchive error code: ' . $res;
      }

      $zip->close();

      return 'ok';
   }
}
