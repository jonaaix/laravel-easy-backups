<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups;

use Aaix\LaravelEasyBackups\Contracts\Wiper;
use Aaix\LaravelEasyBackups\Wipers\MariaDbWiper;
use Aaix\LaravelEasyBackups\Wipers\MySqlWiper;
use Aaix\LaravelEasyBackups\Wipers\PostgreSqlWiper;
use Aaix\LaravelEasyBackups\Wipers\SqliteWiper;
use InvalidArgumentException;

final class WiperFactory
{
   public static function create(string $connectionName): Wiper
   {
      $config = config("database.connections.{$connectionName}");

      return match ($config['driver']) {
         'mysql' => new MySqlWiper($connectionName),
         'mariadb' => new MariaDbWiper($connectionName),
         'pgsql' => new PostgreSqlWiper($connectionName),
         'sqlite' => new SqliteWiper($config['database']),
         default => throw new InvalidArgumentException("Unsupported database driver for wipe: {$config['driver']}"),
      };
   }
}
