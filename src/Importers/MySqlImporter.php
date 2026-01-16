<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Importers;

use Aaix\LaravelEasyBackups\Contracts\Importer;
use Aaix\LaravelEasyBackups\Contracts\ProcessExecutor;
use Aaix\LaravelEasyBackups\Exceptions\ImportFailedException;

final class MySqlImporter implements Importer
{
   public function __construct(
      private readonly string $host,
      private readonly int $port,
      private readonly string $database,
      private readonly string $username,
      private readonly string $password,
      private readonly ProcessExecutor $processExecutor,
   ) {
   }

   public function importFromFile(string $path): void
   {
      $command = sprintf(
         'mysql -h%s -P%d -u%s %s %s < %s',
         escapeshellarg($this->host),
         $this->port,
         escapeshellarg($this->username),
         $this->password ? '-p' . escapeshellarg($this->password) : '',
         escapeshellarg($this->database),
         escapeshellarg($path)
      );

      try {
         $this->processExecutor->execute(
            command: $command,
            cwd: dirname($path),
            timeout: null
         );
      } catch (\Exception $e) {
         throw new ImportFailedException('Failed to import mysql dump: ' . $e->getMessage());
      }
   }
}
