<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups;

use Aaix\LaravelEasyBackups\Contracts\Dumper;
use Aaix\LaravelEasyBackups\Contracts\ProcessExecutor;
use Aaix\LaravelEasyBackups\Dumpers\MariaDbDumper;
use Aaix\LaravelEasyBackups\Dumpers\MySqlDumper;
use Aaix\LaravelEasyBackups\Dumpers\PostgreSqlDumper;
use Aaix\LaravelEasyBackups\Dumpers\SqliteDumper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class DumperFactory
{
   private const DOCKER_INTERNAL_PORTS = [
      'mysql' => 3306,
      'pgsql' => 5432,
   ];

   public static function create(string $connectionName): Dumper
   {
      $configData = config("database.connections.{$connectionName}");

      if (!$configData) {
         throw new InvalidArgumentException("Database connection `{$connectionName}` not configured.");
      }

      // Provide default values for drivers that may not have them (like sqlite).
      $configData = array_merge([
         'host' => 'localhost',
         'port' => 0,
         'username' => '',
         'password' => '',
      ], $configData);


      if (app()->runningUnitTests() && $connectionName !== 'sqlite_test') {
         $configData['host'] = $connectionName; // Docker service name is the hostname
         $configData['port'] = self::DOCKER_INTERNAL_PORTS[$configData['driver']] ?? $configData['port'];
      }

      $connectionConfig = new ConnectionConfig(
         driver: $configData['driver'],
         host: $configData['host'],
         port: (int)$configData['port'],
         database: $configData['database'],
         username: $configData['username'],
         password: $configData['password'],
      );

      $executor = app(ProcessExecutor::class);

      return match ($connectionConfig->driver) {
         'mysql' => self::createMySqlDumper($connectionName, $connectionConfig, $executor),
         'pgsql' => new PostgreSqlDumper($connectionConfig, $executor),
         'sqlite' => new SqliteDumper($connectionConfig, $executor),
         default => throw new InvalidArgumentException("Unsupported database driver: {$connectionConfig->driver}"),
      };
   }

   private static function createMySqlDumper(string $connectionName, ConnectionConfig $config, ProcessExecutor $executor): Dumper
   {
      $version = self::getDatabaseVersion($connectionName);
      $dumperClass = Str::contains(strtolower($version), 'mariadb') ? MariaDbDumper::class : MySqlDumper::class;

      return new $dumperClass($config, $executor);
   }

   private static function getDatabaseVersion(string $connectionName): string
   {
      try {
         if (app()->runningUnitTests()) {
            if ($connectionName === 'mariadb_test') return 'mariadb';
            if ($connectionName === 'mysql_test') return 'mysql';
         }
         return DB::connection($connectionName)->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION);
      } catch (\Throwable) {
         return '';
      }
   }
}
