<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Dumpers;

class MySqlDumper extends DbDumper
{
   public function getDumpCommand(string $path): string
   {
      $credentials = [
         '--host=' . escapeshellarg($this->config->host),
         '--port=' . escapeshellarg((string)$this->config->port),
         '--user=' . escapeshellarg($this->config->username),
      ];

      if ($this->config->password) {
         $credentials[] = '--password=' . escapeshellarg($this->config->password);
      }

      $options = [
         '--skip-comments',
         '--skip-lock-tables',
         '--single-transaction',
         '--no-tablespaces',
      ];

      // Tables to fully skip in the main dump (both excluded and structure-only tables)
      $ignoreTables = array_unique(array_merge($this->excludeTables, $this->excludeTableData));
      foreach ($ignoreTables as $table) {
         $options[] = '--ignore-table=' . escapeshellarg($this->config->database . '.' . $table);
      }

      $binary = $this->getBinaryName();

      $command = sprintf(
         '%s %s %s %s > %s',
         $binary,
         implode(' ', $credentials),
         implode(' ', $options),
         escapeshellarg($this->config->database),
         escapeshellarg($path)
      );

      // For structure-only tables: append a second dump with --no-data for those tables
      if (!empty($this->excludeTableData)) {
         $structureTables = array_map('escapeshellarg', $this->excludeTableData);
         $structureCommand = sprintf(
            '%s %s --skip-comments --no-data --no-tablespaces %s %s >> %s',
            $binary,
            implode(' ', $credentials),
            escapeshellarg($this->config->database),
            implode(' ', $structureTables),
            escapeshellarg($path)
         );
         $command .= ' && ' . $structureCommand;
      }

      return $command;
   }

   protected function getBinaryName(): string
   {
      return 'mysqldump';
   }
}
