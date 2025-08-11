<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Commands;

use Aaix\LaravelEasyBackups\Facades\Restorer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RestoreBackupCommand extends Command
{
   protected $signature = 'backup:restore {filename?}
                            {--disk= : The disk the backup is stored on}
                            {--database= : The database connection to restore to}
                            {--password= : The password for the encrypted backup}
                            {--no-wipe : Do not wipe the database before restoring}';

   protected $description = 'Restore a backup';

   public function handle(): int
   {
      $filename = $this->argument('filename');
      $diskName = $this->option('disk') ?? Storage::getDefaultDriver();

      if (!$filename) {
         $filename = $this->selectBackup($diskName);
      }

      if (!$filename) {
         $this->info('No backup selected.');
         return self::SUCCESS;
      }

      $database = $this->option('database');
      $password = $this->option('password');

      $fullPath = $this->constructFullPath($filename, $diskName);

      $this->info(sprintf('Restoring backup \'%s\' from disk \'%s\'...', $fullPath, $diskName));

      $restore = Restorer::create()
         ->fromDisk($diskName)
         ->fromPath($fullPath);

      if ($this->option('no-wipe')) {
         $restore->disableWipe();
      }

      if ($database) {
         $restore->toDatabase($database);
      }

      if ($password) {
         $restore->withPassword($password);
      }

      $restore->run();

      $this->info('Backup restored successfully.');

      return self::SUCCESS;
   }

   private function selectBackup(string $diskName): ?string
   {
      $backups = [];
      $disk = Storage::disk($diskName);
      $path = $this->getStoragePathForDisk($diskName);

      $files = $disk->files($path);

      foreach ($files as $file) {
         $backups[] = [
            'full_path' => $file,
            'name' => basename($file),
            'disk' => $diskName,
         ];
      }

      if (empty($backups)) {
         return null;
      }

      $choices = array_map(fn ($backup) => sprintf('%s (%s)', $backup['name'], $backup['disk']), $backups);

      $choice = $this->choice('Select a backup to restore', $choices);

      $selectedIndex = array_search($choice, $choices, true);

      return $backups[$selectedIndex]['full_path'] ?? null;
   }

   private function getStoragePathForDisk(string $diskName): string
   {
      if ($diskName === 'local') {
         return config('easy-backups.defaults.database.local_storage_path');
      }
      return config('easy-backups.defaults.database.remote_storage_path');
   }

   private function constructFullPath(string $filename, string $diskName): string
   {
      if (Str::contains($filename, '/')) {
         return $filename;
      }

      $pathPrefix = $this->getStoragePathForDisk($diskName);
      return rtrim($pathPrefix, '/') . '/' . $filename;
   }
}
