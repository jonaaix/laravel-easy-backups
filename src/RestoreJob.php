<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups;

use Aaix\LaravelEasyBackups\Events\RestoreFailed;
use Aaix\LaravelEasyBackups\Events\RestoreSucceeded;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

class RestoreJob implements ShouldQueue
{
   use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

   private string $tempDirectory;

   public function __construct(
      private readonly string $sourceDisk,
      private ?string $sourcePath,
      private readonly string $databaseConnection,
      private readonly ?string $password,
      private readonly bool $shouldWipe = true,
      private readonly bool $useLatest = false,
   ) {
      $this->tempDirectory = storage_path('app/easy-backups-temp/' . Str::random(16));
   }

   public function handle(): void
   {
      if ($this->useLatest) {
         $this->sourcePath = $this->findLatestBackupPath();
      }

      try {
         File::ensureDirectoryExists($this->tempDirectory);

         // 1. Download backup
         $localBackupPath = $this->tempDirectory . DIRECTORY_SEPARATOR . basename($this->sourcePath);
         $backupContent = Storage::disk($this->sourceDisk)->get($this->sourcePath);
         File::put($localBackupPath, $backupContent);

         // 2. Extract archive
         $zip = new ZipArchive();
         if ($zip->open($localBackupPath) !== true) {
            throw new \Exception('Failed to open backup archive.');
         }
         if ($this->password) {
            $zip->setPassword($this->password);
         }
         $zip->extractTo($this->tempDirectory);
         $zip->close();

         // 3. Find SQL dump
         $sqlFiles = File::glob($this->tempDirectory . DIRECTORY_SEPARATOR . 'db-dump_*.sql');
         if (empty($sqlFiles)) {
            throw new \Exception('No SQL dump file found in the archive.');
         }
         $dumpPath = $sqlFiles[0];

         // 4. Wipe the database (if enabled)
         if ($this->shouldWipe) {
            $wiper = WiperFactory::create($this->databaseConnection);
            $wiper->wipe();
         }

         // 5. Import SQL dump
         $importer = ImporterFactory::create($this->databaseConnection);
         $importer->importFromFile($dumpPath);

         event(new RestoreSucceeded($this->sourceDisk, $this->sourcePath, $this->databaseConnection));

      } catch (Throwable $e) {
         event(new RestoreFailed(
            ['sourceDisk' => $this->sourceDisk, 'sourcePath' => $this->sourcePath, 'database' => $this->databaseConnection],
            $e
         ));
         throw $e;
      } finally {
         if (File::isDirectory($this->tempDirectory)) {
            File::deleteDirectory($this->tempDirectory);
         }
      }
   }

   private function findLatestBackupPath(): string
   {
      $disk = Storage::disk($this->sourceDisk);
      // TODO: If path is overridden, we should use that instead of the default one.
      $path = config('easy-backups.defaults.database.remote_storage_path');

      $latestFile = collect($disk->files($path))
         ->filter(fn ($file) => str_starts_with(basename($file), 'backup-') && str_ends_with($file, '.zip'))
         ->mapWithKeys(fn ($file) => [$file => $disk->lastModified($file)])
         ->sortDesc()
         ->keys()
         ->first();

      if (!$latestFile) {
         throw new \Exception("No backup found on disk '{$this->sourceDisk}' in path '{$path}'.");
      }

      return $latestFile;
   }
}
