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

      $command = sprintf(
         'mysqldump %s %s %s > %s',
         implode(' ', $credentials),
         implode(' ', $options),
         escapeshellarg($this->config->database),
         escapeshellarg($path)
      );

      return $command;
   }
}
