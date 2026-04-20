<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Dumpers;

use Aaix\LaravelEasyBackups\Exceptions\DumpFailedException;

class SqliteDumper extends DbDumper
{
   public function getDumpCommand(string $path): string
   {
      if (!file_exists($this->config->database)) {
         throw new DumpFailedException("SQLite database file not found at path: {$this->config->database}");
      }

      $dbArg = escapeshellarg($this->config->database);
      $pathArg = escapeshellarg($path);

      // Fast path: no exclusions → native .dump
      if (empty($this->excludeTables) && empty($this->excludeTableData)) {
         return sprintf('sqlite3 %s .dump > %s', $dbArg, $pathArg);
      }

      // Build a script of sqlite3 commands selecting what to dump per table.
      // Query the DB for the actual table list, then emit .dump or .schema per table.
      $excludedSet = array_flip($this->excludeTables);
      $structureOnlySet = array_flip($this->excludeTableData);

      // Fetch table names from sqlite_master
      $listCmd = sprintf(
         'sqlite3 %s "SELECT name FROM sqlite_master WHERE type=\'table\' AND name NOT LIKE \'sqlite_%%\';"',
         $dbArg
      );
      $output = [];
      exec($listCmd, $output);

      $lines = [];
      foreach ($output as $table) {
         $table = trim($table);
         if ($table === '') {
            continue;
         }
         if (isset($excludedSet[$table])) {
            continue;
         }
         $tableArg = escapeshellarg($table);
         if (isset($structureOnlySet[$table])) {
            $lines[] = sprintf('sqlite3 %s ".schema %s" >> %s', $dbArg, $tableArg, $pathArg);
         } else {
            $lines[] = sprintf('sqlite3 %s ".dump %s" >> %s', $dbArg, $tableArg, $pathArg);
         }
      }

      // Truncate target file first, then append each table's output
      $truncate = sprintf(': > %s', $pathArg);
      return $truncate . ($lines ? ' && ' . implode(' && ', $lines) : '');
   }
}
