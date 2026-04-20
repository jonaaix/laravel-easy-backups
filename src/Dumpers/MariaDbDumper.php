<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Dumpers;

class MariaDbDumper extends MySqlDumper
{
   protected function getBinaryName(): string
   {
      $binary = 'mariadb-dump';

      if (!config('easy-backups.defaults.database.mariadb.use_parallel', true)) {
         return $binary;
      }

      if (!$this->supportsParallel()) {
         return $binary;
      }

      $threads = $this->determineOptimalThreads();
      if ($threads > 1) {
         $binary .= " --parallel={$threads}";
      }

      return $binary;
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
