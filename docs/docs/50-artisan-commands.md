---
sidebar_position: 50
---

# Included Artisan Commands

The package includes a set of basic quickstart Artisan commands.

## `aaix:backup:db:create`

Creates a simple database backup. This command is intended for manual triggers or as a basic template for creating your own, more advanced backup commands.

```bash
php artisan aaix:backup:db:create {--of-database=} {--to-disk=} {--compress} {--password=}
```

**Options**

| Option | Description | Default Behavior |
| --- | --- | --- |
| `--of-database` | The database connection name to back up. | Defaults to your application's default database connection. |
| `--to-disk` | The filesystem disk to store the backup on. | Defaults to `'local'`. |
| `--compress` | A flag to compress the final backup into a `.zip` archive. | If omitted, an uncompressed `.sql` file is stored. |
| `--password` | The password to encrypt the backup archive. Using this option implicitly enables compression. | The backup is not encrypted. |

### Usage Examples

**Create a simple backup of the default database:**

```bash
# Creates an uncompressed backup of the default DB on the 'local' disk
php artisan aaix:backup:db:create
```

**Create a compressed backup of a specific database on S3:**

```bash
# Creates a compressed backup of the 'pgsql' database on the 's3' disk
php artisan aaix:backup:db:create --of-database=pgsql --to-disk=s3 --compress
```

**Create an encrypted backup of the default database:**

```bash
# Creates a compressed, encrypted backup on the 'local' disk
php artisan aaix:backup:db:create --password="your-secret-password"
```

---

## `aaix:backup:db:restore`

Restores a database from a backup. This command is a powerful tool for development and can be run in two modes:

1. **Interactive Mode (Default):** If run without the `--latest` flag, it will present an interactive prompt allowing you to select a backup from a list of the 30 most recent files on the specified disk.
2. **Automated Mode:** When the `--latest` flag is used, it will automatically find and restore the most recent backup, which is ideal for scripting.

```bash
php artisan aaix:backup:db:restore {--latest} {--from-disk=} {--to-database=} {--dir=} {--password=}
```

**Options**

| Option | Description | Default Behavior |
| --- | --- | --- |
| `--from-disk` | The filesystem disk where the backup is stored (e.g., `s3`). | Defaults to `'local'`. |
| `--to-database` | The database connection to restore to (as defined in `config/database.php`). | Defaults to your application's default database connection. |
| `--dir` | A specific directory on the disk to search for backups. | Defaults to the path configured in `easy-backups.php`. |
| `--latest` | A flag to restore the latest available backup without prompting the user. | If omitted, the command runs in interactive mode. |
| `--password` | The password to decrypt an encrypted backup archive. | Only required for encrypted backups. |

### Usage Examples

**Interactive restore from the local disk:**

```bash
# Starts an interactive prompt to select a backup from the 'local' disk
php artisan aaix:backup:db:restore
```

**Automated restore of the latest backup from S3:**

```bash
# Automatically finds and restores the latest backup from the 's3' disk
php artisan aaix:backup:db:restore --latest --from-disk=s3
```

**Restore the latest backup from a specific subdirectory on S3:**

```bash
# Looks inside the 'mysql-daily' folder on S3 for the newest file
php artisan aaix:backup:db:restore --latest --from-disk=s3 --dir=mysql-daily
```

**Interactive restore of an encrypted backup to a specific database:**

```bash
# Interactively select an encrypted backup from 's3' and restore it to the 'testing_db' connection
php artisan aaix:backup:db:restore --from-disk=s3 --to-database=testing_db --password="your-secret-password"
```
