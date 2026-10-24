<?php

use Aaix\LaravelEasyBackups\Restorer;
use Aaix\LaravelEasyBackups\Tests\Support\MetadataCountingAdapter;
use Illuminate\Filesystem\FilesystemAdapter as LaravelFilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

it('restores the latest backup from a local disk', function () {
   $root = __DIR__ . '/../temp/restore-latest-local';
   File::deleteDirectory($root);

   $backupDir = $root . '/easy-backups/database';
   File::ensureDirectoryExists($backupDir);

   file_put_contents($backupDir . '/db-dump_old.sql', 'CREATE TABLE old_backup (id INTEGER);');
   touch($backupDir . '/db-dump_old.sql', time() - 300);
   file_put_contents($backupDir . '/db-dump_new.sql', 'CREATE TABLE new_backup (id INTEGER);');
   touch($backupDir . '/db-dump_new.sql', time() - 100);

   config()->set('filesystems.disks.restore-latest', ['driver' => 'local', 'root' => $root]);
   $databasePath = $this->setupTemporarySqliteDatabase('restore-latest.sqlite');

   Restorer::database()
      ->fromDisk('restore-latest')
      ->fromDir('easy-backups/database')
      ->toDatabase('sqlite_test')
      ->disableWipe()
      ->latest()
      ->run();

   $tables = collect(DB::connection('sqlite_test')->select("SELECT name FROM sqlite_master WHERE type = 'table'"))
      ->pluck('name')
      ->all();

   expect($tables)->toBe(['new_backup']);

   DB::purge('sqlite_test');
   File::delete($databasePath);
   File::deleteDirectory($root);
});

it('aborts before wiping when the backup cannot be read', function (string $backupPath, ?string $contents, string $reason) {
   $root = __DIR__ . '/../temp/restore-unreadable';
   File::deleteDirectory($root);
   File::ensureDirectoryExists($root . '/easy-backups/database');

   if ($contents !== null) {
      file_put_contents($root . '/' . $backupPath, $contents);
   }

   config()->set('filesystems.disks.restore-unreadable', ['driver' => 'local', 'root' => $root]);
   $databasePath = $this->setupTemporarySqliteDatabase('restore-unreadable.sqlite');
   DB::connection('sqlite_test')->statement('CREATE TABLE existing_data (id INTEGER)');

   $restore = fn() => Restorer::database()
      ->fromDisk('restore-unreadable')
      ->fromPath($backupPath)
      ->fromDir('easy-backups/database')
      ->toDatabase('sqlite_test')
      ->run();

   expect($restore)->toThrow(\Exception::class, $reason);

   $tables = collect(DB::connection('sqlite_test')->select("SELECT name FROM sqlite_master WHERE type = 'table'"))
      ->pluck('name')
      ->all();

   expect($tables)->toBe(['existing_data']);

   DB::purge('sqlite_test');
   File::delete($databasePath);
   File::deleteDirectory($root);
})->with([
   'missing file' => ['easy-backups/database/db-dump_missing.sql', null, 'not found'],
   'empty file' => ['easy-backups/database/db-dump_empty.sql', '', 'is empty'],
]);

it('aborts before wiping when the download arrives incomplete', function () {
   $root = __DIR__ . '/../temp/restore-truncated';
   File::deleteDirectory($root);
   File::ensureDirectoryExists($root . '/easy-backups/database');
   file_put_contents($root . '/easy-backups/database/db-dump_truncated.sql', 'CREATE TABLE restored (id INTEGER);');

   $truncating = new class (new LocalFilesystemAdapter($root)) extends MetadataCountingAdapter {
      public function readStream(string $path)
      {
         $full = stream_get_contents(parent::readStream($path));
         $half = fopen('php://memory', 'r+b');
         fwrite($half, substr($full, 0, intdiv(strlen($full), 2)));
         rewind($half);

         return $half;
      }
   };

   config()->set('filesystems.disks.restore-truncated', ['driver' => 'truncating']);
   Storage::extend('truncating', fn($app, $config) => new LaravelFilesystemAdapter(
      new Flysystem($truncating),
      $truncating,
      $config
   ));

   $databasePath = $this->setupTemporarySqliteDatabase('restore-truncated.sqlite');
   DB::connection('sqlite_test')->statement('CREATE TABLE existing_data (id INTEGER)');

   $restore = fn() => Restorer::database()
      ->fromDisk('restore-truncated')
      ->fromPath('easy-backups/database/db-dump_truncated.sql')
      ->fromDir('easy-backups/database')
      ->toDatabase('sqlite_test')
      ->run();

   expect($restore)->toThrow(\Exception::class, 'downloaded incompletely');

   $tables = collect(DB::connection('sqlite_test')->select("SELECT name FROM sqlite_master WHERE type = 'table'"))
      ->pluck('name')
      ->all();

   expect($tables)->toBe(['existing_data']);

   DB::purge('sqlite_test');
   File::delete($databasePath);
   File::deleteDirectory($root);
});
