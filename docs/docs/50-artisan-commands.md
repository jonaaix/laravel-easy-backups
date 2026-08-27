---
sidebar_position: 50
---

# Included Artisan Commands

The package includes robust commands for creating, restoring and monitoring database backups directly from the CLI.

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
php artisan easy-backups:db:restore {--latest} {--force} {--from-disk=} {--to-database=} {--source-env=} {--password=} {--local}
```

> Available under the alias `easy-backups:db:import` as well.

**Options**

| Option | Description | Default Behavior |
| --- | --- | --- |
| `--from-disk` | The filesystem disk where the backup is stored. | Defaults to `backup`. |
| `--to-database` | The target database connection to overwrite. | Defaults to default connection. |
| `--source-env` | The environment to pull backups from (e.g., `production`). | Defaults to current environment. |
| `--latest` | Skip the backup-selection prompt and pick the most recent backup. | Runs interactive selection. |
| `--force` | Run fully unattended: pick the latest backup and skip **all** prompts (source, wipe confirmation, local-copy). | Runs interactive selection. |
| `--password` | Password for encrypted backups. |  |
| `--local` | Force using the local disk as source. | Uses remote disk. |

> `--latest` only auto-selects the file. For a non-interactive script (e.g. a reset/seed routine) use `--force`. With `--force` the source defaults to the **remote** disk in the `production` environment — combine with `--local` to pull the latest local backup instead.

### Usage Examples

**Interactive restore:**

```bash
php artisan easy-backups:db:restore
```

**Restore latest backup (skips file selection, still confirms):**

```bash
php artisan easy-backups:db:restore --latest
```

**Fully unattended restore (CI/CD, reset scripts):**

```bash
php artisan easy-backups:db:restore --force --local
```

**Restore a backup from the `staging` environment:**

```bash
php artisan easy-backups:db:restore --source-env=staging
```

**Restore from local storage:**

```bash
php artisan easy-backups:db:restore --local
```

---

## `easy-backups:status`

Shows the health of your backups at a glance: the five most recent backups per disk, the interval they are created at, whether the latest dump has a suspicious size, how fast the footprint grows, what the next cleanup would delete, and whether a backup is registered in the scheduler at all.

```bash
php artisan easy-backups:status {--max-remote-backups=} {--max-remote-days=} {--max-local-backups=} {--max-local-days=}
```

Both the local and the remote database disk are inspected. The command is read-only and always exits with code `0`.

**Options**

| Option | Description | Default Behavior |
| --- | --- | --- |
| `--max-remote-backups` | Retention count used for the remote cleanup preview. | Read from the scheduled `db:create` invocation, if any. |
| `--max-remote-days` | Retention age in days used for the remote cleanup preview. | Read from the scheduled `db:create` invocation, if any. |
| `--max-local-backups` | Retention count used for the local cleanup preview. | Read from the scheduled `db:create` invocation, if any. |
| `--max-local-days` | Retention age in days used for the local cleanup preview. | Read from the scheduled `db:create` invocation, if any. |

> Retention is not stored in the package configuration — it is passed to `easy-backups:db:create` per run. The status command therefore reads the retention values back from your scheduled invocation of `db:create`. Pass the options explicitly to preview a policy that is not scheduled.

### What the signals mean

| Signal | Meaning |
| --- | --- |
| **Cadence** | Median and average interval between backups, derived from the file timestamps. Warns when the largest gap exceeds twice the median interval — a strong hint that a scheduled run failed silently. |
| **Size** | The newest backup compared against the median of up to 10 previous ones. Warns below 75% (a truncated dump that still exited successfully) or above 200%. |
| **Growth** | Projected gross footprint per month, based on cadence and median backup size. Ignores cleanup, so it answers "how much would this grow unchecked". |
| **Retention** | Simulates the next cleanup pass — how many backups and how many bytes it would delete. Grouped per upload directory, because cleanup operates per directory and non-recursively. |
| **Scheduler** | Registered `easy-backups:*` schedule entries with their cron expression and next run. Warns when none is registered. |

### Usage Examples

**Check backup health:**

```bash
php artisan easy-backups:status
```

**Preview a retention policy that is not scheduled:**

```bash
php artisan easy-backups:status --max-remote-backups=14 --max-local-backups=2
```
