<?php

namespace Aaix\LaravelEasyBackups\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
   protected function getPackageProviders($app): array
   {
      return [
         \Aaix\LaravelEasyBackups\EasyBackupsServiceProvider::class,
         \Illuminate\Bus\BusServiceProvider::class,
      ];
   }

   protected function getEnvironmentSetUp($app): void
   {
      // MySQL Test Connection
      $app['config']->set('database.connections.mysql_test', [
         'driver' => 'mysql',
         'host' => env('DB_HOST_MYSQL', '127.0.0.1'),
         'port' => env('DB_PORT_MYSQL', '33061'),
         'database' => env('DB_DATABASE_MYSQL', 'test'),
         'username' => env('DB_USERNAME_MYSQL', 'root'),
         'password' => env('DB_PASSWORD_MYSQL', 'password'),
         'charset' => 'utf8mb4',
         'collation' => 'utf8mb4_unicode_ci',
         'prefix' => '',
      ]);

      // MariaDB Test Connection
      $app['config']->set('database.connections.mariadb_test', [
         'driver' => 'mariadb', // Changed from 'mysql' to 'mariadb' to trigger correct Dumper
         'host' => env('DB_HOST_MARIADB', '127.0.0.1'),
         'port' => env('DB_PORT_MARIADB', '33062'),
         'database' => env('DB_DATABASE_MARIADB', 'test'),
         'username' => env('DB_USERNAME_MARIADB', 'root'),
         'password' => env('DB_PASSWORD_MARIADB', 'password'),
         'charset' => 'utf8mb4',
         'collation' => 'utf8mb4_unicode_ci',
         'prefix' => '',
      ]);

      // PostgreSQL Test Connection
      $app['config']->set('database.connections.pgsql_test', [
         'driver' => 'pgsql',
         'host' => env('DB_HOST_PGSQL', '127.0.0.1'),
         'port' => env('DB_PORT_PGSQL', '33063'),
         'database' => env('DB_DATABASE_PGSQL', 'test'),
         'username' => env('DB_USERNAME_PGSQL', 'user'),
         'password' => env('DB_PASSWORD_PGSQL', 'password'),
         'charset' => 'utf8',
         'prefix' => '',
         'schema' => 'public',
         'sslmode' => 'prefer',
      ]);

      // SQLite Test Connection
      $app['config']->set('database.connections.sqlite_test', [
         'driver' => 'sqlite',
         'database' => ':memory:',
         'prefix' => '',
      ]);
   }

   public function setupTemporaryDirectory(): string
   {
      $tempDir = __DIR__ . '/temp';
      // This ensures the directory exists but does not clean it on every call.
      // The initial cleanup is handled by the `run-tests.sh` script.
      File::ensureDirectoryExists($tempDir);
      return $tempDir;
   }

   public function setupTemporarySqliteDatabase(string $dbName = 'database.sqlite'): string
   {
      $tempDir = $this->setupTemporaryDirectory();
      $dbPath = $tempDir . '/' . $dbName;
      file_put_contents($dbPath, '');
      config()->set('database.connections.sqlite_test.database', $dbPath);
      return $dbPath;
   }

   public function createTestTableAndDataForDump(string $connection): void
   {
      $schema = DB::connection($connection)->getSchemaBuilder();
      $schema->dropIfExists('test_table');
      $schema->create('test_table', function ($table) {
         $table->integer('id');
         $table->string('name');
      });
      DB::connection($connection)->table('test_table')->insert(['id' => 1, 'name' => 'test_data']);
   }
}
