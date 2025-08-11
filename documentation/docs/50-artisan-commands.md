---
sidebar_position: 50
---

# Artisan Commands Reference

The package provides a set of Artisan commands to make backing up and restoring from the command line a breeze.

## `backup:run`

This command allows you to create a new backup.

```bash
php artisan backup:run
```

By default, it backs up your default database. You can customize the backup using the available options.

**Options:**

-   `--db=<database>`: Specify one or more database connections to back up. You can use the option multiple times (e.g., `--db=mysql --db=pgsql`).
-   `--dir=<directory>`: Specify one or more directories to include in the backup. Provide absolute paths. Can be used multiple times.
-   `--file=<file>`: Specify one or more files to include in the backup. Provide absolute paths. Can be used multiple times.
-   `--disk=<disk>`: The filesystem disk to store the backup on (e.g., `s3`). Overrides the default.
-   `--password=<password>`: Encrypt the backup with the given password.
-   `--keep-local`: Keep the local backup file even after uploading it to a remote disk.

**Example:**

```bash
php artisan backup:run --db=mysql --dir=/var/www/my-app/storage/app/public --disk=s3
```

## `backup:list`

This command lists all available backups on a specified disk.

```bash
php artisan backup:list
```

It will show a table with the backup filename, size, disk, and creation date.

**Options:**

-   `--disk=<disk>`: The disk to list backups from. Defaults to your application's default filesystem disk.

## `backup:restore`

This command restores a database from a backup. You can run it without arguments for an interactive mode.

```bash
php artisan backup:restore
```

When run interactively, it will present you with a list of available backups to choose from.

**Arguments:**

-   `filename` (optional): The name of the backup file to restore.

**Options:**

-   `--disk=<disk>`: The disk where the backup is stored. Defaults to the default filesystem disk.
-   `--database=<database>`: The database connection to restore to. Defaults to your default database connection.
-   `--password=<password>`: The password for an encrypted backup.
-   `--no-wipe`: **Use with caution!** This option disables the default behavior of wiping the database clean before the restore process begins.

**Example:**

```bash
php artisan backup:restore my-backup-file.zip --disk=s3 --database=testing_db
```
