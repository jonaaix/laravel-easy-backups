# Laravel Easy Backups

A developer-first, fluent and flexible package for creating database backups in Laravel.

## Installation

```bash
composer require aaix/laravel-easy-backups
```

Publish the configuration file (optional):

```bash
php artisan vendor:publish --provider="Aaix\LaravelEasyBackups\EasyBackupsServiceProvider" --tag="config"
```

## Usage

Create a database backup with a simple, fluent API. The package automatically detects your database driver (MySQL, MariaDB, PostgreSQL, SQLite) and uses the correct tools.

### Basic Example

This will back up the `mysql` database, compress the backup, save them to the `s3_backups` disk, and keep the last 10 remote backups.

```php
use Aaix\LaravelEasyBackups\Facades\Backup;

Backup::databases(['mysql'])
    ->saveTo('s3_backups')
    ->compress()
    ->maxRemoteBackups(10)
    ->run();
```

### Advanced Example with Callbacks and Queueing

Run a backup on a specific connection and queue, keep local copies, and execute logic before and after the job.

```php
use Aaix\LaravelEasyBackups\Facades\Backup;
use Illuminate\Support\Facades\Log;

Backup::databases(['mysql'])
    ->saveTo('s3_backups')
    ->keepLocal()
    ->maxLocalBackups(3)
    ->maxRemoteBackups(50)
    ->before(fn(array $config) => Log::info('Starting backup...', $config))
    ->after(fn(array $config) => Log::info('Backup finished.', $config))
    ->onConnection('redis')
    ->onQueue('long-running-jobs')
    ->run();
```

### Overriding Storage Paths

You can override the default storage paths for a specific job.

```php
use Aaix\LaravelEasyBackups\Facades\Backup;

Backup::databases(['mysql', 'pgsql'])
    ->saveTo('s3_archive')
    ->setDatabaseLocalStorageDir('app/special_backups')
    ->setDatabaseRemoteStorageDir('project_x_backups/')
    ->run();
```
