<?php

use Aaix\LaravelEasyBackups\Restorer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

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
