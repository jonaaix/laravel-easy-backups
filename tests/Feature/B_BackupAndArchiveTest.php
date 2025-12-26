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

   $backupPaths = Backup::database($connection)
      ->setLocalStorageDir($tempDir)
      ->setTempDirectory($tempDir)
      ->compress()
      ->run();

   expect($backupPaths)->toBeArray()->toHaveCount(1);
   $backupFile = $backupPaths[0];

   expect(File::exists($backupFile))->toBeTrue();

   $validExtensions = ['zip', 'tar', 'gz', 'zst'];
   $extension = pathinfo($backupFile, PATHINFO_EXTENSION);
   expect(in_array($extension, $validExtensions))->toBeTrue();

   // Only check ZIP integrity if it is a zip
   if ($extension === 'zip') {
      $zip = new \ZipArchive();
      expect($zip->open($backupFile, \ZipArchive::CHECKCONS))->toBeTrue();
      // Expect exactly one SQL file inside
      $filesInZip = [];
      for($i = 0; $i < $zip->numFiles; $i++) {
         $filesInZip[] = $zip->getNameIndex($i);
      }
      // Since the timestamp varies, we check strictly for .sql ending
      $sqlFiles = array_filter($filesInZip, fn($name) => str_ends_with($name, '.sql'));
      expect(count($sqlFiles))->toBe(1);

      $dumpContent = $zip->getFromName(reset($sqlFiles));
      expect($dumpContent)->toContain('test_table');
      $zip->close();
   }

   File::delete($backupFile);
})->with('database_drivers');


it('creates a password-protected backup archive', function () {
   $tempDir = $this->setupTemporaryDirectory();
   $this->setupTemporarySqliteDatabase();
   $this->createTestTableAndDataForDump('sqlite_test');
   $password = 'secret-password';

   // NEW API USAGE: toLocalDir() statt setLocalStorageDir()
   $backupPaths = Backup::database('sqlite_test')
      ->setLocalStorageDir($tempDir)
      ->setTempDirectory($tempDir)
      ->encryptWithPassword($password)
      ->run();

   expect($backupPaths)->toBeArray()->toHaveCount(1);
   $backupFile = $backupPaths[0];
   expect(File::exists($backupFile))->toBeTrue();
   expect($backupFile)->toEndWith('.zip'); // Password implies ZIP

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
