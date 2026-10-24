<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups;

use Aaix\LaravelEasyBackups\Contracts\ProcessExecutor;
use Aaix\LaravelEasyBackups\Events\RestoreFailed;
use Aaix\LaravelEasyBackups\Events\RestoreSucceeded;
use Aaix\LaravelEasyBackups\Services\BackupInventoryService;
use Aaix\LaravelEasyBackups\Services\ConsoleFeedback;
use Aaix\LaravelEasyBackups\Services\PathGenerator;
use Aaix\LaravelEasyBackups\Services\TransferProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\UnableToRetrieveMetadata;
use Throwable;

class RestoreJob implements ShouldQueue
{
   use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

   private const DOWNLOAD_CHUNK_BYTES = 1024 * 1024;

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
      $this->tempDirectory = app(PathGenerator::class)->getAbsoluteTempPath() . '/' . Str::random(16);
   }

   public function handle(): void
   {
      if ($this->useLatest) {
         $this->sourcePath = $this->findLatestBackupPath();
      }

      ConsoleFeedback::info("Starting restore for database '{$this->databaseConnection}'...");

      try {
         File::ensureDirectoryExists($this->tempDirectory);
         $localPath = $this->tempDirectory . DIRECTORY_SEPARATOR . basename($this->sourcePath);

         // Step 1: Download
         ConsoleFeedback::step("Downloading backup from disk '{$this->sourceDisk}'...");
         $this->download($localPath);

         if (File::size($localPath) === 0) {
            throw new \Exception("Backup '{$this->sourcePath}' on disk '{$this->sourceDisk}' is empty.");
         }

         if ($this->saveCopyDisk) {
            ConsoleFeedback::info("Caching a copy to local disk '{$this->saveCopyDisk}'...");
            $directory = app(PathGenerator::class)->getDatabaseLocalPath();
            $targetPath = $directory
               ? rtrim($directory, '/') . '/' . basename($this->sourcePath)
               : basename($this->sourcePath);

            Storage::disk($this->saveCopyDisk)->put($targetPath, fopen($localPath, 'rb'));
         }

         // Step 2: Extraction
         ConsoleFeedback::step("Analyzing and extracting archive...");
         $dumpPath = match (true) {
            Str::endsWith($localPath, '.zip') => $this->extractZip($localPath),
            Str::endsWith($localPath, ['.tar', '.gz', '.zst']) => $this->extractTar($localPath),
            Str::endsWith($localPath, '.sql') => $localPath,
            default => throw new \Exception('Unsupported backup format.'),
         };

         // Step 3: Wiping
         if ($this->shouldWipe) {
            ConsoleFeedback::warning("Wiping existing data in '{$this->databaseConnection}'...");
            WiperFactory::create($this->databaseConnection)->wipe();
         } else {
            ConsoleFeedback::info("Skipping database wipe.");
         }

         // Step 4: Import
         ConsoleFeedback::step("Importing SQL dump into database...");
         ImporterFactory::create($this->databaseConnection)->importFromFile($dumpPath);

         ConsoleFeedback::success("Restore completed successfully.");
         event(new RestoreSucceeded($this->sourceDisk, $this->sourcePath, $this->databaseConnection));

      } catch (Throwable $e) {
         ConsoleFeedback::error("Restore failed: " . $e->getMessage());

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

   private function download(string $localPath): void
   {
      $disk = Storage::disk($this->sourceDisk);

      try {
         $totalBytes = $disk->size($this->sourcePath);
      } catch (UnableToRetrieveMetadata $e) {
         throw new \Exception("Backup '{$this->sourcePath}' not found on disk '{$this->sourceDisk}'.", previous: $e);
      }

      $progress = TransferProgress::start('Downloaded', $totalBytes);
      $source = $disk->readStream($this->sourcePath);

      if (!$source) {
         File::put($localPath, $disk->get($this->sourcePath));
         $progress->advance(File::size($localPath));
         $progress->finish();
         return;
      }

      $target = fopen($localPath, 'wb');

      try {
         while (($copied = stream_copy_to_stream($source, $target, self::DOWNLOAD_CHUNK_BYTES)) > 0) {
            $progress->advance($copied);
         }
      } finally {
         fclose($target);
         if (is_resource($source)) {
            fclose($source);
         }
      }

      $progress->finish();
   }

   private function extractTar(string $path): string
   {
      $flags = '-xf';
      if (Str::endsWith($path, '.gz')) $flags = '-zxf';
      if (Str::endsWith($path, '.zst')) $flags = '--zstd -xf';

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
      $inventory = app(BackupInventoryService::class);
      $latest = $inventory->findLatest($this->sourceDisk, $this->sourceDirectory);

      if (!$latest) {
         throw new \Exception("No valid backup found in path '{$this->sourceDirectory}'.");
      }

      return $inventory->diskRelativePath($this->sourceDisk, $latest['path']);
   }
}
