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
                           {--local : Store backup only locally (overrides to-disk)}
                           {--notify-mail-success= : Email address for success notifications}
                           {--notify-mail-failure= : Email address for failure notifications}';

   protected $description = 'Creates a new atomic database backup with optional remote upload and retention.';

   public function handle(): int
   {
      $this->info('Initializing backup process...');

      $database = $this->option('of-database') ?? config('database.default');
      $isLocalOnly = $this->option('local');

      $backup = Backup::database($database);

      if ($isLocalOnly) {
         $this->info('Mode: Local only.');
         $backup->onlyLocal();
      } else {
         $disk = $this->option('to-disk');
         // If user provided a disk explicitly, set it. Otherwise let Backup::run() handle the default.
         if ($disk) {
            $backup->saveTo($disk);
            $this->info("Mode: Remote upload to disk '{$disk}'.");
         } else {
            $defaultDisk = config('easy-backups.defaults.database.remote_disk');
            $this->info("Mode: Remote upload to default disk '{$defaultDisk}'.");
         }
      }

      $name = $this->option('name');
      $password = $this->option('password');
      $compress = $this->option('compress') || $password;
      $maxRemoteBackups = $this->option('max-remote-backups') ? (int)$this->option('max-remote-backups') : null;
      $maxRemoteDays = $this->option('max-remote-days') ? (int)$this->option('max-remote-days') : null;

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

      $notifyMailSuccess = $this->option('notify-mail-success');
      if ($notifyMailSuccess) {
         $this->comment("Success notifications will be sent to: {$notifyMailSuccess}");
         $backup->notifyOnSuccess('mail', $notifyMailSuccess);
      }

      $notifyMailFailure = $this->option('notify-mail-failure');
      if ($notifyMailFailure) {
         $this->comment("Failure notifications will be sent to: {$notifyMailFailure}");
         $backup->notifyOnFailure('mail', $notifyMailFailure);
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
