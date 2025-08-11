---
sidebar_position: 40
---

# Common Recipes

This page provides ready-to-use solutions for common backup scenarios. You can adapt these "recipes" for your own needs, for
example, in a custom Artisan command or a scheduled task.

## Daily Backup to S3 with Cleanup

This is a very common requirement: a daily, automated backup of your main database to a remote location like Amazon S3, ensuring
you only keep a limited number of recent backups.

**The Goal:** Create a daily backup of the `mysql` database, store it on S3, and keep only the 7 most recent backups.

```php
// app/Console/Commands/CreateDailyBackup.php

use Aaix\LaravelEasyBackups\Facades\Backup;
use Illuminate\Console\Command;

class CreateDailyBackup extends Command
{
    protected $signature = 'app:create-daily-backup';
    protected $description = 'Create a daily backup of the database and upload to S3.';

    public function handle(): int
    {
        $this->info('Starting daily backup...');

        Backup::create()
            ->includeDatabases(['mysql'])
            ->saveTo('s3')
            ->compress()
            ->maxRemoteBackups(7) // Keep the last 7 backups
            ->run();

        $this->info('Daily backup job dispatched successfully!');
        
        return self::SUCCESS;
    }
}
```

**To make this run daily, register the command in your `app/Console/Kernel.php`:**

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('app:create-daily-backup')->daily()->at('02:00');
}
```

## Backup User Uploads with the Database

If your users upload files (like avatars or documents), you need to back up those files along with your database.

**The Goal:** Back up the default database and the entire `storage/app/public` directory.

```php
use Aaix\LaravelEasyBackups\Facades\Backup;

Backup::create()
    ->includeDatabases([
        config('database.default')
    ])
    ->includeDirectories([
        storage_path('app/public')
    ])
    ->compress()
    ->saveTo('s3')
    ->run();
```

## Create Encrypted Backups

If your backup contains sensitive data, you should always encrypt it.

**The Goal:** Create a password-protected, encrypted backup.

```php
use Aaix\LaravelEasyBackups\Facades\Backup;

Backup::create()
    ->includeDatabases(['mysql'])
    ->encryptWithPassword(config('app.backup_password')) // Store password securely!
    ->compress()
    ->saveTo('s3')
    ->run();
```

**Important:** Store your backup password securely, for example, in your `.env` file and `config/app.php`. Never hard-code it.

To restore it, you must provide the same password:

```php
use Aaix\LaravelEasyBackups\Facades\Restorer;

Restorer::create()
    ->fromDisk('s3')
    ->fromPath('path/to/encrypted-backup.zip')
    ->withPassword(config('app.backup_password'))
    ->run();
```

## Email Notifications for Failed Backups

Getting notified immediately when a backup fails is crucial. This recipe shows how to set up email notifications for failures.

**The Goal:** Send an email to a specific address only when a backup process fails.

```php
use Aaix\LaravelEasyBackups\Facades\Backup;

// Assumes your application's mail driver is configured

Backup::create()
    ->includeDatabases(['mysql'])
    ->saveTo('s3')
    ->compress()
    ->notifyOnFailure('mail', 'monitoring@example.com') // The recipient's email address
    ->run();
```

This uses Laravel's built-in notification system. As long as your application's mail driver is correctly configured (e.g., in your
`.env` file and `config/mail.php`), it will work out-of-the-box.
