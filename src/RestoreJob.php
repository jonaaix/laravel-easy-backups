<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups;

use Aaix\LaravelEasyBackups\Contracts\ProcessExecutor;
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

class RestoreJob implements ShouldQueue
{
   use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

   private string $tempDirectory;

   public function __construct(
      private readonly string $sourceDisk,
      private ?string $sourcePath,
      private readonly string $sourceDirectory,
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
         $localPath = $this->tempDirectory . DIRECTORY_SEPARATOR . basename($this->sourcePath);

         // Download
         File::put($localPath, Storage::disk($this->sourceDisk)->get($this->sourcePath));

         $dumpPath = match(true) {
            Str::endsWith($localPath, '.zip') => $this->extractZip($localPath),
            Str::endsWith($localPath, ['.tar', '.gz', '.zst']) => $this->extractTar($localPath),
            Str::endsWith($localPath, '.sql') => $localPath,
            default => throw new \Exception('Unsupported backup format.'),
         };

         if ($this->shouldWipe) {
            WiperFactory::create($this->databaseConnection)->wipe();
         }

         ImporterFactory::create($this->databaseConnection)->importFromFile($dumpPath);

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

   private function extractTar(string $path): string
   {
      $flags = '-xf';
      if (Str::endsWith($path, '.gz')) $flags = '-zxf';
      if (Str::endsWith($path, '.zst')) $flags = '--zstd -xf';

      app(ProcessExecutor::class)->execute(
         sprintf('tar %s %s -C %s', $flags, escapeshellarg($path), escapeshellarg($this->tempDirectory)),
         $this->tempDirectory
      );

      return $this->validateAndFindSql();
   }

   private function extractZip(string $path): string
   {
      $zip = new \ZipArchive();
      if ($zip->open($path) !== true) {
         throw new \Exception('Failed to open zip archive.');
      }
      if ($this->password) {
         $zip->setPassword($this->password);
      }
      $zip->extractTo($this->tempDirectory);
      $zip->close();

      return $this->validateAndFindSql();
   }

   private function validateAndFindSql(): string
   {
      // Search recursively
      $files = File::allFiles($this->tempDirectory);
      $sqlFiles = array_filter($files, fn($file) => $file->getExtension() === 'sql');

      $count = count($sqlFiles);

      if ($count === 0) {
         throw new \Exception('No SQL file found in archive.');
      }

      if ($count > 1) {
         throw new \Exception("Ambiguous backup: {$count} SQL files found. Automated restore requires exactly one.");
      }

      return reset($sqlFiles)->getRealPath();
   }

   private function findLatestBackupPath(): string
   {
      $disk = Storage::disk($this->sourceDisk);
      $latestFile = collect($disk->files($this->sourceDirectory))
         ->filter(fn (string $file) => Str::endsWith($file, ['.zip', '.sql', '.tar', '.gz', '.zst']))
         ->mapWithKeys(fn (string $file) => [$file => $disk->lastModified($file)])
         ->sortDesc()
         ->keys()
         ->first();

      if (!$latestFile) {
         throw new \Exception("No valid backup found in path '{$this->sourceDirectory}'.");
      }

      return $latestFile;
   }
}
