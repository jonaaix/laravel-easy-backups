# Changelog

## Version v1.2.0
- Add `obfuscate()` to the Fluent API for anonymized backups — replace sensitive column values with Faker-generated data via a `'table.column' => fn(Faker, $row)` map
- Obfuscated tables are dumped structure-only, then re-inserted with fake data (NULLs preserved, FK checks toggled, queue-safe via `SerializableClosure`)
- Add `fakerphp/faker` as an optional (`suggest`) dependency with a clear runtime guard
- Add "Anonymizing Backups" documentation page

## Version v1.1.5
- Add `easy-backups:db:import` as an alias of `easy-backups:db:restore` for better discoverability
- Add `--force` flag to the restore command for unattended use: auto-selects the latest backup and skips all confirmations (CI / AI agents)
- Document the import alias and unattended `--force` workflow in the Laravel Boost AI skill file

## Version v1.1.4
- Add `--max-local-backups` / `--max-local-days` CLI options and `maxLocalDays()` fluent method
- Add days-based retention for local backups (count + age can be combined)
- Add `--keep-local` CLI flag (equivalent to `keepLocal()`) to preserve the local copy after a remote upload
- Migrate documentation site from Docusaurus to VitePress
- Add Laravel Boost AI skill file at `resources/boost/skills/easy-backups-development/SKILL.md`

## Version v1.1.3
- Add interactive `easy-backups:db:manage` command for inspecting and deleting local & remote backups
- Add `easy-backups:db:list` command to list backups on a disk with size, age, and format
- Add `BackupInventoryService` as the single source of truth for discovering existing backup artifacts
- Add `--dry-run` mode (CLI) and `dryRun()` (fluent) — print the plan without executing dumps, compression, or uploads
- Add table exclusion (`--exclude-tables` / `excludeTables()`) and data-only exclusion (`--exclude-table-data` / `excludeTableData()`) for sensitive tables
- Add MariaDB parallel dump option (`use_parallel`)
- Restore now asks for source disk interactively instead of defaulting to remote

## Version v1.1.2
- Add mail notification to create backup cmd

## Version v1.1.1
- Fix default restore env path

## Version v1.1.0
- Changed default backup disk from `s3-backup` to `backup`
- Fix path issues
- Fix local only logic
- Add interactive wizard command
- Add detailed progress logging to cli
