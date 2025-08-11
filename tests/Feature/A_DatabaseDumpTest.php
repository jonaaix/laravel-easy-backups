<?php

use Aaix\LaravelEasyBackups\Contracts\ProcessExecutor;
use Aaix\LaravelEasyBackups\DumperFactory;
use Aaix\LaravelEasyBackups\Tests\Support\DockerProcessExecutor;
use Illuminate\Support\Facades\File;

it('creates a valid and non-empty database dump file for each driver', function (string $connection) {
   $tempDir = $this->setupTemporaryDirectory();

   if ($connection === 'sqlite_test') {
      $this->setupTemporarySqliteDatabase();
   } else {
      $this->app->bind(ProcessExecutor::class, fn () => new DockerProcessExecutor($connection));
   }

   $this->createTestTableAndDataForDump($connection);

   $dumper = DumperFactory::create($connection);
   $dumpPath = $tempDir . DIRECTORY_SEPARATOR . "db-dump_{$connection}.sql";

   $dumper->dumpToFile($dumpPath);

   expect(File::exists($dumpPath))
      ->toBeTrue("Dump file was not created for {$connection} at path: {$dumpPath}");

   expect(File::size($dumpPath))
      ->toBeGreaterThan(0, "Dump file for {$connection} is empty.");

})->with('database_drivers');
