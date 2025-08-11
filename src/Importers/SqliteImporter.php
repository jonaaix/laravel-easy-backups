<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Importers;

use Aaix\LaravelEasyBackups\Contracts\Importer;
use Aaix\LaravelEasyBackups\Contracts\ProcessExecutor;
use Aaix\LaravelEasyBackups\Exceptions\ImportFailedException;
use Illuminate\Support\Facades\File;

final class SqliteImporter implements Importer
{
   public function __construct(
      private readonly string $database,
      private readonly ProcessExecutor $processExecutor
   ) {
   }

   public function importFromFile(string $path): void
   {
      if ($this->database !== ':memory:') {
         File::ensureDirectoryExists(dirname($this->database));
      }

      $command = sprintf(
         'sqlite3 %s < %s',
         escapeshellarg($this->database),
         escapeshellarg($path)
      );

      try {
         $this->processExecutor->execute($command, dirname($path));
      } catch (\Exception $e) {
         throw new ImportFailedException('Failed to import sqlite dump: ' . $e->getMessage());
      }
   }
}
