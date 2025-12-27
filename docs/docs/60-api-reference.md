---
sidebar_position: 60
---

# API Reference

This page provides an exhaustive list of all available methods for the `Backup` and `Restorer` facades. Use this as a quick reference.

## `Backup` Facade Methods

These methods are available on `Aaix\LaravelEasyBackups\Facades\Backup`.

| Method | Description |
| :--- | :--- |
| `database(string $connection)` | **(Static)** Initiates a new backup process for a specific database connection. |
| `files()` | **(Static)** Initiates a new backup process for files and directories. |
| `includeFiles(array $files)` | Adds specific files to the backup archive. Expects an array of absolute paths. |
| `includeDirectories(array $dirs)` | Adds entire directories to the backup archive. Expects an array of absolute paths. |
| `includeStorage(?string $path)` | Helper to include the storage directory (default: `storage_path('app')`). |
| `includeEnv()` | Helper to include the `.env` file. |
| `saveTo(string $disk)` | Sets the filesystem disk where the backup will be stored (e.g., 'local', 's3'). |
| `setLocalStorageDir(string $path)` | Overrides the default local storage directory. |
| `setRemoteStorageDir(string $path)` | Overrides the default remote storage directory for the specified `saveTo` disk. |
| `maxRemoteBackups(int $count)` | Sets the number of backups to keep on the remote storage. Old backups will be deleted. |
| `maxLocalBackups(int $count)` | Sets the number of backups to keep on the local storage. |
| `keepLocal()` | If set, the local backup file will not be deleted after being uploaded to remote storage. |
| `compress()` | Compresses the final backup archive into a `.zip` or `.tar.gz` file. |
| `encryptWithPassword(string $pass)` | Encrypts the backup archive with a password. |
| `before(string $hookClass)` | FQCN of an invokable class to run before the backup job starts. |
| `after(string $hookClass)` | FQCN of an invokable class to run after the backup job successfully completes. |
| `notifyOnSuccess(string\|array $ch)` | Sends a notification on successful backup to the specified channel(s). |
| `notifyOnFailure(string\|array $ch)` | Sends a notification on failed backup to the specified channel(s). |
| `onQueue(string $queue)` | Specifies the queue to dispatch the backup job to. |
| `onConnection(string $connection)` | Specifies the queue connection to use. |
| `run()` | Executes the backup process by dispatching the job. |

## `Restorer` Facade Methods

These methods are available on `Aaix\LaravelEasyBackups\Facades\Restorer`.

| Method | Description |
| :--- | :--- |
| `database()` | **(Static)** Initiates a new restore process builder. |
| `toDatabase(string $connection)` | Specifies the target database connection to overwrite. **Required.** |
| `fromDisk(string $disk)` | The filesystem disk where the backup is located. |
| `fromDir(string $directory)` | The directory on the disk where the backup is located. |
| `fromPath(string $path)` | The path to the specific backup file on the disk. |
| `withPassword(string $password)` | The password to decrypt an encrypted backup archive. |
| `disableWipe()` | If called, the target database will not be wiped clean before the import. **Use with caution.** |
| `latest()` | Tells the Restorer to automatically find and use the latest backup file in the directory. |
| `onQueue(string $queue)` | The queue to dispatch the restore job to. |
| `onConnection(string $connection)` | The queue connection to use. |
| `run()` | Executes the restore process by dispatching the job. |
| `getRecentBackups(string $disk, ...)` | **(Static)** Helper to fetch a collection of recent backups for UI selection. |
