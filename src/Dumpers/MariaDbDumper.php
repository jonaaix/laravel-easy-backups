<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Dumpers;

class MariaDbDumper extends MySqlDumper
{
   public function getDumpCommand(string $path): string
   {
      // MariaDB dump command is very similar to MySQL dump
      $command = parent::getDumpCommand($path);

      return str_replace('mysqldump', 'mariadb-dump', $command);
   }
}
