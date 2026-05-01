---
name: easy-backups-development
description: Build database and file backups with Laravel Easy Backups — fluent Backup API, retention policies, encryption, notifications, and the easy-backups Artisan commands.
---

# Laravel Easy Backups Development

## When to use this skill

Use this skill when working with the `aaix/laravel-easy-backups` package — that is, when:

- Code imports or references `Aaix\LaravelEasyBackups\Facades\Backup` or `Aaix\LaravelEasyBackups\Facades\Restorer`.
- The user asks for backup/restore commands, retention policies, scheduled backups, or backup notifications.
- The user runs or asks about any `easy-backups[:*]` Artisan command.
- A custom Artisan command is being created to schedule backups (the recommended pattern).

Do **not** invoke for unrelated database/file operations or for Spatie Backup or other backup packages.

## Package conventions

- Namespace: `Aaix\LaravelEasyBackups`
- Service provider auto-registers the facade alias `Backup` and 5 commands (no manual registration needed).
- Config file (optional): `config/easy-backups.php` published via `php artisan vendor:publish --provider="Aaix\LaravelEasyBackups\EasyBackupsServiceProvider" --tag="config"`.
- Default disks: local copies on `local`, remote uploads on a disk named `backup` (override via config or `saveTo()`).
- Path layout (auto-generated, do **not** hand-build paths): `{env}/{type}/{driver}/{filename}`. Use `enableEnvPathPrefix(false)` to drop the env prefix.
- Recommended pattern: wrap each scheduled backup in its own dedicated Artisan command rather than calling `Backup::...` from a closure in routes/console.php — keeps backup logic version-controlled and reviewable.

## Artisan commands

| Command | Purpose |
| --- | --- |
| `easy-backups` | Interactive wizard (create or restore). Best for ad-hoc usage. |
| `easy-backups:db:create` | Create a database backup. Supports compression, encryption, retention, dry-run. |
| `easy-backups:db:restore` | Restore a database backup interactively or with `--latest`. |
| `easy-backups:db:list` | List backups on a disk with size, age, format. |
| `easy-backups:db:manage` | Interactive inspect/delete on local and remote disks. |

### `easy-backups:db:create` flag cheatsheet

```
--of-database=mysql           # connection name (defaults to default connection)
--to-disk=s3                  # remote disk override
--local                       # store ONLY locally — skip remote upload
--keep-local                  # keep local copy AFTER remote upload (no-op with --local)
--compress                    # force compression (.tar.gz / .zst)
--password=secret             # encrypt as .zip with password (implies --compress)
--name=pre-deploy             # filename suffix
--max-remote-backups=N        # retention by count on remote
--max-remote-days=N           # retention by age on remote (days)
--max-local-backups=N         # retention by count on local (only with --local or --keep-local)
--max-local-days=N            # retention by age on local (only with --local or --keep-local)
--exclude-tables=t1,t2        # drop entirely (no structure, no data)
--exclude-table-data=audit    # structure only, skip rows (sensitive tables)
--notify-mail-success=a@b     # email on success
--notify-mail-failure=a@b     # email on failure
--dry-run                     # print plan only, no dumps/uploads
```

## Fluent Backup API

Always start from `Backup::database($connection)` for database backups or `Backup::files()` for file/directory archives. The builder is immutable-style — chain everything before the final `->run()`.

### Database backup — minimal

```php
use Aaix\LaravelEasyBackups\Facades\Backup;

Backup::database('mysql')->compress()->run();
```

### Database backup — production pattern with retention

```php
Backup::database('mysql')
    ->saveTo('s3-backups')          // override default 'backup' disk
    ->compress()
    ->encryptWithPassword(config('app.backup_password'))
    ->maxRemoteBackups(30)
    ->maxRemoteDays(40)             // count + age caps combine
    ->notifyOnFailure('mail', 'ops@example.com')
    ->run();
```

### File backup

```php
Backup::files()
    ->setName('weekly-files')
    ->includeStorage()              // helper: storage_path('app')
    ->includeEnv()                  // helper: base_path('.env')
    ->includeDirectories([base_path('uploads')])
    ->compress()
    ->maxRemoteBackups(8)
    ->run();
```

## Retention semantics — IMPORTANT

Local-side retention (`maxLocalBackups()` / `maxLocalDays()` / `--max-local-*`) **only takes effect when there is a local copy after the run**, i.e.:

1. `onlyLocal()` / `--local` — no upload happens, local copy persists.
2. `saveTo(...)` (or default remote) **plus** `keepLocal()` / `--keep-local` — local copy survives the upload.

In the **default flow** (upload to remote without `keepLocal()`), the local file is deleted right after a successful upload, so `maxLocal*` has nothing to act on. Don't pair `maxRemote*` and `maxLocal*` without `keepLocal()` — it reads as a contradiction in code review.

