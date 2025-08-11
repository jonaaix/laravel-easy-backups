<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Importers;

use Aaix\LaravelEasyBackups\Contracts\Importer;
use Aaix\LaravelEasyBackups\Contracts\ProcessExecutor;
use Aaix\LaravelEasyBackups\Exceptions\ImportFailedException;

final class PostgreSqlImporter implements Importer
{
   public function __construct(
      private readonly string $host,
      private readonly int $port,
      private readonly string $database,
      private readonly string $username,
      private readonly string $password,
      private readonly ProcessExecutor $processExecutor
   ) {
   }

   public function importFromFile(string $path): void
   {
      $command = sprintf(
         'psql -h %s -p %d -U %s -d %s -f %s',
         escapeshellarg($this->host),
         $this->port,
         escapeshellarg($this->username),
         escapeshellarg($this->database),
         escapeshellarg($path)
      );

      $env = [
         'PGPASSWORD' => $this->password,
      ];

      try {
         $this->processExecutor->execute($command, dirname($path), $env);
      } catch (\Exception $e) {
         throw new ImportFailedException('Failed to import postgresql dump: ' . $e->getMessage());
      }
   }
}
