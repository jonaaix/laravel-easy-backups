<p align="center">
  <a href="https://github.com/jonaaix/laravel-easy-backups">
    <img src="https://raw.githubusercontent.com/jonaaix/laravel-easy-backups/main/docs/static/img/logo2.png" alt="Laravel Easy Backups Logo" width="200">
  </a>
</p>

<h1 align="center">Laravel Easy Backups</h1>

<p align="center">
A developer-first, fluent and flexible package for creating backups in Laravel.
</p>

<p align="center">
  <a href="https://packagist.org/packages/aaix/laravel-easy-backups"><img src="https://img.shields.io/packagist/v/aaix/laravel-easy-backups.svg?style=flat-square" alt="Latest Version on Packagist"></a>
  <a href="https://packagist.org/packages/aaix/laravel-easy-backups"><img src="https://img.shields.io/packagist/dt/aaix/laravel-easy-backups.svg?style=flat-square" alt="Total Downloads"></a>
  <a href="https://github.com/jonaaix/laravel-easy-backups/actions/workflows/tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/jonaaix/laravel-easy-backups/tests.yml?branch=main&label=tests&style=flat-square" alt="GitHub Actions"></a>
  <a href="https://github.com/jonaaix/laravel-easy-backups/blob/main/LICENSE.md"><img src="https://img.shields.io/packagist/l/aaix/laravel-easy-backups.svg?style=flat-square" alt="License"></a>
</p>

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
