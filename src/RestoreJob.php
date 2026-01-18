<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups;

use Aaix\LaravelEasyBackups\Contracts\ProcessExecutor;
use Aaix\LaravelEasyBackups\Events\RestoreFailed;
use Aaix\LaravelEasyBackups\Events\RestoreSucceeded;
use Aaix\LaravelEasyBackups\Services\PathGenerator;
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
      private readonly ?string $saveCopyDisk = null,
   ) {
      // Refactored: Use centralized absolute path logic
      $this->tempDirectory = app(PathGenerator::class)->getAbsoluteTempPath() . '/' . Str::random(16);
   }

   public function handle(): void
   {
      if ($this->useLatest) {
         $this->sourcePath = $this->findLatestBackupPath();
      }

      try {
         File::ensureDirectoryExists($this->tempDirectory);
         $localPath = $this->tempDirectory . DIRECTORY_SEPARATOR . basename($this->sourcePath);

         // Download via Stream to save memory (RAM-safe for large files)
         Storage::disk($this->sourceDisk)->readStream($this->sourcePath)
            ? File::put($localPath, Storage::disk($this->sourceDisk)->readStream($this->sourcePath))
            : File::put($localPath, Storage::disk($this->sourceDisk)->get($this->sourcePath));

         // Logic for saving a local copy if requested
         if ($this->saveCopyDisk) {
            // Determine target directory via PathGenerator (Centralized Logic)
            $directory = app(PathGenerator::class)->getDatabaseLocalPath();

            $targetPath = $directory
               ? rtrim($directory, '/') . '/' . basename($this->sourcePath)
               : basename($this->sourcePath);

            // Use 'rb' for binary safety and stream to prevent OOM
            Storage::disk($this->saveCopyDisk)->put($targetPath, fopen($localPath, 'rb'));
         }

         // Detect format and extract
         $dumpPath = match (true) {
            Str::endsWith($localPath, '.zip') => $this->extractZip($localPath),
            Str::endsWith($localPath, ['.tar', '.gz', '.zst']) => $this->extractTar($localPath),
            Str::endsWith($localPath, '.sql') => $localPath,
            default => throw new \Exception('Unsupported backup format.'),
         };

         // Wipe database if requested
         if ($this->shouldWipe) {
            WiperFactory::create($this->databaseConnection)->wipe();
         }

         // Import the dump
         ImporterFactory::create($this->databaseConnection)->importFromFile($dumpPath);

         event(new RestoreSucceeded($this->sourceDisk, $this->sourcePath, $this->databaseConnection));
      } catch (Throwable $e) {
         event(new RestoreFailed(
            ['sourceDisk' => $this->sourceDisk, 'sourcePath' => $this->sourcePath, 'database' => $this->databaseConnection],
            $e
         ));
         throw $e;
      } finally {
         // Always cleanup temp directory
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

      // Disable timeout for large archive extraction
      app(ProcessExecutor::class)->execute(
         command: sprintf('tar %s %s -C %s', $flags, escapeshellarg($path), escapeshellarg($this->tempDirectory)),
         cwd: $this->tempDirectory,
         timeout: null
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
      // Search recursively for the .sql file
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
         ->filter(fn(string $file) => Str::endsWith($file, ['.zip', '.sql', '.tar', '.gz', '.zst']))
         ->mapWithKeys(fn(string $file) => [$file => $disk->lastModified($file)])
         ->sortDesc()
         ->keys()
         ->first();

      if (!$latestFile) {
         throw new \Exception("No valid backup found in path '{$this->sourceDirectory}'.");
      }

      return $latestFile;
   }
}
