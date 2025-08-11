<?php

use Aaix\LaravelEasyBackups\Backup;
use Aaix\LaravelEasyBackups\Contracts\ProcessExecutor;
use Aaix\LaravelEasyBackups\Tests\Support\DockerProcessExecutor;
use Illuminate\Support\Facades\File;

it('creates a valid, compressed backup archive for each driver', function (string $connection) {
   $tempDir = $this->setupTemporaryDirectory();

   if ($connection === 'sqlite_test') {
      $this->setupTemporarySqliteDatabase();
   } else {
      $this->app->bind(ProcessExecutor::class, fn () => new DockerProcessExecutor($connection));
   }

   $this->createTestTableAndDataForDump($connection);

   $backupPaths = Backup::create()
      ->includeDatabases([$connection])
      ->setLocalStorageDir($tempDir)
      ->setTempDirectory($tempDir)
      ->compress()
      ->run();

   expect($backupPaths)->toBeArray()->toHaveCount(1);
   $backupFile = $backupPaths[0];

   expect(File::exists($backupFile))->toBeTrue();
   expect($backupFile)->toEndWith('.zip');

   $zip = new \ZipArchive();
   expect($zip->open($backupFile, \ZipArchive::CHECKCONS))->toBeTrue();
   expect($zip->numFiles)->toBe(1);
   expect($zip->getNameIndex(0))->toBe("db-dump_{$connection}.sql");

   $dumpContent = $zip->getFromIndex(0);
   expect($dumpContent)->toContain('test_table');

   $zip->close();
   File::delete($backupFile);
})->with('database_drivers');


it('creates a password-protected backup archive', function () {
   $tempDir = $this->setupTemporaryDirectory();
   $this->setupTemporarySqliteDatabase();
   $this->createTestTableAndDataForDump('sqlite_test');
   $password = 'secret-password';

   $backupPaths = Backup::create()
      ->includeDatabases(['sqlite_test'])
      ->setLocalStorageDir($tempDir)
      ->setTempDirectory($tempDir)
      ->encryptWithPassword($password)
      ->run();

   expect($backupPaths)->toBeArray()->toHaveCount(1);
   $backupFile = $backupPaths[0];
   expect(File::exists($backupFile))->toBeTrue();

   $zip = new \ZipArchive();
   expect($zip->open($backupFile))->toBeTrue();

   $fileStats = $zip->statIndex(0);
   expect($fileStats['encryption_method'])->not->toBe(0, 'File inside the zip is not encrypted.');

   $zip->setPassword($password);
   $extractedContent = $zip->getFromIndex(0);
   $hasTableName = strpos($extractedContent, 'test_table') !== false;
   expect($hasTableName)->toBeTrue('Could not extract encrypted file with correct password or the file is empty.');

   $zip->close();

   File::delete($backupFile);
});
