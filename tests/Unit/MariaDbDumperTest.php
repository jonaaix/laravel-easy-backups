<?php

namespace Aaix\LaravelEasyBackups\Tests\Unit;

use Aaix\LaravelEasyBackups\ConnectionConfig;
use Aaix\LaravelEasyBackups\Contracts\ProcessExecutor;
use Aaix\LaravelEasyBackups\Dumpers\MariaDbDumper;
use Aaix\LaravelEasyBackups\Tests\TestCase;
use Mockery;

class MariaDbDumperTest extends TestCase
{
   protected function tearDown(): void
   {
      Mockery::close();
      parent::tearDown();
   }

   public function test_it_adds_parallel_flag_when_supported_and_enabled()
   {
      config(['easy-backups.defaults.database.mariadb.use_parallel' => true]);

      $config = new ConnectionConfig(
         driver: 'mariadb',
         host: 'localhost',
         port: 3306,
         database: 'test_db',
         username: 'user',
         password: 'password'
      );

      $executor = Mockery::mock(ProcessExecutor::class);
      
      // We mock the dumper and only partial mock supportsParallel and determineOptimalThreads
      // to avoid environment dependency in unit tests.
      $dumper = Mockery::mock(MariaDbDumper::class, [$config, $executor])->makePartial();
      $dumper->shouldAllowMockingProtectedMethods();
      $dumper->shouldReceive('supportsParallel')->andReturn(true);
      $dumper->shouldReceive('determineOptimalThreads')->andReturn(4);

      $command = $dumper->getDumpCommand('/path/to/dump.sql');

      $this->assertStringContainsString('--parallel=4', $command);
      $this->assertStringContainsString('mariadb-dump', $command);
   }

   public function test_it_does_not_add_parallel_flag_when_disabled_in_config()
   {
      config(['easy-backups.defaults.database.mariadb.use_parallel' => false]);

      $config = new ConnectionConfig(
         driver: 'mariadb',
         host: 'localhost',
         port: 3306,
         database: 'test_db',
         username: 'user',
         password: 'password'
      );

      $executor = Mockery::mock(ProcessExecutor::class);
      $dumper = Mockery::mock(MariaDbDumper::class, [$config, $executor])->makePartial();
      $dumper->shouldAllowMockingProtectedMethods();
      $dumper->shouldReceive('supportsParallel')->andReturn(true);
      $dumper->shouldReceive('determineOptimalThreads')->andReturn(4);

      $command = $dumper->getDumpCommand('/path/to/dump.sql');

      $this->assertStringNotContainsString('--parallel', $command);
   }

   public function test_it_does_not_add_parallel_flag_when_not_supported_by_version()
   {
      config(['easy-backups.defaults.database.mariadb.use_parallel' => true]);

      $config = new ConnectionConfig(
         driver: 'mariadb',
         host: 'localhost',
         port: 3306,
         database: 'test_db',
         username: 'user',
         password: 'password'
      );

      $executor = Mockery::mock(ProcessExecutor::class);
      $dumper = Mockery::mock(MariaDbDumper::class, [$config, $executor])->makePartial();
      $dumper->shouldAllowMockingProtectedMethods();
      $dumper->shouldReceive('supportsParallel')->andReturn(false);

      $command = $dumper->getDumpCommand('/path/to/dump.sql');

      $this->assertStringNotContainsString('--parallel', $command);
   }
}
