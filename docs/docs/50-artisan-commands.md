---
sidebar_position: 50
---

# Included Artisan Commands

The package includes two robust commands for managing database backups directly from the CLI.

## `easy-backups:db:create`

Creates a new atomic database backup.

```bash
php artisan easy-backups:db:create {--of-database=} {--to-disk=} {--compress} {--password=} {--name=} {--max-remote-backups=} {--max-remote-days=} {--max-local-backups=} {--max-local-days=} {--local} {--keep-local}
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
| `--max-local-backups` | Number of backups to keep on the local disk. | No cleanup is performed. |
| `--max-local-days` | Delete local backups older than N days. | No cleanup is performed. |
| `--local` | Store the backup **only** on the local disk. | Uploads to remote disk. |
| `--keep-local` | Keep the local copy after a successful remote upload. Required to make `--max-local-*` effective in remote-upload flows. No-op when `--local` is set. | Local copy is deleted after upload. |

> The `--max-local-*` options only take effect when there is a local copy after the run — i.e. when `--local` is used, or when `--keep-local` is combined with a remote upload. In the default flow (upload to remote without `--keep-local`), the local file is deleted right after upload.

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

**Local-only backup with combined retention (keep last 10 and not older than 5 days):**

```bash
php artisan easy-backups:db:create --of-database=mysql --local --max-local-backups=10 --max-local-days=5
```

**Remote upload that also keeps a local copy with both retentions:**

```bash
php artisan easy-backups:db:create --keep-local --max-remote-backups=30 --max-local-backups=3
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

**Restore a backup from the `staging` environment:**

```bash
php artisan easy-backups:db:restore --source-env=staging
```

**Restore from local storage:**

```bash
php artisan easy-backups:db:restore --local
```
