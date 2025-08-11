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

      return sprintf(
         'sqlite3 %s .dump > %s',
         escapeshellarg($this->config->database),
         escapeshellarg($path)
      );
   }
}
