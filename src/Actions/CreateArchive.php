<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Actions;

use Aaix\LaravelEasyBackups\Contracts\ProcessExecutor;
use Aaix\LaravelEasyBackups\Enums\CompressionFormatEnum;
use Exception;
use Illuminate\Support\Facades\File;
use ZipArchive;

final class CreateArchive
{
   public function __construct(
      private readonly ProcessExecutor $executor
   ) {
   }

   public function execute(
      string $archivePath,
      array $files,
      array $directories,
      ?string $password = null
   ): CompressionFormatEnum {
      // Force ZIP if password is required (standard tar usually doesn't support encryption portably)
      if ($password !== null) {
         $this->createZip($archivePath, $files, $directories, $password);
         return CompressionFormatEnum::ZIP;
      }

      $format = $this->determineBestFormat();

      match ($format) {
         CompressionFormatEnum::ZIP => $this->createZip($archivePath, $files, $directories, null),
         CompressionFormatEnum::ZSTD => $this->createTar($archivePath, $files, $directories, 'zstd'),
         CompressionFormatEnum::GZIP => $this->createTar($archivePath, $files, $directories, 'gzip'),
         CompressionFormatEnum::TAR => $this->createTar($archivePath, $files, $directories, null),
      };

      return $format;
   }

   private function determineBestFormat(): CompressionFormatEnum
   {
      if ($this->isBinaryAvailable('zstd')) {
         return CompressionFormatEnum::ZSTD;
      }

      // Prefer GZIP via System Binary or Extension
      if ($this->isBinaryAvailable('gzip') || extension_loaded('zlib')) {
         return CompressionFormatEnum::GZIP;
      }

      if (extension_loaded('zip')) {
         return CompressionFormatEnum::ZIP;
      }

      return CompressionFormatEnum::TAR;
   }

   private function isBinaryAvailable(string $binary): bool
   {
      // Simple check if binary exists in path
      return !empty(shell_exec(sprintf('which %s', escapeshellarg($binary))));
   }

   private function createTar(string $path, array $files, array $directories, ?string $compression): void
   {
      $flag = match ($compression) {
         'zstd' => '--zstd',
         'gzip' => '-z',
         default => '',
      };

      $sources = array_merge($files, $directories);
      // Ensure we are in the root context or handle absolute paths correctly.
      // For simplicity with docker/local, we assume absolute paths are passed and tar handles them.
      $sourcesString = implode(' ', array_map('escapeshellarg', $sources));

      // -cf = Create File
      $command = sprintf('tar %s -cf %s %s', $flag, escapeshellarg($path), $sourcesString);

      // Execute in the root directory to allow absolute paths in sources
      $this->executor->execute($command, '/');
   }

   private function createZip(string $path, array $files, array $directories, ?string $password): void
   {
      if (!extension_loaded('zip')) {
         throw new Exception('PHP extension "zip" is required for ZIP compression.');
      }

      $zip = new ZipArchive();
      if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
         throw new Exception("Cannot open zip archive: {$path}");
      }

      if ($password) {
         $zip->setPassword($password);
      }

      foreach ($files as $file) {
         if (File::exists($file)) {
            $name = basename($file);
            $zip->addFile($file, $name);
            if ($password) {
               $zip->setEncryptionName($name, ZipArchive::EM_AES_256);
            }
         }
      }

      foreach ($directories as $directory) {
         $this->addDirectoryToZip($zip, $directory, $password);
      }

      if (!$zip->close()) {
         throw new Exception("Failed to write and close zip archive: {$path}");
      }
   }

   private function addDirectoryToZip(ZipArchive $zip, string $directory, ?string $password): void
   {
      if (!File::isDirectory($directory)) {
         return;
      }

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
