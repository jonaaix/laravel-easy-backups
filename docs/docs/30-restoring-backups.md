---
sidebar_position: 30
---

# Restoring Backups

Restoring a database is a critical operation. This package provides robust tools to handle this process safely and efficiently, whether you are in a local development environment or managing production recovery.

## The Easy Way: Using the CLI

The package comes with a powerful, interactive Artisan command that handles 95% of use cases out of the box.

```bash
php artisan easy-backups:db:restore
```

When you run this command without arguments:

1. It connects to your configured remote disk (default: `s3-backup`).
2. It automatically scans the correct directory for your current environment and database driver.
3. It presents an **interactive list** allowing you to select the exact file to restore.
4. It asks for confirmation before wiping your database.

### Advanced CLI Features

**Automated Restoration (CI/CD friendly):**
Restore the absolutely latest backup without any prompts.

```bash
php artisan easy-backups:db:restore --latest
```

**Cross-Environment Restore:**
Pull a backup from the `production` environment into your local machine.

```bash
php artisan easy-backups:db:restore --source-env=production --local
```

**Smart Local Caching:**
When downloading a large backup from S3, the command can save a copy to your local disk. Future restores of the same file will check the local disk first, saving bandwidth and time.

## The Flexible Way: Custom Restore Logic

If you need to integrate the restore process into a custom workflow (e.g., sanitizing data after import), you can use the `Restorer` facade directly.

```php
use Aaix\LaravelEasyBackups\Facades\Restorer;

Restorer::database()
    ->fromDisk('s3-backup')
    ->toDatabase('mysql')
    ->latest()
    ->run();
```

### Restoring a Specific Backup

If you need to restore an older, specific backup file instead of the latest one, replace `->latest()` with `->fromPath()`:

```php
Restorer::database()
    ->fromDisk('s3-backup')
    ->fromPath('production/db-backups/mysql/db-dump_2025-08-10.sql')
    ->toDatabase('mysql')
    ->run();
```

### Important: Wiping the Database

**By default, the restore process will completely wipe all existing tables from the target database before importing the backup.** This is a critical safety feature to ensure a clean and predictable restore.

If you need to restore into a database without deleting existing tables, you can use the `disableWipe()` method:

```php
Restorer::database()
    ->fromDisk('local')
    ->latest()
    ->disableWipe()
    ->run();
```

## Source Code Reference

The standard `easy-backups:db:restore` command implements advanced logic like:

* **Environment Detection** to automatically find the correct paths.
* **Smart Caching** to keep local copies of remote downloads.

If you want to build your own specialized restore command, we recommend looking at the source code of our command as a blueprint.

[View src/Commands/RestoreDatabaseBackupCommand.php](https://github.com/jonaaix/laravel-easy-backups/blob/main/src/Commands/CreateDatabaseBackupCommand.php)

<details>
<summary>Under The Hood: The Restore Workflow</summary>

When you call `run()`, a `RestoreJob` is dispatched to the queue. Here’s a summary of what happens inside:

1. **Download & Extract**: The job downloads the backup archive from its source disk into a temporary local directory (using streaming to prevent memory issues). It then extracts the archive, using your password if provided.
2. **Locate SQL Dump**: It finds the `.sql` file inside the extracted contents.
3. **Wipe Database**: If `disableWipe()` was not called, the system drops all tables from the target database.
4. **Import Dump**: The system executes the SQL dump file against the target database using native shell redirection (Zero-Overhead).
5. **Cleanup & Events**: The temporary directory is deleted, and a `RestoreSucceeded` or `RestoreFailed` event is dispatched.

</details>
