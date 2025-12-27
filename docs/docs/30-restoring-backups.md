---
sidebar_position: 30
---

# Restoring Backups

This guide explains how to restore a database from a previously created backup. The recommended approach is to create a dedicated Artisan command for your restore logic, making the process repeatable, testable, and version-controlled.

## Setting up your Restore Command

We start by creating a new Artisan command specifically for restoring the database.

```bash
php artisan make:command Backup\\DatabaseRestoreCommand
```

This will create a new command file at `app/Console/Commands/Backup/DatabaseRestoreCommand.php`.

Next, we'll implement the logic to restore the latest available backup from a remote disk using the `Restorer` facade.

```php
namespace App\Console\Commands\Backup;

use Illuminate\Console\Command;
use Aaix\LaravelEasyBackups\Facades\Restorer;

class DatabaseRestoreCommand extends Command
{
    protected $signature = 'app:backup:db:restore-latest';

    protected $description = 'Restores the latest database backup from the specified disk.';

    public function handle(): void
    {
        if ($this->confirm('Are you sure you want to restore the database? This will wipe the current database.')) {
            $this->info('Starting database restore...');

            Restorer::database()
                ->fromDisk('s3')
                ->toDatabase(config('database.default'))
                ->latest()
                ->run();

            $this->info('Latest database backup restored successfully!');
        }
    }
}
```

### Let's break down what's happening here:

* `Restorer::database()`: Initiates a new database restore builder.
* `->fromDisk('s3')`: Specifies the filesystem disk where the backups are located (e.g., 's3').
* `->toDatabase(...)`: Specifies the target database connection that you want to overwrite.
* `->latest()`: This is the key method for automation. It automatically finds the most recent backup file on the specified disk, eliminating the need to manually find the filename.
* `->run()`: This starts the restore process, which is dispatched to your default queue.

### Alternative: Restoring a Specific Backup

If you need to restore an older, specific backup file instead of the latest one, you can replace `->latest()` with `->fromPath()`:

```php
Restorer::database()
    ->fromDisk('s3')
    ->fromPath('path/on/disk/backup-2025-08-10_02-00-00.zip') // Specify the exact file
    ->toDatabase(config('database.default'))
    ->run();
```

### Important: Wiping the Database

**By default, the restore process will completely wipe all existing tables from the target database before importing the backup.** This is a critical safety feature to ensure a clean and predictable restore.

If you need to restore into a database without deleting existing tables, you can use the `disableWipe()` method.

<details>
<summary>Under The Hood: The Restore Workflow</summary>

When you call `run()`, a `RestoreJob` is dispatched to the queue. Here’s a summary of what happens inside:

1. **Download & Extract**: The job downloads the backup archive from its source disk (e.g., 's3' or 'local') into a temporary local directory. It then extracts the archive, using your password if you provided one.
2. **Locate SQL Dump**: It finds the `db-dump_...sql` file inside the extracted contents.
3. **Wipe Database**: If `disableWipe()` was not called, a driver-specific `Wiper` class drops all tables from the target database.
4. **Import Dump**: A driver-specific `Importer` class executes the SQL dump file against the target database, restoring its structure and data.
5. **Cleanup & Events**: The temporary directory is deleted, and a `RestoreSucceeded` or `RestoreFailed` event is dispatched.

</details>

## Recipe: An Interactive Restore Command

While the `--latest` flag is perfect for automation, you often need to restore a specific, recent backup in your development or staging environment. Manually finding and typing the filename is tedious and error-prone.

This recipe shows you how to build a powerful, interactive restore command that presents a list of the 30 most recent backups for you to choose from.

### The Final Command

First, create the command if you haven't already:

```shell
php artisan make:command Backup\\DatabaseRestoreCommand
```

Then, replace the entire content of the file with the following code. It combines both the interactive selector and the `--latest` flag functionality.

```php
<?php

namespace App\Console\Commands\Backup;

use Illuminate\Console\Command;
use Aaix\LaravelEasyBackups\Facades\Restorer;
use function Laravel\Prompts\select;

class DatabaseRestoreCommand extends Command
{
    protected $signature = 'backup:db:restore {--latest : Restore the latest backup without prompting}';

    protected $description = 'Restores a database backup, either the latest one or from an interactive selection.';

    public function handle(): int
    {
        if ($this->option('latest')) {
            return $this->restoreLatest();
        }

        return $this->restoreFromSelection();
    }

    private function restoreLatest(): int
    {
        if (!$this->confirm('Are you sure you want to restore the LATEST backup? This will wipe the current database.')) {
            return self::SUCCESS;
        }

        $this->info('Starting restore of the LATEST backup...');

        Restorer::database()
            ->fromDisk('s3')
            ->toDatabase(config('database.default'))
            ->latest()
            ->run();

        $this->info('Latest database backup restored successfully!');
        
        return self::SUCCESS;
    }

    private function restoreFromSelection(): int
    {
        $this->info('Fetching recent backups from disk...');

        $backups = Restorer::getRecentBackups('s3');

        if ($backups->isEmpty()) {
            $this->warn('No backups found on disk \'s3\'.');
            return self::SUCCESS;
        }

        $selectedPath = select(
            label: 'Which backup would you like to restore?',
            options: $backups->pluck('label', 'path')->all(),
            scroll: 10,
            required: true
        );

        if ($this->confirm("Are you sure you want to restore '" . basename($selectedPath) . "'? This will wipe the current database.")) {
            $this->info("Starting restore of '" . basename($selectedPath) . "'...");

            Restorer::database()
                ->fromDisk('s3')
                ->fromPath($selectedPath)
                ->toDatabase(config('database.default'))
                ->run();

            $this->info('Database backup restored successfully!');
        }

        return self::SUCCESS;
    }
}
```

You can now run the command in two ways:

```shell
# For an interactive selection
php artisan backup:db:restore

# To automatically restore the latest backup
php artisan backup:db:restore --latest
```
