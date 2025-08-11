<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups;

use Aaix\LaravelEasyBackups\Contracts\Importer;
use Aaix\LaravelEasyBackups\Contracts\ProcessExecutor;
use Aaix\LaravelEasyBackups\Importers\MariaDbImporter;
use Aaix\LaravelEasyBackups\Importers\MySqlImporter;
use Aaix\LaravelEasyBackups\Importers\PostgreSqlImporter;
use Aaix\LaravelEasyBackups\Importers\SqliteImporter;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ImporterFactory
{
   public static function create(string $connectionName): Importer
   {
      $config = config("database.connections.{$connectionName}");
      $processExecutor = app(ProcessExecutor::class);

      return match ($config['driver']) {
         'mysql' => new MySqlImporter(
            host: $config['host'],
            port: (int) $config['port'],
            database: $config['database'],
            username: $config['username'],
            password: $config['password'],
         ),
         'mariadb' => new MariaDbImporter(
            host: $config['host'],
            port: (int) $config['port'],
            database: $config['database'],
            username: $config['username'],
            password: $config['password'],
         ),
         'pgsql' => new PostgreSqlImporter(
            host: $config['host'],
            port: (int) $config['port'],
            database: $config['database'],
            username: $config['username'],
            password: $config['password'],
            processExecutor: $processExecutor,
         ),
         'sqlite' => new SqliteImporter(
            database: $config['database'],
            processExecutor: $processExecutor,
         ),
         default => throw new InvalidArgumentException("Unsupported database driver for import: {$config['driver']}"),
      };
   }
}
