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

The package's service provider will be automatically registered. Next, you should publish the configuration file. While the default settings work out-of-the-box for most applications, publishing the config allows you to customize paths and defaults later.

```bash
php artisan vendor:publish --provider="Aaix\LaravelEasyBackups\EasyBackupsServiceProvider" --tag="config"
```

This will create a `config/easy-backups.php` file in your project.

## Setting up your backup command

This package is designed to be used within a customized, application-specific backup command to give you full control. So we start by creating a new command:

```bash
php artisan make:command Backup\\DatabaseBackupCommand
```

This will create a new command in `app/Console/Commands/Backup/DatabaseBackupCommand.php`.

Next, we use the package to set up the backup configuration.

```php
<?php

namespace App\Console\Commands\Backup;

use Illuminate\Console\Command;
use Aaix\LaravelEasyBackups\Facades\Backup;

class DatabaseBackupCommand extends Command
{
    protected $signature = 'backup:db:create';

    protected $description = 'Create a database backup';

    public function handle(): int
    {
        $this->info('Creating database backup...');

        // We use the 'database' static method to start a DB backup context
        Backup::database(config('database.default'))
             ->saveTo('local')
             ->compress()
             ->run();

        $this->info('Database backup created successfully.');

        return self::SUCCESS;
    }
}
```

### Let's break down what's happening here:

* `Backup::database(...)`: This initiates a new backup process specifically for the given database connection.
* `->saveTo('local')`: This specifies that the backup archive should be saved using the `local` disk driver, which usually corresponds to your application's `storage/app` directory.
* `->compress()`: This tells the package to create a compressed `.zip` (or `.tar.gz`) archive of the database dump.
* `->run()`: This starts the backup process.

That's it! You've just created your first backup. Now, let's dive deeper into creating and customizing your backups.
