<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Dumpers;

class MariaDbDumper extends MySqlDumper
{
   public function getDumpCommand(string $path): string
   {
      $command = parent::getDumpCommand($path);
      $command = str_replace('mysqldump', 'mariadb-dump', $command);

      if (!config('easy-backups.defaults.database.mariadb.use_parallel', true)) {
         return $command;
      }

      if (!$this->supportsParallel()) {
         return $command;
      }

      $threads = $this->determineOptimalThreads();
      if ($threads > 1) {
         $command = str_replace('mariadb-dump', "mariadb-dump --parallel={$threads}", $command);
      }

      return $command;
   }

   protected function determineOptimalThreads(): int
   {
      $cores = 1;

      if (is_readable('/proc/cpuinfo')) {
         $cpuinfo = file_get_contents('/proc/cpuinfo');
         $cores = substr_count($cpuinfo, 'processor');
      } elseif (strtoupper(substr(PHP_OS, 0, 3)) === 'DAR') {
         $cores = (int) shell_exec('sysctl -n hw.ncpu');
      }

      $cores = max(1, $cores);
      $recommended = (int) ceil($cores * 0.75);

      return min(6, $recommended);
   }

   protected function supportsParallel(): bool
   {
      try {
         $output = [];
         exec('mariadb-dump --version', $output);
         $versionString = $output[0] ?? '';

         if (preg_match('/(\d+\.\d+)\.\d+-MariaDB/', $versionString, $matches)) {
            return version_compare($matches[1], '10.5', '>=');
         }
      } catch (\Exception $e) {
         return false;
      }

      return false;
   }
}
