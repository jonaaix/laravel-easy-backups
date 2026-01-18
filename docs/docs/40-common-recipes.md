---
sidebar_position: 40
---

# Common Recipes

This page provides ready-to-use solutions for common backup scenarios.

## Daily Backup to S3 with Cleanup

To create a daily automated backup of your main database to a remote location (e.g. S3) and keep only the 7 most recent backups, you can schedule the included Artisan command directly.

**In `routes/console.php`:**

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('easy-backups:db:create --compress --to-disk=s3-backup --max-remote-backups=7')
    ->daily()
    ->at('02:00');
```

Alternatively, if you prefer a custom command class using the Facade:

```php
Backup::database('mysql')
    ->saveTo('s3-backup')
    ->compress()
    ->maxRemoteBackups(7)
    ->run();
```

## Backup User Uploads

If you need to back up user-uploaded files (e.g. `storage/app/public`) alongside your database, dispatch a separate job for the files.

:::info Single Responsibility
A backup job handles **either** a database **or** files, not both simultaneously.
:::

```php
use Aaix\LaravelEasyBackups\Facades\Backup;

// 1. Backup the Database
Backup::database(config('database.default'))
    ->saveTo('s3-backup')
    ->compress()
    ->run();

// 2. Backup the Files
Backup::files()
    ->includeDirectories([
        storage_path('app/public')
    ])
    ->saveTo('s3-backup')
    ->run();
```

## Create Encrypted Backups

For sensitive data, creating an encrypted backup is recommended.

**Creating the backup:**

```php
use Aaix\LaravelEasyBackups\Facades\Backup;

Backup::database('mysql')
    ->encryptWithPassword(config('app.backup_password'))
    ->saveTo('s3-backup')
    ->run();
```

**Restoring the backup:**

To restore an encrypted backup, you must provide the password.

```php
use Aaix\LaravelEasyBackups\Facades\Restorer;

Restorer::database()
    ->fromDisk('s3-backup')
    ->toDatabase('mysql')
    ->latest()
    ->withPassword(config('app.backup_password'))
    ->run();
```

## Email Notifications

To receive alerts when a backup process fails (or succeeds), configure the notification channels.

```php
use Aaix\LaravelEasyBackups\Facades\Backup;

Backup::database('mysql')
    ->saveTo('s3-backup')
    ->compress()
    // Requires your mail driver to be configured
    ->notifyOnFailure('mail', 'monitoring@example.com') 
    ->run();
```
