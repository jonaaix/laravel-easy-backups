<?php


declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Commands;

use Aaix\LaravelEasyBackups\Facades\Backup;
use Illuminate\Console\Command;

class CreateDatabaseBackupCommand extends Command
{
   /**
    * The name and signature of the console command.
    *
    * @var string
    */
   protected $signature = 'aaix:backup:db:create
                            {--of-database= : The database connection name to back up (defaults to the default connection)}
                            {--to-disk= : The disk to store the backup on (defaults to "local")}
                            {--compress : Compress the backup into a zip archive}
                            {--password= : The password for an encrypted backup}';

   /**
    * The console command description.
    *
    * @var string
    */
   protected $description = 'Creates a new, simple database backup.';

   /**
    * Execute the console command.
    *
    * @return int
    */
   public function handle(): int
   {
      $database = $this->option('of-database') ?? config('database.default');
      $disk = $this->option('to-disk') ?? 'local';
      $password = $this->option('password');

      // Compression is implicitly required for encrypted backups.
      $compress = $this->option('compress') || $password;

      $this->info("Starting backup of database '{$database}' to disk '{$disk}'...");

      $backup = Backup::create()
         ->includeDatabases([$database])
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
