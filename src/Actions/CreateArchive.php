<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Actions;

use Exception;
use Illuminate\Support\Facades\File;
use ZipArchive;

final class CreateArchive
{
   public function execute(string $archivePath, array $files, array $directories, ?string $password = null): void
   {
      $zip = new ZipArchive();

      if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
         throw new Exception("Cannot open zip archive: {$archivePath}");
      }

      if ($password) {
         $zip->setPassword($password);
      }

      foreach ($files as $file) {
         if (File::exists($file)) {
            $entryName = basename($file);
            $zip->addFile($file, $entryName);
            if ($password) {
               $zip->setEncryptionName($entryName, ZipArchive::EM_AES_256);
            }
         }
      }

      foreach ($directories as $directory) {
         if (File::isDirectory($directory)) {
            $this->addDirectoryToZip($zip, $directory, $password);
         }
      }

      if (!$zip->close()) {
         throw new Exception("Failed to write and close zip archive: {$archivePath}");
      }
   }

   private function addDirectoryToZip(ZipArchive $zip, string $directory, ?string $password): void
   {
      $allFiles = File::allFiles($directory);
      $basePath = realpath(dirname($directory));

      foreach ($allFiles as $file) {
         $filePath = $file->getRealPath();
         $relativePath = ltrim(str_replace($basePath, '', $filePath), DIRECTORY_SEPARATOR);
         $zip->addFile($filePath, $relativePath);
         if ($password) {
            $zip->setEncryptionName($relativePath, ZipArchive::EM_AES_256);
         }
      }
   }
}
