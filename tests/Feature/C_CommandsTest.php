<?php

use Aaix\LaravelEasyBackups\Contracts\ProcessExecutor;
use Aaix\LaravelEasyBackups\Facades\Backup;
use Aaix\LaravelEasyBackups\Tests\Support\DockerProcessExecutor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

it('lists backups correctly using the list command', function () {
   $tempDir = $this->setupTemporaryDirectory();
   config()->set('filesystems.disks.test_disk', [
      'driver' => 'local',
      'root' => $tempDir,
   ]);
   config()->set('easy-backups.defaults.database.remote_storage_path', '');

   $backupFilename = 'backup-2025-08-11_19-50-00.zip';
   File::put($tempDir . '/' . $backupFilename, 'dummy content');

   $this->artisan('backup:list', ['--disk' => 'test_disk'])
      ->expectsOutputToContain($backupFilename)
      ->assertExitCode(0);
});

it('restores a database successfully using the restore command', function (string $connection) {
   // 1. Setup
   $tempDir = $this->setupTemporaryDirectory();

   if ($connection === 'sqlite_test') {
      $this->setupTemporarySqliteDatabase();
   } else {
      $this->app->bind(ProcessExecutor::class, fn () => new DockerProcessExecutor($connection));
   }

   $this->createTestTableAndDataForDump($connection);

   config()->set('filesystems.disks.test_disk', [
      'driver' => 'local',
      'root' => $tempDir,
   ]);

   // 2. Create a valid backup archive to restore from
   $backupPaths = Backup::create()
      ->includeDatabases([$connection])
      ->setLocalStorageDir($tempDir)
      ->setTempDirectory($tempDir)
      ->saveTo('test_disk')
      ->compress()
      ->run();

   $backupFilename = basename($backupPaths[0]);
   $fullPath = config('easy-backups.defaults.database.remote_storage_path') . $backupFilename;

   // 3. Simulate data loss by dropping the table
   $schema = DB::connection($connection)->getSchemaBuilder();
   $schema->dropIfExists('test_table');
   expect($schema->hasTable('test_table'))->toBeFalse();

   // 4. Run the restore command
   $this->artisan('backup:restore', [
      'filename' => $fullPath,
      '--disk' => 'test_disk',
      '--database' => $connection,
   ])->assertExitCode(0);

   // Force reconnect for file-based databases like SQLite after the file has been replaced.
   DB::purge($connection);
   DB::reconnect($connection);

   // 5. Verify that the data has been restored
   $schema = DB::connection($connection)->getSchemaBuilder(); // Re-get schema builder from new connection
   expect($schema->hasTable('test_table'))->toBeTrue();
   $restoredData = DB::connection($connection)->table('test_table')->first();
   expect($restoredData->name)->toBe('test_data');
})->with('database_drivers');
