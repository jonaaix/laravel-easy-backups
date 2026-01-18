<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Commands;

use Aaix\LaravelEasyBackups\Facades\Backup;
use Illuminate\Console\Command;

class CreateDatabaseBackupCommand extends Command
{
       protected $signature = 'easy-backups:db:create
                               {--of-database= : The database connection name to back up}
                               {--to-disk= : The disk to store the backup on (defaults to config)}
                               {--compress : Force compression}
                               {--password= : The password for encrypted backup}
                               {--name= : Optional suffix for the backup filename}
                               {--max-remote-backups= : Keep only the last N backups on remote}
                               {--max-remote-days= : Delete backups older than N days on remote}
                               {--local : Store backup only locally (overrides to-disk)}';
   
       protected $description = 'Creates a new atomic database backup with optional remote upload and retention.';
   
       public function handle(): int
       {
           $this->info('Initializing backup process...');
   
           $database = $this->option('of-database') ?? config('database.default');
           $isLocalOnly = $this->option('local');
   
           $defaultRemoteDisk = config('easy-backups.defaults.database.remote_disk', 's3-backup');
           $disk = $isLocalOnly ? 'local' : ($this->option('to-disk') ?? $defaultRemoteDisk);
   
           $name = $this->option('name');
           $password = $this->option('password');
           $compress = $this->option('compress') || $password;
           $maxRemoteBackups = $this->option('max-remote-backups') ? (int) $this->option('max-remote-backups') : null;
           $maxRemoteDays = $this->option('max-remote-days') ? (int) $this->option('max-remote-days') : null;
   
           $backup = Backup::database($database)->saveTo($disk);
   
           if ($isLocalOnly) {
               $this->info('Mode: Local only.');
               $backup->keepLocal();
           } else {
               $this->info("Mode: Remote upload to disk '{$disk}'.");
           }
   
           if ($compress) {
               $backup->compress();
           }
   
           if ($password) {
               $backup->encryptWithPassword($password);
           }
   
           if ($name) {
               $backup->setName($name);
           }
   
           if ($maxRemoteBackups && !$isLocalOnly) {
               $this->comment("Retention policy: Keeping last {$maxRemoteBackups} backups.");
               $backup->maxRemoteBackups($maxRemoteBackups);
           }
   
           if ($maxRemoteDays && !$isLocalOnly) {
               $this->comment("Retention policy: Keeping backups for {$maxRemoteDays} days.");
               $backup->maxRemoteDays($maxRemoteDays);
           }
   
           $result = $backup->run();
      if (is_array($result)) {
         $this->displaySummary($result);
      } else {
         $this->info('Backup job has been dispatched to the queue.');
      }

      return self::SUCCESS;
   }

   private function displaySummary(array $result): void
   {
      $this->newLine();
      $this->info('--------------------------------------');
      $this->info(' BACKUP COMPLETED SUCCESSFULLY');
      $this->info('--------------------------------------');

      $sizeMb = number_format($result['size'] / 1024 / 1024, 2);
      $this->line(" Size: <comment>{$sizeMb} MB</comment>");
      $this->line(" Disk: <comment>{$result['disk']}</comment>");

      $this->newLine();
      $this->info(' Created Artifacts:');
      foreach ($result['paths'] as $path) {
         $this->line(' - ' . $path);
      }
      $this->newLine();
   }
}
