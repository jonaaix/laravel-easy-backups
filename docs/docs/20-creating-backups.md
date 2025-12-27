---
sidebar_position: 20
---

# Creating Backups

This guide covers the most common ways to create and customize your backups using the fluent API.

## The Basics: Backing up a Database

The most fundamental use case is backing up a single database. The `Backup` facade provides a clean, readable way to define this task using the `database()` static method.

Let's create a backup of the primary database, compress it, and store it on the default local disk.

```php
use Aaix\LaravelEasyBackups\Facades\Backup;

Backup::database(config('database.default'))
    ->compress()
    ->saveTo('local')
    ->run();
```

This example is straightforward, but you can easily chain more methods to build a more specific backup tailored to your needs.

## Managing Backup Retention with Cleanup Policies

A core feature of this package is the ability to automatically clean up old backups. This prevents your storage from filling up with outdated files. You can define how many backups to keep for local and remote storage separately.

```php
use Aaix\LaravelEasyBackups\Facades\Backup;

Backup::database('app_data')
    ->saveTo('s3')
    ->compress()
    ->encryptWithPassword('secret')
    ->maxRemoteBackups(7) // Keep the last 7 backups on S3
    ->maxLocalBackups(3)  // Keep the last 3 backups locally
    ->run();
```

* `->saveTo('s3')`: This method instructs the package to store the backup on the `s3` disk. Make sure your `s3` disk is correctly configured in `config/filesystems.php`.
* `->compress()`: This method instructs the package to compress the backup archive before storing it.
* `->encryptWithPassword('secret')`: This method instructs the package to encrypt the backup archive with the specified password.
* `->maxRemoteBackups(7)`: After a successful backup to a remote disk (like `s3`), this will delete the oldest backups, ensuring only the 7 most recent ones are kept.
* `->maxLocalBackups(3)`: Similarly, this manages the number of backups on your local filesystem.

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
    ->saveTo('s3')
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
    ->saveTo('s3')
    ->run();
```

## A More Advanced Example: Production Database Backup

Let's imagine a complex scenario where you want to back up your main PostgreSQL database to an Amazon S3 bucket, encrypt it for security, and dispatch the job to a specific queue to avoid blocking user requests.

```php
use Aaix\LaravelEasyBackups\Facades\Backup;

Backup::database('pgsql')
    // 1. Store the final archive on the 's3' disk
    ->saveTo('s3')

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
* `->saveTo('s3')`: This method is key for off-site backups. It instructs the package to use one of your configured filesystem disks.
* `->encryptWithPassword(...)`: Secures your data at rest using Zip encryption.
* `->onConnection('redis')`: It instructs the package to use a specific queue connection.
* `->onQueue('backups')`: For long-running backups, it's wise to use a dedicated queue to avoid interfering with other application tasks.

<details>
<summary>Under The Hood: The Backup Workflow</summary>

When you call `run()`, a `BackupJob` is dispatched. Here’s a summary of what happens inside that job:

1. **Temporary Directory**: A unique, temporary working directory is created on the local filesystem.
2. **Artifact Generation**:
* If initialized with `database()`, a driver-specific `Dumper` creates a `.sql` dump.
* If initialized with `files()`, the specified files are gathered.


3. **Creating the Archive**: The artifacts are added to a single archive (ZIP or TAR). If you used `encryptWithPassword()`, the archive is encrypted.
4. **Verification**: The package performs a quick integrity check on the archive to ensure it's not corrupted or empty.
5. **Storage**: The verified archive is moved to its final local destination or uploaded to the specified remote disk (like S3).
6. **Cleanup**: The job cleans up old backups based on your retention policy (e.g., `maxRemoteBackups()`). If the backup was uploaded and `keepLocal()` was not used, the local copy is deleted.
7. **Notifications & Events**: Finally, the job dispatches events (`BackupSucceeded`, `BackupFailed`) and sends notifications if configured.

</details>
