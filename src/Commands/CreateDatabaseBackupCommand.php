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
                           {--max-local-backups= : Keep only the last N backups locally}
                           {--max-local-days= : Delete local backups older than N days}
                           {--local : Store backup only locally (overrides to-disk)}
                           {--keep-local : Keep the local copy after a remote upload (no-op with --local)}
                           {--notify-mail-success= : Email address for success notifications}
                           {--notify-mail-failure= : Email address for failure notifications}
                           {--exclude-tables= : Comma-separated list of tables to exclude entirely (no structure, no data)}
                           {--exclude-table-data= : Comma-separated list of tables to export structure only (no row data)}
                           {--dry-run : Print what would be done without executing dumps, compression, or uploads}';

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
      $maxLocalBackups = $this->option('max-local-backups') ? (int)$this->option('max-local-backups') : null;
      $maxLocalDays = $this->option('max-local-days') ? (int)$this->option('max-local-days') : null;

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

      if ($maxLocalBackups) {
         $this->comment("Retention policy: Keeping last {$maxLocalBackups} local backups.");
         $backup->maxLocalBackups($maxLocalBackups);
      }

      if ($maxLocalDays) {
         $this->comment("Retention policy: Keeping local backups for {$maxLocalDays} days.");
         $backup->maxLocalDays($maxLocalDays);
      }

      if ($this->option('keep-local') && !$isLocalOnly) {
         $this->comment('Local copy will be kept after remote upload.');
         $backup->keepLocal();
      }

      if ($this->option('dry-run')) {
         $this->comment('Dry-run mode enabled.');
         $backup->dryRun();
      }

      $excludeTables = $this->parseTableList($this->option('exclude-tables'));
      if (!empty($excludeTables)) {
         $this->comment('Excluding tables entirely: ' . implode(', ', $excludeTables));
         $backup->excludeTables($excludeTables);
      }

      $excludeTableData = $this->parseTableList($this->option('exclude-table-data'));
      if (!empty($excludeTableData)) {
         $this->comment('Excluding data (structure only) for: ' . implode(', ', $excludeTableData));
         $backup->excludeTableData($excludeTableData);
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
         if ($result['dry_run'] ?? false) {
            $this->newLine();
            $this->info('Dry-run complete. No changes were made.');
         } else {
            $this->displaySummary($result);
         }
      } else {
         $this->info('Backup job has been dispatched to the queue.');
      }

      return self::SUCCESS;
   }

   private function parseTableList(?string $raw): array
   {
      if (!$raw) {
         return [];
      }
      return array_values(array_filter(array_map('trim', explode(',', $raw))));
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
