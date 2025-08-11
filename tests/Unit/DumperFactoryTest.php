<?php

use Aaix\LaravelEasyBackups\DumperFactory;
use Aaix\LaravelEasyBackups\Dumpers\MariaDbDumper;
use Aaix\LaravelEasyBackups\Dumpers\MySqlDumper;
use Aaix\LaravelEasyBackups\Dumpers\PostgreSqlDumper;
use Aaix\LaravelEasyBackups\Dumpers\SqliteDumper;

it('creates the correct dumper for each driver', function (string $connection, string $expectedClass) {
   if ($connection === 'sqlite_test') {
      $this->setupTemporarySqliteDatabase();
   }

   $dumper = DumperFactory::create($connection);
   expect($dumper)->toBeInstanceOf($expectedClass);
})->with([
   'mysql' => ['mysql_test', MySqlDumper::class],
   'mariadb' => ['mariadb_test', MariaDbDumper::class],
   'postgresql' => ['pgsql_test', PostgreSqlDumper::class],
   'sqlite' => ['sqlite_test', SqliteDumper::class],
]);

it('throws an exception for unsupported drivers', function () {
   config()->set('database.connections.unsupported', [
      'driver' => 'unsupported',
      'host' => 'localhost',
      'port' => 3306,
      'database' => 'test',
      'username' => 'root',
      'password' => '',
   ]);
   DumperFactory::create('unsupported');
})->throws(InvalidArgumentException::class);
