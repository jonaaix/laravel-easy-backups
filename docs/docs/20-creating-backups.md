---
sidebar_position: 20
---

# Creating Backups

This guide covers the most common ways to create backups. You can either use the ready-to-go Artisan commands or the Fluent API for more granular control.

## The Quickest Way: Using the CLI

For most standard use cases, you don't need to write any code. The package includes a robust Artisan command that handles compression, encryption, and storage automatically.

**Create a standard remote backup:**
```bash
php artisan easy-backups:db:create --compress
```

**Create a local-only snapshot (e.g. before a deploy):**

```bash
php artisan easy-backups:db:create --local --name="pre-deploy"
```

---

## The Flexible Way: Using the Fluent API

If you need to integrate backups into your own scheduled commands, jobs, or workflows, the `Backup` facade provides a clean, readable API.

### The Basics: Backing up a Database

The most fundamental use case is backing up a single database.

Let's create a backup of the primary database, compress it, and store it **only** on the local disk (skipping any remote upload).

```php
use Aaix\LaravelEasyBackups\Facades\Backup;

Backup::database(config('database.default'))
    ->compress()
    ->onlyLocal()
    ->run();
```

This example is straightforward, but you can easily chain more methods to build a more specific backup tailored to your needs.

## Managing Backup Retention with Cleanup Policies

A core feature of this package is the ability to automatically clean up old backups. This prevents your storage from filling up with outdated files. You can define retention for local and remote storage separately.

:::info When local retention applies
The `maxLocalBackups()` / `maxLocalDays()` options only have an effect when there actually is a local copy after the run — i.e. when you use `onlyLocal()`, or when you upload to a remote disk *and* call `keepLocal()`. In the default flow (upload to remote without `keepLocal()`), the local file is deleted right after the upload, so local retention has nothing to act on.
:::

```php
use Aaix\LaravelEasyBackups\Facades\Backup;

// Remote upload with retention on the remote disk only.
// Local copy is deleted right after upload — no local retention needed.
Backup::database('app_data')
    ->saveTo('backup') // Optional: Defaults to the 'remote_disk' config
    ->compress()
    ->encryptWithPassword('secret')
    ->maxRemoteBackups(7) // Keep the last 7 backups on the remote disk
    ->maxRemoteDays(40)   // Drop remote backups older than 40 days
    ->run();

// Remote upload AND keep local copies — apply retention on both sides.
Backup::database('app_data')
    ->saveTo('backup')
    ->keepLocal()           // Local copy is preserved after upload
    ->maxRemoteBackups(7)
    ->maxLocalBackups(3)
    ->run();

// Local-only with combined count- and age-based retention.
Backup::database('app_data')
    ->onlyLocal()
    ->maxLocalBackups(10) // Keep at most 10 local backups...
    ->maxLocalDays(5)     // ...and drop anything older than 5 days
    ->run();
```

* `->saveTo('my-backup')`: Stores the backup on the specified disk. If omitted, the default disk from `config/easy-backups.php` is used.
* `->onlyLocal()`: Forces the backup to be stored only locally, skipping any remote upload.
* `->keepLocal()`: When uploading to a remote disk, the local copy is **not** deleted afterwards. Required if you want local retention to take effect alongside remote upload.
* `->compress()`: Compresses the backup archive before storing it.
* `->encryptWithPassword('secret')`: Encrypts the backup archive.
* `->maxRemoteBackups(7)`: After a successful upload, deletes the oldest remote backups so only the 7 most recent remain.
* `->maxRemoteDays(40)`: Deletes remote backups older than 40 days.
* `->maxLocalBackups(3)`: Keeps at most 3 backups on the local filesystem (only applies in `onlyLocal()` or `keepLocal()` flows).
* `->maxLocalDays(7)`: Deletes local backups older than 7 days. Can be combined with `maxLocalBackups()` to enforce both a count and an age cap.

:::tip Automatic Path Generation
You don't need to specify folder paths manually. The package uses a smart `PathGenerator` to automatically organize your backups:
`{environment}/{type}/{driver}/{filename}`.

**Customizing Paths:**

