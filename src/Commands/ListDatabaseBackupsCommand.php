<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Commands;

use Aaix\LaravelEasyBackups\Services\BackupInventoryService;
use Aaix\LaravelEasyBackups\Services\PathGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ListDatabaseBackupsCommand extends Command
{
   protected $signature = 'easy-backups:db:list
                           {--disk= : The disk to inspect (defaults to configured remote disk)}
                           {--path= : The path within the disk (defaults to the configured remote path)}
                           {--local : List from the local database disk instead of remote}
                           {--limit=20 : Maximum number of backups to display}
                           {--recursive : Include backups in subdirectories (useful for listing across driver subfolders)}';

   protected $description = 'List existing database backups on a disk with size, age, and format.';

   public function handle(BackupInventoryService $inventory, PathGenerator $paths): int
   {
      $useLocal = (bool) $this->option('local');
      $disk = $this->option('disk') ?? ($useLocal
         ? config('easy-backups.defaults.database.local_disk', 'local')
         : config('easy-backups.defaults.database.remote_disk'));

      if (!$disk) {
         $this->error('No disk specified and no default disk configured.');
         return self::FAILURE;
      }

      if (config("filesystems.disks.{$disk}") === null) {
         $this->error("Disk '{$disk}' is not defined in filesystems.php.");
         return self::FAILURE;
      }

      $path = $this->option('path') ?? ($useLocal
         ? $paths->getDatabaseLocalPath()
         : $this->defaultRemoteListPath());

      $limit = max(1, (int) $this->option('limit'));
      // Remote default path sits one level above driver subfolders, so recurse by default there.
      $recursive = (bool) $this->option('recursive') || (!$useLocal && !$this->option('path'));

      $this->info("Listing backups on disk '{$disk}' at path '{$path}'" . ($recursive ? ' (recursive)' : '') . ':');
      $this->newLine();

      $backups = $inventory->list($disk, $path, $recursive);

      if ($backups->isEmpty()) {
         $this->comment('No backups found.');
         return self::SUCCESS;
      }

      $rows = $backups->take($limit)->map(fn(array $entry) => [
         'filename' => $entry['filename'],
         'size' => $this->formatSize($entry['size']),
         'age' => Carbon::createFromTimestamp($entry['last_modified'])->diffForHumans(),
         'created' => Carbon::createFromTimestamp($entry['last_modified'])->format('Y-m-d H:i:s'),
         'format' => $this->detectFormat($entry['filename']),
      ])->all();

      $this->table(['Filename', 'Size', 'Age', 'Created', 'Format'], $rows);

      $total = $backups->count();
      $shown = min($total, $limit);
      $totalSize = $this->formatSize($backups->sum('size'));

      $this->newLine();
      $this->line(" Showing <comment>{$shown}</comment> of <comment>{$total}</comment> backup(s). Combined size: <comment>{$totalSize}</comment>.");

      return self::SUCCESS;
   }

   /**
    * Build the default remote path for listing. Matches the layout used by PathGenerator
    * when producing upload targets: optionally prefixed by the app env, then the remote
    * backup base folder. Driver subfolders below that are included automatically because
    * the inventory lists recursively-flat via Storage::files() on a parent prefix.
    */
   private function defaultRemoteListPath(): string
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

   private function detectFormat(string $filename): string
   {
      return match (true) {
         str_ends_with($filename, '.tar.zst') || str_ends_with($filename, '.zst') => 'tar.zst',
         str_ends_with($filename, '.tar.gz') || str_ends_with($filename, '.gz') => 'tar.gz',
         str_ends_with($filename, '.zip') => 'zip (possibly encrypted)',
         str_ends_with($filename, '.tar') => 'tar',
         str_ends_with($filename, '.sql') => 'sql (raw)',
         default => 'unknown',
      };
   }
}
