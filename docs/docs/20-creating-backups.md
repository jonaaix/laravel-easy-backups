---
sidebar_position: 20
---

# Creating Backups

This guide covers the most common ways to create and customize your backups using the fluent API.

## The Basics: Backing up a Database

The most fundamental use case is backing up a single database. The `Backup` facade provides a clean, readable way to define this task. You can run this code from anywhere in your application, like a controller, a scheduled command, or a queue job.

Let's create a backup of the primary database, compress it, and store it on the default local disk.

```php
use Aaix\LaravelEasyBackups\Facades\Backup;

Backup::create()
    ->includeDatabases([config('database.default')])
    ->compress()
    ->saveTo('local')
    ->run();
```

This example is straightforward, but you can easily chain more methods to build a more specific backup tailored to your needs.

## Managing Backup Retention with Cleanup Policies

A core feature of this package is the ability to automatically clean up old backups. This prevents your storage from filling up with outdated files. You can define how many backups to keep for local and remote storage separately.

```php
use Aaix\LaravelEasyBackups\Facades\Backup;

Backup::create()
    ->includeDatabases(['app_data', 'translations'])
    ->saveTo('s3')
    ->compress()
    ->encryptWithPassword('secret')
    ->maxRemoteBackups(7) // Keep the last 7 backups on S3
    ->maxLocalBackups(3)  // Keep the last 3 backups locally
    ->run();
```
- `->saveTo('s3')`: This method instructs the package to store the backup on the `s3` disk. Make sure your `s3` disk is correctly configured in `config/filesystems.php`.
- `->compress()`: This method instructs the package to compress the backup archive before storing it.
- `->encryptWithPassword('secret')`: This method instructs the package to encrypt the backup archive with the specified password.
- `->maxRemoteBackups(7)`: After a successful backup to a remote disk (like `s3`), this will delete the oldest backups, ensuring only the 7 most recent ones are kept.
- `->maxLocalBackups(3)`: Similarly, this manages the number of backups on your local filesystem.

## Including Files and Directories

Often, you need to back up more than just the database. You might have user-uploaded content, logs, or other important files. The package makes it easy to include them in the same backup archive.

This is perfect for backing up user uploads, generated reports, or other important files.

```php
use Aaix\LaravelEasyBackups\Facades\Backup;

Backup::create()
    // 1. Specify the database connection to back up
    ->includeDatabases(['pgsql'])

    // 2. Also include the user uploads directory
    ->includeDirectories([storage_path('app/public')])

    // 3. Store the final archive on the 's3' disk
    ->saveTo('s3')

    // 4. Ensure the backup is compressed
    ->compress()
    
    // 5. Execute the backup process
    ->run();
```

## A More Advanced Example: Backing up to the Cloud

Let's imagine a more complex scenario where you want to back up your main PostgreSQL database and your user-generated content to an Amazon S3 bucket, and dispatch the job to a specific queue.

```php
use Aaix\LaravelEasyBackups\Facades\Backup;

Backup::create()
    // 1. Specify the database connection to back up
    ->includeDatabases(['pgsql'])

    // 2. Also include the user uploads directory
    ->includeDirectories([storage_path('app/public')])

    // 3. Store the final archive on the 's3' disk
    ->saveTo('s3')

    // 4. Ensure the backup is compressed
    ->compress()

    // 5. Dispatch the job to a specific queue
    ->onConnection('redis')
    ->onQueue('backups')

    // 6. Execute the backup process
    ->run();
```

### Contextual Explanation

- `->includeDatabases(['pgsql'])`: We explicitly tell the package to back up the database configured with the `pgsql` connection name in `config/database.php`.
- `->includeDirectories([...])`: In addition to the database, we're including an entire directory.
- `->saveTo('s3')`: This method is key for off-site backups. It instructs the package to use one of your configured filesystem disks. Make sure your `s3` disk is correctly configured in `config/filesystems.php`.
- `->onConnection('redis')`: This method is key for off-site backups. It instructs the package to use a specific queue connection. Make sure your `redis` connection is correctly configured in `config/queue.php`.   
- `->onQueue('backups')`: For long-running backups, it's wise to use a dedicated queue to avoid interfering with other application tasks. This sends the `BackupJob` to the `backups` queue.

<details>
<summary>Under The Hood: The Backup Workflow</summary>

When you call `run()`, a `BackupJob` is dispatched. Here’s a summary of what happens inside that job:

1.  **Temporary Directory**: A unique, temporary working directory is created on the local filesystem.
2.  **Dumping Databases**: For each database connection specified, a driver-specific `Dumper` creates a `.sql` dump file in the temporary directory.
3.  **Creating the Archive**: All SQL dumps and any extra files/directories you included are added to a single `.zip` archive. If you used `encryptWithPassword()`, this is when the archive is encrypted.
4.  **Verification**: The package performs a quick integrity check on the zip file to ensure it's not corrupted or empty.
5.  **Storage**: The verified archive is moved to its final local destination or uploaded to the specified remote disk (like S3).
6.  **Cleanup**: The job cleans up old backups based on your retention policy (e.g., `maxRemoteBackups()`). If the backup was uploaded and `keepLocal()` was not used, the local copy is deleted.
7.  **Notifications & Events**: Finally, the job dispatches events (`BackupSucceeded`, `BackupFailed`) and sends notifications if configured.

</details>
