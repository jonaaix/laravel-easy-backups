---
sidebar_position: 10
---

# Getting Started

Welcome to Laravel Easy Backups! This guide will walk you through the installation and how to perform your first database backup
in just a few minutes.

## Installation

First, install the package via Composer:

```bash
composer require aaix/laravel-easy-backups
```

The package's service provider will be automatically registered.

Next, you should publish the configuration file. While the default settings work out-of-the-box for most applications, publishing
the config allows you to customize things later.

```bash
php artisan vendor:publish --tag="config"
```

This will create a `config/easy-backups.php` file in your project.

## Setting up your backup command
This package intents to be used within a customized application-specific backup command.
So we start with creating a new backup command.
```bash
php artisan make:command Backup\\DatabaseBackupCommand
```

This will create a new command in `app/Console/Commands/Backup/DatabaseBackupCommand.php`

Next, we use the package to set up the backup configuration.

```php
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
    
        Backup::create()
            ->includeDatabases([config('database.default')])
            ->saveTo('local')
            ->compress()
            ->run();
            
        $this->info('Database backup created successfully.');
        
        return self::SUCCESS;
    }
}
```

### Let's break down what's happening here:

- `Backup::create()`: This initiates a new backup process.
- `->includeDatabases([...])`: Here, we specify which database connections to back up. We're using the default connection defined
  in your `config/database.php`.
- `->saveTo('local')`: This specifies that the backup archive should be saved using the `local` disk driver, which corresponds to
  your application's `storage/app` directory.
- `->compress()`: This tells the package to create a compressed `.zip` archive of the database dump.
- `->run()`: This starts the backup process.

That's it! You've just created your first backup.

Now, let's dive deeper into creating and customizing your backups.
