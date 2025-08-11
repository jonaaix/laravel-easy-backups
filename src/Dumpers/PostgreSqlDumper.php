<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Dumpers;

use Illuminate\Support\Facades\File;

class PostgreSqlDumper extends DbDumper
{
   public function getDumpCommand(string $path): string
   {
      return sprintf(
         'pg_dump --host=%s --port=%s --username=%s --dbname=%s --clean --if-exists > %s',
         escapeshellarg($this->config->host),
         escapeshellarg((string)$this->config->port),
         escapeshellarg($this->config->username),
         escapeshellarg($this->config->database),
         escapeshellarg($path)
      );
   }

   public function dumpToFile(string $path): void
   {
      File::ensureDirectoryExists(dirname($path));
      $command = $this->getDumpCommand($path);
      $this->executor->execute($command, dirname($path), ['PGPASSWORD' => $this->config->password]);
   }
}
