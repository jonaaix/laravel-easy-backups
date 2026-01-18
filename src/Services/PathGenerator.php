<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Services;

use Illuminate\Support\Str;

final class PathGenerator
{
   // --- Relative Paths (for Storage Facade) ---

   public function getTempPath(): string
   {
      return config('easy-backups.defaults.temp_path', 'easy-backups/tmp');
   }

   public function getDatabaseLocalPath(): string
   {
      return config('easy-backups.defaults.database.local_path', 'easy-backups/database');
   }

   public function getFilesLocalPath(): string
   {
      return config('easy-backups.defaults.files.local_path', 'easy-backups/files');
   }

   public function getDatabaseRemotePath(string $connectionName, ?string $env = null): string
   {
      $base = config('easy-backups.defaults.database.remote_path', 'db-backups');
      $driver = config("database.connections.{$connectionName}.driver", 'unknown');

      return $this->buildRemotePath($base, $driver, $env);
   }

   public function getFilesRemotePath(string $namePrefix, ?string $env = null): string
   {
      $base = config('easy-backups.defaults.files.remote_path', 'file-backups');
      $subFolder = Str::slug($namePrefix);

      return $this->buildRemotePath($base, $subFolder, $env);
   }

   // --- Absolute Paths (for File Facade / Native Operations) ---

   public function getAbsoluteTempPath(): string
   {
      return storage_path('app/' . $this->getTempPath());
   }

   public function getAbsoluteDatabaseLocalPath(): string
   {
      return storage_path('app/' . $this->getDatabaseLocalPath());
   }

   public function getAbsoluteFilesLocalPath(): string
   {
      return storage_path('app/' . $this->getFilesLocalPath());
   }

   // --- Internal Logic ---

   private function buildRemotePath(string $base, string $subFolder, ?string $env = null): string
   {
      $parts = [];

      if (config('easy-backups.defaults.strategy.prefix_env', true)) {
         $parts[] = $env ?? (string) config('app.env');
      }

      $parts[] = trim($base, '/');
      $parts[] = trim($subFolder, '/');

      return implode('/', array_filter($parts));
   }
}
