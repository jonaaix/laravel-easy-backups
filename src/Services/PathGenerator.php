<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class PathGenerator
{
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

   public function getDatabaseLocalDisk(): string
   {
      return config('easy-backups.defaults.database.local_disk', 'local');
   }

   public function getFilesLocalDisk(): string
   {
      return config('easy-backups.defaults.files.local_disk', 'local');
   }

   public function getDatabaseRemotePath(
      string $connectionName,
      ?string $customBase = null,
      ?bool $enableEnvPathPrefix = null,
      ?string $targetEnv = null
   ): string {
      if ($customBase) {
         return $this->buildRemotePath(null, $customBase, $targetEnv, $enableEnvPathPrefix);
      }

      $base = config('easy-backups.defaults.database.remote_path', 'db-backups');
      $driver = config("database.connections.{$connectionName}.driver", 'unknown');

      return $this->buildRemotePath($base, $driver, $targetEnv, $enableEnvPathPrefix);
   }

   public function getFilesRemotePath(
      string $namePrefix,
      ?string $customBase = null,
      ?bool $enableEnvPathPrefix = null,
      ?string $targetEnv = null
   ): string {
      if ($customBase) {
         return $this->buildRemotePath(null, $customBase, $targetEnv, $enableEnvPathPrefix);
      }

      $base = config('easy-backups.defaults.files.remote_path', 'file-backups');
      $subFolder = Str::slug($namePrefix);

      return $this->buildRemotePath($base, $subFolder, $targetEnv, $enableEnvPathPrefix);
   }

   public function getAbsoluteTempPath(): string
   {
      return Storage::disk($this->getDatabaseLocalDisk())->path($this->getTempPath());
   }

   public function getAbsoluteDatabaseLocalPath(): string
   {
      return Storage::disk($this->getDatabaseLocalDisk())->path($this->getDatabaseLocalPath());
   }

   public function getAbsoluteFilesLocalPath(): string
   {
      return Storage::disk($this->getFilesLocalDisk())->path($this->getFilesLocalPath());
   }

   private function buildRemotePath(?string $base, string $subFolder, ?string $env = null, ?bool $enableEnvPathPrefix = null): string
   {
      $parts = [];

      $shouldPrefix = $enableEnvPathPrefix ?? config('easy-backups.defaults.strategy.prefix_env', true);

      if ($shouldPrefix) {
         $parts[] = $env ?? (string) config('app.env');
      }

      if ($base) {
         $parts[] = trim($base, '/');
      }

      $parts[] = trim($subFolder, '/');

      return implode('/', array_filter($parts));
   }
}
