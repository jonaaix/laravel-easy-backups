<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Importers;

use Aaix\LaravelEasyBackups\Contracts\Importer;
use Aaix\LaravelEasyBackups\Exceptions\ImportFailedException;
use Illuminate\Support\Facades\Process;

final class MariaDbImporter implements Importer
{
   public function __construct(
      private readonly string $host,
      private readonly int $port,
      private readonly string $database,
      private readonly string $username,
      private readonly string $password,
   ) {
   }

   public function importFromFile(string $path): void
   {
      $command = [
         'mariadb',
         "-h{$this->host}",
         "-P{$this->port}",
         "-u{$this->username}",
      ];

      if ($this->password) {
         $command[] = "-p{$this->password}";
      }

      $command[] = $this->database;

      $process = Process::input(fopen($path, 'r'))->run($command);

      if ($process->failed()) {
         throw new ImportFailedException('Failed to import mariadb dump: ' . $process->errorOutput());
      }
   }
}
