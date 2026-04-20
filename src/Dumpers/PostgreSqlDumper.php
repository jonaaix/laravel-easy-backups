<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Dumpers;

use Illuminate\Support\Facades\File;

class PostgreSqlDumper extends DbDumper
{
   public function getDumpCommand(string $path): string
   {
      $options = [];

      foreach ($this->excludeTables as $table) {
         $options[] = '--exclude-table=' . escapeshellarg($table);
      }

      foreach ($this->excludeTableData as $table) {
         $options[] = '--exclude-table-data=' . escapeshellarg($table);
      }

      return sprintf(
         'pg_dump --host=%s --port=%s --username=%s --dbname=%s --clean --if-exists%s > %s',
         escapeshellarg($this->config->host),
         escapeshellarg((string)$this->config->port),
         escapeshellarg($this->config->username),
         escapeshellarg($this->config->database),
         $options ? ' ' . implode(' ', $options) : '',
         escapeshellarg($path)
      );
   }

   public function dumpToFile(string $path): void
   {
      File::ensureDirectoryExists(dirname($path));
      $command = $this->getDumpCommand($path);
      $this->executor->execute(
         command: $command,
         cwd: dirname($path),
         env: ['PGPASSWORD' => $this->config->password],
         timeout: null
      );
   }
}
