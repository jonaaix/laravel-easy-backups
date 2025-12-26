<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Commands;

use Aaix\LaravelEasyBackups\Facades\Backup;
use Illuminate\Console\Command;

class CreateDatabaseBackupCommand extends Command
{
   protected $signature = 'aaix:backup:db:create
                            {--of-database= : The database connection name to back up (defaults to default connection)}
                            {--to-disk= : The disk to store the backup on (defaults to "local")}
                            {--compress : Force compression (smart detection)}
                            {--password= : The password for encrypted backup (implies zip)}';

   protected $description = 'Creates a new atomic database backup.';

   public function handle(): int
   {
      $database = $this->option('of-database') ?? config('database.default');
      $disk = $this->option('to-disk') ?? 'local';
      $password = $this->option('password');
      $compress = $this->option('compress') || $password;

      $this->info("Starting backup of database '{$database}' to disk '{$disk}'...");

      $backup = Backup::database($database)
         ->saveTo($disk);

      if ($compress) {
         $backup->compress();
      }

      if ($password) {
         $backup->encryptWithPassword($password);
      }

      $backup->run();

      $this->info('Backup job dispatched successfully.');

      return self::SUCCESS;
   }
}
