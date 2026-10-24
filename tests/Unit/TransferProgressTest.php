<?php

use Aaix\LaravelEasyBackups\Actions\UploadBackupAction;
use Aaix\LaravelEasyBackups\Restorer;
use Aaix\LaravelEasyBackups\Services\ProgressReportingStream;
use Aaix\LaravelEasyBackups\Services\TransferProgress;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

it('downloads a backup larger than one chunk completely', function () {
   $root = __DIR__ . '/../temp/transfer-download';
   File::deleteDirectory($root);
   File::ensureDirectoryExists($root . '/easy-backups/database');

   $rows = 40000;
   $sql = "CREATE TABLE payload (id INTEGER, filler TEXT);\n";
   for ($i = 1; $i <= $rows; $i++) {
      $sql .= "INSERT INTO payload VALUES ({$i}, '" . str_repeat('x', 40) . "');\n";
   }
   file_put_contents($root . '/easy-backups/database/db-dump_large.sql', $sql);
   expect(strlen($sql))->toBeGreaterThan(2 * 1024 * 1024);

   config()->set('filesystems.disks.transfer-download', ['driver' => 'local', 'root' => $root]);
   $databasePath = $this->setupTemporarySqliteDatabase('transfer-download.sqlite');

   Restorer::database()
      ->fromDisk('transfer-download')
      ->fromPath('easy-backups/database/db-dump_large.sql')
      ->fromDir('easy-backups/database')
      ->toDatabase('sqlite_test')
      ->disableWipe()
      ->run();

   expect(DB::connection('sqlite_test')->table('payload')->count())->toBe($rows);

   DB::purge('sqlite_test');
   File::delete($databasePath);
   File::deleteDirectory($root);
});

it('uploads a backup unchanged through the progress stream', function () {
   $root = __DIR__ . '/../temp/transfer-upload';
   File::deleteDirectory($root);
   File::ensureDirectoryExists($root . '/remote');

   $localPath = $root . '/db-dump_upload.tar.gz';
   file_put_contents($localPath, random_bytes(3 * 1024 * 1024 + 123));

   config()->set('filesystems.disks.transfer-upload', ['driver' => 'local', 'root' => $root . '/remote']);

   app(UploadBackupAction::class)->execute($localPath, 'transfer-upload', 'production/db-backup/sqlite');

   $uploaded = Storage::disk('transfer-upload')->path('production/db-backup/sqlite/db-dump_upload.tar.gz');
   expect(hash_file('sha256', $uploaded))->toBe(hash_file('sha256', $localPath));

   File::deleteDirectory($root);
});

it('exposes size and seeking of the wrapped file', function () {
   $root = __DIR__ . '/../temp/transfer-stream';
   File::deleteDirectory($root);
   File::ensureDirectoryExists($root);

   $path = $root . '/backup.sql';
   file_put_contents($path, 'abcdefghij');

   $stream = ProgressReportingStream::open($path, TransferProgress::start('Uploaded', 10));

   expect(fstat($stream)['size'])->toBe(10);
   expect(stream_get_meta_data($stream)['seekable'])->toBeTrue();
   expect(fread($stream, 4))->toBe('abcd');
   rewind($stream);
   expect(stream_get_contents($stream))->toBe('abcdefghij');

   fclose($stream);
   File::deleteDirectory($root);
});
