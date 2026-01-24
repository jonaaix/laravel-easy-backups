---
sidebar_position: 50
---

# Included Artisan Commands

The package includes two robust commands for managing database backups directly from the CLI.

## `easy-backups:db:create`

Creates a new atomic database backup.

```bash
php artisan easy-backups:db:create {--of-database=} {--to-disk=} {--compress} {--password=} {--name=} {--max-remote-backups=} {--max-remote-days=} {--local}
```

**Options**

| Option | Description | Default Behavior |
| --- | --- | --- |
| `--of-database` | The database connection name to back up. | Defaults to your application's default connection. |
| `--to-disk` | The filesystem disk to store the backup on. | Defaults to `backup` (or configured remote disk). |
| `--compress` | Force compression into a `.zip` or `.tar.gz` archive. | If omitted, behavior depends on config. |
| `--password` | Encrypt the backup with this password. Implies compression. | No encryption. |
| `--name` | A custom suffix for the filename. |  |
| `--max-remote-backups` | Number of backups to keep on the remote disk. | No cleanup is performed. |
| `--max-remote-days` | Delete backups older than N days on remote. | No cleanup is performed. |
| `--local` | Store the backup **only** on the local disk. | Uploads to remote disk. |

### Usage Examples

**Standard backup to Remote Storage (Default):**

```bash
php artisan easy-backups:db:create --compress
```

**Local-only snapshot with a name:**

```bash
php artisan easy-backups:db:create --local --name="pre-migration"
```

**Backup with retention policy (keep last 10):**

```bash
php artisan easy-backups:db:create --max-remote-backups=10
```

**Backup with age-based retention (keep 30 days):**

```bash
php artisan easy-backups:db:create --max-remote-days=30
```

---

## `easy-backups:db:restore`

Restores a database from a backup. Runs in interactive mode by default.

```bash
php artisan easy-backups:db:restore {--latest} {--from-disk=} {--to-database=} {--source-env=} {--password=} {--local}
```

**Options**

| Option | Description | Default Behavior |
| --- | --- | --- |
| `--from-disk` | The filesystem disk where the backup is stored. | Defaults to `backup`. |
| `--to-database` | The target database connection to overwrite. | Defaults to default connection. |
| `--source-env` | The environment to pull backups from (e.g., `production`). | Defaults to current environment. |
| `--latest` | Restore the latest backup immediately without prompting. | Runs interactive selection. |
| `--password` | Password for encrypted backups. |  |
| `--local` | Force using the local disk as source. | Uses remote disk. |

### Usage Examples

**Interactive restore:**

```bash
php artisan easy-backups:db:restore
```

**Restore latest backup (Automated):**

```bash
php artisan easy-backups:db:restore --latest
```

**Restore a backup from Production environment:**

```bash
php artisan easy-backups:db:restore --source-env=production
```

**Restore from local storage:**

```bash
php artisan easy-backups:db:restore --local
```
