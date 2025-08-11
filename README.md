# Laravel Easy Backups

A developer-first, fluent and flexible package for creating database backups in Laravel.

---

## Installation

Install the package via Composer:

```bash
composer require aaix/laravel-easy-backups
```

Next, publish the configuration file. This is optional but recommended.

```bash
php artisan vendor:publish --provider="Aaix\LaravelEasyBackups\EasyBackupsServiceProvider" --tag="config"
```

## A Quick Look

Laravel Easy Backups provides a fluent, chainable API to define your backup workflows directly in your Laravel application.

Here's a quick example of how to back up your primary database to an S3 disk while ensuring only the 10 most recent backups are
kept.

```php
use Aaix\LaravelEasyBackups\Facades\Backup;

Backup::create()
    ->includeDatabases([config('database.default')])
    ->saveTo('s3')
    ->compress()
    ->maxRemoteBackups(10)
    ->run();
```

### Documentation

For the full documentation, including advanced features, common recipes, and detailed guides, please visit our full [documentation
website](https://jonaaix.github.io/laravel-easy-backups).
