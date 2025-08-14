<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups;

use Aaix\LaravelEasyBackups\Contracts\Dumper;
use Aaix\LaravelEasyBackups\Contracts\ProcessExecutor;
use Aaix\LaravelEasyBackups\Dumpers\MariaDbDumper;
use Aaix\LaravelEasyBackups\Dumpers\MySqlDumper;
use Aaix\LaravelEasyBackups\Dumpers\PostgreSqlDumper;
use Aaix\LaravelEasyBackups\Dumpers\SqliteDumper;
use InvalidArgumentException;

final class DumperFactory
{
   public static function create(string $connectionName): Dumper
   {
      $config = config("database.connections.{$connectionName}");
      $executor = app(ProcessExecutor::class);

      $connectionConfig = new ConnectionConfig(
         driver: $config['driver'],
         host: $config['host'] ?? '',
         port: (int) ($config['port'] ?? 0),
         database: $config['database'],
         username: $config['username'] ?? '',
         password: $config['password'] ?? '',
      );

      return match ($connectionConfig->driver) {
         'mysql' => new MySqlDumper($connectionConfig, $executor),
         'mariadb' => new MariaDbDumper($connectionConfig, $executor),
         'pgsql' => new PostgreSqlDumper($connectionConfig, $executor),
         'sqlite' => new SqliteDumper($connectionConfig, $executor),
         default => throw new InvalidArgumentException("Unsupported database driver: {$connectionConfig->driver}"),
      };
   }
}
