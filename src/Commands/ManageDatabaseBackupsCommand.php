<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Commands;

use Aaix\LaravelEasyBackups\Services\BackupInventoryService;
use Aaix\LaravelEasyBackups\Services\PathGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;

class ManageDatabaseBackupsCommand extends Command
{
   protected $signature = 'easy-backups:db:manage';

   protected $description = 'Interactively manage (inspect, delete) local and remote database backups.';

   public function handle(BackupInventoryService $inventory, PathGenerator $paths): int
   {
      $localDisk = config('easy-backups.defaults.database.local_disk', 'local');
      $remoteDisk = config('easy-backups.defaults.database.remote_disk', 'backup');
      $isProduction = app()->environment('production');

      // Step 1: Choose scope — non-production environments can only manage local backups.
      if ($isProduction) {
         $scope = select(
            label: 'Which backups would you like to manage?',
            options: [
               'local' => "Local backups ({$localDisk})",
               'remote' => "Remote backups ({$remoteDisk})",
            ],
         );
      } else {
         $this->comment("Environment: " . app()->environment() . " — only local backups can be managed.");
         $scope = 'local';
      }

      if ($scope === 'local') {
         $disk = $localDisk;
         $path = $paths->getDatabaseLocalPath();
         $recursive = false;
      } else {
         $disk = $remoteDisk;
         $path = $this->defaultRemotePath();
         $recursive = true;
      }

      // Step 2: List backups
      $backups = $inventory->list($disk, $path, $recursive);

      if ($backups->isEmpty()) {
         $this->info('No backups found.');
         return self::SUCCESS;
      }

      $this->renderTable($backups);

      // Step 3: Action
      $action = select(
         label: 'What would you like to do?',
         options: [
            'delete' => 'Delete selected backups',
            'quit' => 'Done — exit',
         ],
      );

      if ($action === 'quit') {
         return self::SUCCESS;
      }

      // Step 4: Select backups to delete
      $options = $backups->mapWithKeys(fn(array $entry) => [
         $entry['path'] => sprintf(
            '%s  (%s, %s)',
            $entry['filename'],
            $this->formatSize($entry['size']),
            Carbon::createFromTimestamp($entry['last_modified'])->diffForHumans()
         ),
      ])->all();

      $selected = multiselect(
         label: 'Select backups to delete',
         options: $options,
         required: true,
         scroll: 15,
      );

      if (empty($selected)) {
         $this->info('Nothing selected.');
         return self::SUCCESS;
      }

      // Step 5: Confirm
      $count = count($selected);
      $totalSize = $backups->filter(fn(array $e) => in_array($e['path'], $selected))->sum('size');

      $this->newLine();
      $this->warn("You are about to delete {$count} backup(s) ({$this->formatSize($totalSize)}) from disk '{$disk}':");
      foreach ($selected as $path) {
         $this->line('  - ' . basename($path));
      }
      $this->newLine();

      if (!confirm('Are you sure? This cannot be undone.', default: false)) {
         $this->info('Cancelled.');
         return self::SUCCESS;
      }

      // Step 6: Delete
      $this->deleteBackups($disk, $selected);

      $this->info("{$count} backup(s) deleted.");

      return self::SUCCESS;
   }

   private function deleteBackups(string $disk, array $paths): void
   {
      $driver = config("filesystems.disks.{$disk}.driver");

      if ($driver === 'local') {
         File::delete($paths);
      } else {
         Storage::disk($disk)->delete($paths);
      }
   }

   private function renderTable($backups): void
   {
      $rows = $backups->map(fn(array $entry) => [
         'filename' => $entry['filename'],
         'size' => $this->formatSize($entry['size']),
         'age' => Carbon::createFromTimestamp($entry['last_modified'])->diffForHumans(),
         'created' => Carbon::createFromTimestamp($entry['last_modified'])->format('Y-m-d H:i:s'),
      ])->all();

      $this->table(['Filename', 'Size', 'Age', 'Created'], $rows);

      $totalSize = $this->formatSize($backups->sum('size'));
      $this->line(" <comment>{$backups->count()}</comment> backup(s), combined size: <comment>{$totalSize}</comment>.");
      $this->newLine();
   }

   private function defaultRemotePath(): string
   {
      $parts = [];
      if (config('easy-backups.defaults.strategy.prefix_env', true)) {
         $parts[] = (string) config('app.env');
      }
      $parts[] = trim((string) config('easy-backups.defaults.database.remote_path', 'db-backups'), '/');
      return implode('/', array_filter($parts));
   }

   private function formatSize(int $bytes): string
   {
      if ($bytes < 1024) {
         return "{$bytes} B";
      }
      $units = ['KB', 'MB', 'GB', 'TB'];
      $value = $bytes / 1024;
      $unit = 'KB';
      foreach ($units as $u) {
         $unit = $u;
         if ($value < 1024) {
            break;
         }
         $value /= 1024;
      }
      return number_format($value, 2) . " {$unit}";
   }
}
