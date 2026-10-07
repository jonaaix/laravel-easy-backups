<?php

use Aaix\LaravelEasyBackups\Restorer;
use Aaix\LaravelEasyBackups\Services\BackupInventoryService;
use Aaix\LaravelEasyBackups\Tests\Support\MetadataCountingAdapter;
use Illuminate\Filesystem\FilesystemAdapter as LaravelFilesystemAdapter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

function registerCountingDisk(string $root): MetadataCountingAdapter
{
   File::ensureDirectoryExists($root);
   $counting = new MetadataCountingAdapter(new LocalFilesystemAdapter($root));

   config()->set('filesystems.disks.counting', ['driver' => 'counting', 'root' => $root]);
   Storage::extend('counting', fn($app, $config) => new LaravelFilesystemAdapter(
      new Flysystem($counting),
      $counting,
      $config
   ));

   return $counting;
}

it('reads remote size and timestamp from the listing without per-file metadata calls', function () {
   $root = __DIR__ . '/../temp/inventory-listing';
   File::deleteDirectory($root);

   $counting = registerCountingDisk($root);

   $backupDir = $root . '/production/db-backups/mysql';
   File::ensureDirectoryExists($backupDir);

   foreach ([['a.tar.zst', 2048, 300], ['b.tar.zst', 4096, 200], ['c.tar.zst', 1024, 100]] as [$name, $bytes, $agoSeconds]) {
      $path = $backupDir . '/mysql-' . $name;
      file_put_contents($path, str_repeat('x', $bytes));
      touch($path, time() - $agoSeconds);
   }

   file_put_contents($backupDir . '/notes.txt', 'ignored');

   $backups = app(BackupInventoryService::class)->list('counting', 'production/db-backups', true);

   expect($backups)->toHaveCount(3);
   expect($counting->metadataCalls)->toBe(0);

   expect($backups->pluck('filename')->all())->toBe([
      'mysql-c.tar.zst',
      'mysql-b.tar.zst',
      'mysql-a.tar.zst',
   ]);

   expect($backups->firstWhere('filename', 'mysql-b.tar.zst')['size'])->toBe(4096);
   expect($backups->firstWhere('filename', 'mysql-b.tar.zst')['path'])
      ->toBe('production/db-backups/mysql/mysql-b.tar.zst');
   expect($backups->every(fn(array $entry) => $entry['last_modified'] > 0))->toBeTrue();

   File::deleteDirectory($root);
});

it('lists non-recursively without descending into driver subfolders', function () {
   $root = __DIR__ . '/../temp/inventory-listing-shallow';
   File::deleteDirectory($root);

   $counting = registerCountingDisk($root);

   File::ensureDirectoryExists($root . '/db-backups/mysql');
   file_put_contents($root . '/db-backups/top.tar.zst', str_repeat('x', 512));
   file_put_contents($root . '/db-backups/mysql/nested.tar.zst', str_repeat('x', 512));

   $backups = app(BackupInventoryService::class)->list('counting', 'db-backups', false);

   expect($backups->pluck('filename')->all())->toBe(['top.tar.zst']);
   expect($counting->metadataCalls)->toBe(0);

   File::deleteDirectory($root);
});

it('builds the restore selection from the listing without per-file metadata calls', function () {
   $root = __DIR__ . '/../temp/restore-listing';
   File::deleteDirectory($root);

   $counting = registerCountingDisk($root);

   $backupDir = $root . '/production/db-backup/mariadb';
   File::ensureDirectoryExists($backupDir);

   foreach ([['old.tar.gz', 2048, 300], ['new.tar.gz', 1024, 100]] as [$name, $bytes, $agoSeconds]) {
      $path = $backupDir . '/db-dump_' . $name;
      file_put_contents($path, str_repeat('x', $bytes));
      touch($path, time() - $agoSeconds);
   }

   $backups = Restorer::getRecentBackups('counting', 'production/db-backup/mariadb');

   expect($counting->metadataCalls)->toBe(0);
   expect($backups->pluck('path')->all())->toBe([
      'production/db-backup/mariadb/db-dump_new.tar.gz',
      'production/db-backup/mariadb/db-dump_old.tar.gz',
   ]);
   expect($backups->first()['label'])->toStartWith('[GZ] db-dump_new.tar.gz (1 KB, ');

   File::deleteDirectory($root);
});

it('returns disk-relative paths for local restore selections', function () {
   $root = __DIR__ . '/../temp/restore-listing-local';
   File::deleteDirectory($root);
   File::ensureDirectoryExists($root . '/easy-backups/database');
   file_put_contents($root . '/easy-backups/database/db-dump_local.sql', 'SELECT 1;');

   config()->set('filesystems.disks.restore-local', ['driver' => 'local', 'root' => $root]);

   $backups = Restorer::getRecentBackups('restore-local', 'easy-backups/database');

   expect($backups->pluck('path')->all())->toBe(['easy-backups/database/db-dump_local.sql']);
   expect(Storage::disk('restore-local')->exists($backups->first()['path']))->toBeTrue();

   File::deleteDirectory($root);
});