* **Disable Env Prefix:** Use `->enableEnvPathPrefix(false)` to remove the `{environment}` folder (e.g., `production/`).
* **Custom Base Dir:** Use `->setRemoteStorageDir('custom-dir')` to replace `{type}/{driver}` with your own folder.

**Examples:**

* Default: `production/db-backups/mysql/db-dump_...sql`
* With `setRemoteStorageDir('daily')`: `production/daily/db-dump_...sql`
* With `setRemoteStorageDir('daily')` AND `enableEnvPathPrefix(false)`: `daily/db-dump_...sql`
  :::

## Backing up Files and Directories

Aside from databases, you often need to back up user-uploaded content, logs, or other assets.

:::danger Single Responsibility
A backup job can handle **either** a database **or** files, but not both simultaneously. If you need to back up both, you must dispatch two separate jobs.
:::

### Using `Backup::files()`

To back up specific files or directories, use the `files()` entry point. You can chain multiple inclusion methods.

```php
use Aaix\LaravelEasyBackups\Facades\Backup;

Backup::files()
    ->includeDirectories([storage_path('app/public')])
    ->includeFiles([base_path('.env')])
    ->saveTo('backup')
    ->run();
```

### Shortcuts for Storage and Env

Since backing up the storage directory or the environment file are common tasks, there are dedicated helper methods available on the file backup instance.

```php
use Aaix\LaravelEasyBackups\Facades\Backup;

// Backs up the entire storage_path('app') directory AND the .env file
Backup::files()
    ->includeStorage() // defaults to storage_path('app')
    ->includeEnv()     // defaults to base_path('.env')
    ->run(); // Automatically uses the default remote disk
```

## A More Advanced Example: Production Database Backup

Let's imagine a complex scenario where you want to back up your main PostgreSQL database to an Amazon S3 bucket (configured as `backup`), encrypt it for security, and dispatch the job to a specific queue to avoid blocking user requests.

```php
use Aaix\LaravelEasyBackups\Facades\Backup;

Backup::database('pgsql')
    // 1. Store the final archive on the 'backup' disk
    ->saveTo('backup')

    // 2. Ensure the backup is compressed and encrypted
    ->encryptWithPassword(config('app.backup_password'))

    // 3. Dispatch the job to a specific queue connection and queue
    ->onConnection('redis')
    ->onQueue('backups')

    // 4. Keep strict retention policies
    ->maxRemoteBackups(30) // Keep one month of daily backups

    // 5. Execute the backup process
    ->run();
```

### Contextual Explanation

* `Backup::database('pgsql')`: We initiate a backup specifically for the `pgsql` connection defined in `config/database.php`.
* `->saveTo('backup')`: This method is key for off-site backups.
* `->encryptWithPassword(...)`: Secures your data at rest using Zip encryption.
* `->onConnection('redis')`: It instructs the package to use a specific queue connection.
* `->onQueue('backups')`: For long-running backups, it's wise to use a dedicated queue to avoid interfering with other application tasks.

<details>
<summary>Under The Hood: The Backup Workflow</summary>

When you call `run()`, a `BackupJob` is dispatched. Here’s a summary of what happens inside that job:

1. **Temporary Directory**: A unique, temporary working directory is created (configured via `temp_path`).
2. **Artifact Generation**:

* If initialized with `database()`, a driver-specific `Dumper` creates a `.sql` dump.
* If initialized with `files()`, the specified files are gathered.

3. **Creating the Archive**: The artifacts are added to a single archive (ZIP or TAR). If you used `encryptWithPassword()`, the archive is encrypted.
4. **Verification**: The package performs a quick integrity check on the archive to ensure it's not corrupted or empty.
5. **Storage**: The verified archive is moved to its final local destination. If a remote disk is configured, it is uploaded using **memory-safe streaming** (ideal for large files).
6. **Cleanup**: The job cleans up old backups based on your retention policy (e.g., `maxRemoteBackups()`). If `keepLocal()` was not used, the local copy is deleted after upload.
7. **Notifications & Events**: Finally, the job dispatches events (`BackupSucceeded`, `BackupFailed`) and sends notifications if configured.

</details>