```php
// ✅ Local-only with both retention axes
Backup::database('mysql')
    ->onlyLocal()
    ->maxLocalBackups(10)
    ->maxLocalDays(5)
    ->run();

// ✅ Upload AND keep a rolling local cache
Backup::database('mysql')
    ->saveTo('s3-backups')
    ->keepLocal()
    ->maxRemoteBackups(30)
    ->maxLocalBackups(3)
    ->run();
```

## Recommended scheduling pattern

Create a dedicated command per backup policy (daily DB, weekly files, etc.) instead of inlining `Backup::...` in `routes/console.php`. Schedule the command, not the facade.

```php
// app/Console/Commands/Backup/DailyDatabaseBackup.php
namespace App\Console\Commands\Backup;

use Aaix\LaravelEasyBackups\Facades\Backup;
use Illuminate\Console\Command;

class DailyDatabaseBackup extends Command
{
    protected $signature = 'app:backup:db:daily';
    protected $description = 'Daily compressed DB backup with 30/40 retention.';

    public function handle(): int
    {
        Backup::database(config('database.default'))
            ->compress()
            ->maxRemoteBackups(30)
            ->maxRemoteDays(40)
            ->notifyOnFailure('mail', config('app.ops_mail'))
            ->run();

        return self::SUCCESS;
    }
}
```

```php
// routes/console.php (Laravel 11+) or app/Console/Kernel.php (≤10)
use Illuminate\Support\Facades\Schedule;

Schedule::command('app:backup:db:daily')->dailyAt('02:30')->withoutOverlapping();
```

## Restore

Always inspect first via `easy-backups:db:list` before restoring. Restore is destructive: the target connection is wiped before import unless `disableWipe()` is called.

```bash
# Interactive selection from configured backup disk
php artisan easy-backups:db:restore

# Automated: pick the newest backup on the configured remote disk
php artisan easy-backups:db:restore --latest

# Pull the newest backup created in the 'production' env into local 'mysql_local'
php artisan easy-backups:db:restore --latest --source-env=production --to-database=mysql_local
```

Programmatic equivalent:

```php
use Aaix\LaravelEasyBackups\Facades\Restorer;

Restorer::database()
    ->fromDisk('s3-backups')
    ->latest()
    ->toDatabase('mysql_local')
    ->withPassword(config('app.backup_password'))
    ->run();
```

## Hooks and notifications

```php
Backup::database('mysql')
    ->before(\App\Backup\PutAppInMaintenance::class)   // invokable class
    ->after(\App\Backup\TakeAppOutOfMaintenance::class)
    ->notifyOnSuccess('mail', 'ops@example.com')
    ->notifyOnFailure('mail', 'ops@example.com')
    ->run();
```

Hooks must be FQCNs of invokable classes; they are resolved through the container (`app()->call(...)`), so constructor injection works.

## Sensitive data — table exclusions

```php
Backup::database('mysql')
    ->excludeTables(['cache', 'jobs', 'failed_jobs'])         // structure + data dropped
    ->excludeTableData(['users_audit', 'webhooks_log'])       // structure only, no rows
    ->run();
```

Defaults can also be set globally via `config('easy-backups.defaults.database.exclude_tables')` and `exclude_table_data`. Per-call values **merge** with config defaults, they don't replace them.

## Dry-run before scheduling

When introducing a new backup command, run with `--dry-run` (CLI) or `->dryRun()` (fluent) once to confirm:
- which connections will be dumped,
- the exact dumper command,
- the upload target,
- which retention rules will fire.

```bash
php artisan easy-backups:db:create --of-database=mysql --compress --max-remote-days=30 --dry-run
```

No files are written and no uploads happen in dry-run.

## Best-practice checklist

- ✅ One Artisan command per backup policy; schedule the command, not the facade.
- ✅ Always set retention (`maxRemoteBackups` / `maxRemoteDays`) on scheduled remote backups — otherwise the bucket grows forever.
- ✅ Pair `maxLocal*` only with `onlyLocal()` or `keepLocal()` — otherwise it's dead config.
- ✅ Use `encryptWithPassword()` for any backup leaving the host; never commit the password.
- ✅ Add `notifyOnFailure(...)` to scheduled backups so silent failures surface.
- ✅ Run `--dry-run` once when authoring a new backup command.
- ✅ Use `excludeTableData()` (not `excludeTables()`) for tables you still want to be able to recreate empty (e.g. analytics, audit logs).
- ❌ Don't hand-craft remote paths — let the package's `PathGenerator` produce `{env}/{type}/{driver}/...`.
- ❌ Don't call `Backup::run()` from inside HTTP request lifecycles (controllers, listeners on hot paths). Dispatch via `onQueue()` or run from a command.
