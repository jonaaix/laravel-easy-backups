<?php

namespace App\Console\Commands\DB;

use App\Enums\DiskEnum;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;

class DB_BackupCleanS3Cmd extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:backup:clean-s3 {--keep-last= : Keep the last n backups on S3. Deletes older backups.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleans old database backups from S3, keeping a specified number of recent backups.';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void
    {
        $keepLastOption = $this->option('keep-last');

        if (!$keepLastOption) {
            $this->info('No --keep-last option provided. Skipping S3 backup cleaning.');
            return;
        }

        $keepLast = filter_var($keepLastOption, FILTER_VALIDATE_INT);

        if ($keepLast === false || $keepLast <= 0) {
            $this->error('Invalid value for --keep-last. Must be a positive integer. Provided: ' . $keepLastOption);
            return;
        }

        $this->info("Attempting to clean S3 backups, aiming to keep the last $keepLast.");

        $s3 = Storage::disk(DiskEnum::BACKUP);
        $prefix = config('app.env') === 'production' ? 'prod' : 'dev';
        $backupDir = "$prefix/mysql";

        $allFiles = $s3->files($backupDir);

        if (empty($allFiles)) {
            $this->info('No backups found on S3 in directory: ' . $backupDir);
            return;
        }

        $this->info('Available backups on S3 before cleaning (' . count($allFiles) . ' in ' . $backupDir . '):');

        $backupsWithTime = collect($allFiles)->mapWithKeys(function ($file) use ($s3) {
            return [$file => $s3->lastModified($file)];
        })->sort(); // Sorts by timestamp, oldest first

        foreach ($backupsWithTime as $file => $timestamp) {
            $this->line(' - ' . basename($file) . ' (Last Modified: ' . date('Y-m-d H:i:s T', $timestamp) . ')');
        }

        if (count($backupsWithTime) <= $keepLast) {
            $this->info('Number of backups (' . count($backupsWithTime) . ') is less than or equal to --keep-last (' . $keepLast . '). No backups will be deleted.');
            $this->logFinalBackupState($backupsWithTime, $backupDir);
            return;
        }

        $filesToDelete = $backupsWithTime->keys()->slice(0, count($backupsWithTime) - $keepLast);

        if ($filesToDelete->isEmpty()) {
            $this->info('No backups to delete to meet the --keep-last (' . $keepLast . ') criteria.');
            $this->logFinalBackupState($backupsWithTime, $backupDir);
            return;
        }

        $this->warn('Backups to be deleted (' . $filesToDelete->count() . '):');
        foreach ($filesToDelete as $file) {
            $this->line(' - ' . basename($file) . ' (Last Modified: ' . date('Y-m-d H:i:s T', $backupsWithTime[$file]) . ')');
        }

        $s3->delete($filesToDelete->toArray());
        $this->info('Successfully deleted ' . $filesToDelete->count() . ' old backup(s).');

        // Fetch remaining backups
        $remainingFilesRaw = $s3->files($backupDir);
        $remainingBackupsWithTime = collect($remainingFilesRaw)->mapWithKeys(function ($file) use ($s3) {
            return [$file => $s3->lastModified($file)];
        })->sort();

        $this->logFinalBackupState($remainingBackupsWithTime, $backupDir);
    }

    /**
     * Logs the final state of backups on S3.
     *
     * @param Collection $backupsCollection Sorted collection of backups (path => timestamp).
     * @param string $backupDir The directory on S3.
     * @return void
     */
    private function logFinalBackupState(Collection $backupsCollection, string $backupDir): void
    {
        $count = $backupsCollection->count();
        $separator = str_repeat('=', 80);

        $this->line(''); // Add a blank line before for better separation
        $this->warn($separator);
        $this->warn('Final S3 Backup State in ' . $backupDir);
        $this->warn($separator);

        $this->line('Total available backups: ' . $count);

        if ($backupsCollection->isEmpty()) {
            $this->line('No backups remaining.');
        } else {
            // Oldest backup date is from the full collection (sorted oldest first)
            $oldestBackupTimestamp = $backupsCollection->first();
            $this->line('Oldest remaining backup date: ' . date('Y-m-d H:i:s T', $oldestBackupTimestamp));

            $this->line(''); // Spacer
            $this->info('Showing the 5 newest remaining backups (of ' . $count . ' total):');

            // To get the newest 5, we take the last 5 from the collection (which is sorted oldest to newest)
            $newestFive = $backupsCollection->slice(-5)->reverse(); // Reverse to show newest first in the log

            $s3 = Storage::disk(DiskEnum::BACKUP);

            foreach ($newestFive as $file => $timestamp) {
                $fileSize = $s3->size($file);
                $fileSizeMB = number_format($fileSize / 1024 / 1024, 3);
                $this->line(' - ' . basename($file) . ' (Size: ' . $fileSizeMB . ' MB, Last Modified: ' . date('Y-m-d H:i:s T', $timestamp) . ')');
            }

            if ($count > 5) {
                $this->line('... and ' . ($count - 5) . ' older backup(s).');
            }
        }

        $this->line($separator);
        $this->line(''); // Add a blank line after
    }
}
