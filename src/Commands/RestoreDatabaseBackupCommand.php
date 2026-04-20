<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Commands;

use Aaix\LaravelEasyBackups\Facades\Restorer;
use Aaix\LaravelEasyBackups\Services\PathGenerator;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;

class RestoreDatabaseBackupCommand extends Command
{
   protected $signature = 'easy-backups:db:restore
                            {--from-disk= : The disk to restore from}
                            {--to-database= : The database connection to restore to}
                            {--source-env= : The source environment to pull from (e.g. production)}
                            {--latest : Restore the latest backup without prompting}
                            {--password= : The password for an encrypted backup}
                            {--local : Force use local disk}';

   protected $description = 'Restores a database backup from a local or remote disk with interactive selection.';

   public function handle(): int
   {
      $connection = $this->option('to-database') ?? config('database.default');

      // Resolve Disks
      $localDisk = config('easy-backups.defaults.database.local_disk', 'local');
      $defaultRemoteDisk = config('easy-backups.defaults.database.remote_disk', 'backup');

      // Determine source: explicit flag, or ask interactively
      if ($this->option('local')) {
         $useLocal = true;
      } elseif ($this->option('from-disk')) {
         $useLocal = false;
      } else {
         $source = select(
            label: 'Where would you like to restore from?',
            options: [
               'remote' => "Remote disk ({$defaultRemoteDisk})",
               'local' => "Local disk ({$localDisk})",
            ],
            default: 'remote',
         );
         $useLocal = $source === 'local';
      }

      $sourceDisk = $useLocal ? $localDisk : ($this->option('from-disk') ?? $defaultRemoteDisk);

      // Resolve Environment (Default to 'production' if looking remote, 'local' if looking local)
      $sourceEnv = $this->option('source-env') ?: ($useLocal ? config('app.env') : 'production');

      if ($useLocal) {
         $searchDir = config('easy-backups.defaults.database.local_path');
      } else {
         $searchDir = app(PathGenerator::class)->getDatabaseRemotePath(
            connectionName: $connection,
            customBase: null,
            enableEnvPathPrefix: true,
            targetEnv: $sourceEnv
         );
      }

      $this->info("Fetching available backups from disk: '{$sourceDisk}' (Dir: '{$searchDir}')...");

      try {
         $backups = Restorer::getRecentBackups($sourceDisk, $searchDir);
      } catch (\Exception $e) {
         $this->error("Error accessing disk '{$sourceDisk}': " . $e->getMessage());
         return self::FAILURE;
      }

      if ($backups->isEmpty()) {
         $this->warn("No backups found on disk '{$sourceDisk}' in directory '{$searchDir}'.");
         return self::FAILURE;
      }

      if ($this->option('latest')) {
         $selectedPath = $backups->first()['path'];
         $this->info("Selected latest backup: " . basename($selectedPath));
      } else {
         $selectedPath = select(
            label: 'Which backup would you like to restore?',
            options: $backups->pluck('label', 'path')->all(),
            scroll: 10,
            required: true,
         );
      }

      $filename = basename($selectedPath);

      if (!confirm(
         label: "DANGER: This will WIPE the database '{$connection}' and restore '{$filename}'. Continue?",
         default: false
      )) {
         return self::SUCCESS;
      }

      $restorer = Restorer::database()
         ->fromDisk($sourceDisk)
         ->fromPath($selectedPath)
         ->fromDir($searchDir)
         ->toDatabase($connection);

      if ($this->option('password')) {
         $restorer->withPassword($this->option('password'));
      }

      if (!$useLocal && $sourceDisk !== $localDisk) {
         if (confirm("Do you want to save a copy to '{$localDisk}' for faster future restores?", default: true)) {
            $restorer->saveCopyTo($localDisk);
         }
      }

      $this->info('Starting restore process...');

      $restorer->run();

      $this->info('Restore completed successfully.');

      return self::SUCCESS;
   }
}
