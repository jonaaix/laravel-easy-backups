<?php

namespace Aaix\LaravelEasyBackups\Tests\Unit;

use Aaix\LaravelEasyBackups\ConnectionConfig;
use Aaix\LaravelEasyBackups\Contracts\ProcessExecutor;
use Aaix\LaravelEasyBackups\Dumpers\MariaDbDumper;
use Aaix\LaravelEasyBackups\Dumpers\MySqlDumper;
use Aaix\LaravelEasyBackups\Dumpers\PostgreSqlDumper;
use Aaix\LaravelEasyBackups\Tests\TestCase;
use Mockery;

class TableExclusionTest extends TestCase
{
   protected function tearDown(): void
   {
      Mockery::close();
      parent::tearDown();
   }

   private function mysqlConfig(): ConnectionConfig
   {
      return new ConnectionConfig(
         driver: 'mysql',
         host: 'localhost',
         port: 3306,
         database: 'test_db',
         username: 'user',
         password: 'pass'
      );
   }

   private function pgsqlConfig(): ConnectionConfig
   {
      return new ConnectionConfig(
         driver: 'pgsql',
         host: 'localhost',
         port: 5432,
         database: 'test_db',
         username: 'user',
         password: 'pass'
      );
   }

   public function test_mysql_adds_ignore_table_for_excluded_tables(): void
   {
      $executor = Mockery::mock(ProcessExecutor::class);
      $dumper = new MySqlDumper($this->mysqlConfig(), $executor);
      $dumper->excludeTables(['sessions', 'password_resets']);

      $command = $dumper->getDumpCommand('/tmp/dump.sql');

      $this->assertStringContainsString("--ignore-table='test_db.sessions'", $command);
      $this->assertStringContainsString("--ignore-table='test_db.password_resets'", $command);
      $this->assertStringNotContainsString('--no-data', $command);
   }

   public function test_mysql_appends_structure_only_dump_for_exclude_table_data(): void
   {
      $executor = Mockery::mock(ProcessExecutor::class);
      $dumper = new MySqlDumper($this->mysqlConfig(), $executor);
      $dumper->excludeTableData(['users', 'audit_logs']);

      $command = $dumper->getDumpCommand('/tmp/dump.sql');

      // Structure-only tables should be ignored in the main dump...
      $this->assertStringContainsString("--ignore-table='test_db.users'", $command);
      $this->assertStringContainsString("--ignore-table='test_db.audit_logs'", $command);
      // ...and appended via a second --no-data dump chained with &&
      $this->assertStringContainsString('&&', $command);
      $this->assertStringContainsString('--no-data', $command);
      $this->assertStringContainsString("'users'", $command);
      $this->assertStringContainsString("'audit_logs'", $command);
      $this->assertStringContainsString(">> '/tmp/dump.sql'", $command);
   }

   public function test_mysql_combines_both_exclusion_types(): void
   {
      $executor = Mockery::mock(ProcessExecutor::class);
      $dumper = new MySqlDumper($this->mysqlConfig(), $executor);
      $dumper->excludeTables(['sessions']);
      $dumper->excludeTableData(['users']);

      $command = $dumper->getDumpCommand('/tmp/dump.sql');

      $this->assertStringContainsString("--ignore-table='test_db.sessions'", $command);
      $this->assertStringContainsString("--ignore-table='test_db.users'", $command);
      $this->assertStringContainsString('--no-data', $command);
      // sessions must not appear in the --no-data follow-up
      $noDataSegment = substr($command, strpos($command, '--no-data'));
      $this->assertStringNotContainsString("'sessions'", $noDataSegment);
   }

   public function test_mariadb_inherits_exclusion_with_correct_binary(): void
   {
      config(['easy-backups.defaults.database.mariadb.use_parallel' => false]);

      $executor = Mockery::mock(ProcessExecutor::class);
      $config = new ConnectionConfig(
         driver: 'mariadb',
         host: 'localhost',
         port: 3306,
         database: 'test_db',
         username: 'user',
         password: 'pass'
      );
      $dumper = new MariaDbDumper($config, $executor);
      $dumper->excludeTables(['sessions']);
      $dumper->excludeTableData(['users']);

      $command = $dumper->getDumpCommand('/tmp/dump.sql');

      $this->assertStringContainsString('mariadb-dump', $command);
      $this->assertStringNotContainsString('mysqldump', $command);
      $this->assertStringContainsString("--ignore-table='test_db.sessions'", $command);
      $this->assertStringContainsString('--no-data', $command);
   }

   public function test_postgres_uses_native_exclude_flags(): void
   {
      $executor = Mockery::mock(ProcessExecutor::class);
      $dumper = new PostgreSqlDumper($this->pgsqlConfig(), $executor);
      $dumper->excludeTables(['sessions']);
      $dumper->excludeTableData(['users']);

      $command = $dumper->getDumpCommand('/tmp/dump.sql');

      $this->assertStringContainsString("--exclude-table='sessions'", $command);
      $this->assertStringContainsString("--exclude-table-data='users'", $command);
      // No chained commands needed for pg_dump
      $this->assertStringNotContainsString('&&', $command);
   }

   public function test_dumpers_without_exclusions_produce_unchanged_command(): void
   {
      $executor = Mockery::mock(ProcessExecutor::class);
      $dumper = new MySqlDumper($this->mysqlConfig(), $executor);

      $command = $dumper->getDumpCommand('/tmp/dump.sql');

      $this->assertStringNotContainsString('--ignore-table', $command);
      $this->assertStringNotContainsString('&&', $command);
   }
}
