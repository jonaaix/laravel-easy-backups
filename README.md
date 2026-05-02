<p align="center">
  <a href="https://github.com/jonaaix/laravel-easy-backups">
    <img src="https://jonaaix.github.io/laravel-easy-backups/img/logo2.png" alt="Laravel Easy Backups Logo" width="120">
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

**The Easy Way (CLI)**

Use the **interactive wizard** to create or restore backups without remembering flags:

```bash
php artisan easy-backups
```

Or use the robust **direct commands** for cronjobs, scripts, and CI/CD:

```bash
php artisan easy-backups:db:create --compress
php artisan easy-backups:db:restore
```

**The Flexible Way (Fluent API)**

Define custom backup workflows directly in your code using the chainable API:

```php
use Aaix\LaravelEasyBackups\Facades\Backup;

Backup::database(config('database.default'))
    ->saveTo('backup')
    ->compress()
    ->maxRemoteBackups(10)
    ->run();
```

### Documentation

For the full documentation, including advanced features, common recipes, and detailed guides, please visit our full [documentation
website](https://jonaaix.github.io/laravel-easy-backups).
