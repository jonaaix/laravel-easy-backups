<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Commands;

use Aaix\LaravelEasyBackups\Facades\Restorer;
use Illuminate\Console\Command;
use function Laravel\Prompts\select;

class RestoreDatabaseBackupCommand extends Command
{
   protected $signature = 'aaix:backup:db:restore
                            {--from-disk= : The disk to restore from (defaults to "local")}
                            {--to-database= : The database connection to restore to (defaults to the default connection)}
                            {--latest : Restore the latest backup without prompting}
                            {--password= : The password for an encrypted backup}';

   protected $description = 'Restores a database backup from a local or remote disk.';

   public function handle(): int
   {
      if ($this->option('latest')) {
         return $this->restoreLatest();
      }

      return $this->restoreFromSelection();
   }

   private function restoreLatest(): int
   {
      $disk = $this->option('from-disk') ?? 'local';
      $database = $this->option('to-database') ?? config('database.default');

      if (!$this->confirm("Are you sure you want to restore the LATEST backup from disk '{$disk}' to database '{$database}'? This will wipe the database.")) {
         return self::SUCCESS;
      }

      $this->info("Starting restore of the LATEST backup from '{$disk}'...");

      $restorer = Restorer::create()
         ->fromDisk($disk)
         ->toDatabase($database)
         ->latest();

      if ($password = $this->option('password')) {
         $restorer->withPassword($password);
      }

      $restorer->run();

      $this->info('Latest database backup restored successfully!');

      return self::SUCCESS;
   }

   private function restoreFromSelection(): int
   {
      $disk = $this->option('from-disk') ?? 'local';
      $database = $this->option('to-database') ?? config('database.default');

      $this->info("Fetching recent backups from disk '{$disk}'...");

      $backups = Restorer::getRecentBackups($disk);

      if ($backups->isEmpty()) {
         $this->warn("No backups found on disk '{$disk}'.");
         return self::SUCCESS;
      }

      $selectedPath = select(
         label: 'Which backup would you like to restore?',
         options: $backups->pluck('label', 'path')->all(),
         scroll: 10,
         required: true
      );

      if ($this->confirm("Are you sure you want to restore '" . basename($selectedPath) . "' to database '{$database}'? This will wipe the database.")) {
         $this->info("Starting restore of '" . basename($selectedPath) . "'...");

         $restorer = Restorer::create()
            ->fromDisk($disk)
            ->fromPath($selectedPath)
            ->toDatabase($database);

         if ($password = $this->option('password')) {
            $restorer->withPassword($password);
         }

         $restorer->run();

         $this->info('Database backup restored successfully!');
      }

      return self::SUCCESS;
   }
}
