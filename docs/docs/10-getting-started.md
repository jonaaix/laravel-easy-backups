---
sidebar_position: 10
---

# Getting Started

Welcome to Laravel Easy Backups! This guide will walk you through the installation and how to perform your first database backup in just a few minutes.

## Installation

First, install the package via Composer:

```bash
composer require aaix/laravel-easy-backups
```

The package's service provider will be automatically registered. Next, you can optionally publish the configuration file if you
want to customize paths and defaults. The default settings work out-of-the-box for most applications.

```bash
php artisan vendor:publish --provider="Aaix\LaravelEasyBackups\EasyBackupsServiceProvider" --tag="config"
```

This will create a `config/easy-backups.php` file in your project.

## Quickstart: Creating a Database Backup

For standard database backups, you don't need to write any code. The package comes with a ready-to-use Artisan command.

To create a backup of your default database and store it locally:

```bash
php artisan easy-backups:db:create
```

To create a compressed backup and upload it to your configured remote disk (default: `s3-backup`):

```bash
php artisan easy-backups:db:create --compress --to-disk=s3-backup
```

You can verify the created backup using the restore command:

```bash
php artisan easy-backups:db:restore
```

## Advanced: Create your own backup command

While the included commands are great for quick tasks, you might want to create a custom command to handle complex retention policies, notifications, or specific file inclusions.

Start by creating a new command:

```bash
php artisan make:command Backup\\DailyBackupCommand
```

Then, use the fluent API to define your backup logic:

```php
<?php

namespace App\Console\Commands\Backup;

use Illuminate\Console\Command;
use Aaix\LaravelEasyBackups\Facades\Backup;

class DailyBackupCommand extends Command
{
    protected $signature = 'app:backup:daily';
    protected $description = 'Create a daily backup';

    public function handle(): int
    {
        $this->info('Starting backup...');

        Backup::database('mysql')
             ->saveTo('s3-backup')
             ->compress()
             ->run();

        $this->info('Backup created successfully.');

        return self::SUCCESS;
    }
}
```

This is just the beginning. For more details on customization, handling files, and retention policies, check out the [Creating Backups](https://www.google.com/search?q=./creating-backups) guide.
