---
sidebar_position: 35
---

# Anonymizing Backups

Sometimes you need a backup that carries **realistic** production data — correct volumes, relationships and edge cases — but **without** the sensitive values. The classic use case is a development or staging database that mirrors production closely, yet contains no real emails, names or phone numbers.

The `obfuscate()` method on the Fluent API does exactly that: it replaces the values of specific columns with generated fake data while leaving everything else untouched.

```php
use Aaix\LaravelEasyBackups\Facades\Backup;
use Faker\Generator as Faker;

Backup::database('mysql')
    ->obfuscate([
        'users.email' => fn (Faker $faker, array $row) => $faker->unique()->safeEmail(),
        'users.name'  => fn (Faker $faker, array $row) => $faker->name(),
        'users.phone' => fn (Faker $faker, array $row) => $faker->phoneNumber(),
    ])
    ->compress()
    ->onlyLocal()
    ->setName('dev-anonymized')
    ->run();
```

::: info Requires Faker
Obfuscation uses [`fakerphp/faker`](https://fakerphp.org/), which is **not** a hard dependency of this package. Install it where you run anonymized backups:

```bash
composer require fakerphp/faker
```

If `obfuscate()` is used without Faker installed, the backup fails with a clear exception.
:::

## How it works

Native dump tools (`mysqldump`, `pg_dump`, …) stream raw rows straight to disk — there is no hook to transform a value mid-dump. Easy Backups therefore splits the work:

1. **Structure only:** obfuscated tables are dumped *without* their data (reusing the same mechanism as `excludeTableData()`).
2. **Fetch & transform:** the rows are read from the live connection in chunks; mapped columns are passed through your callback, everything else is copied verbatim.
3. **Append:** the resulting `INSERT` statements are appended to the same dump file.

All other tables are dumped natively at full speed and contain real data. The result is a single, consistent `.sql` file.

## The mapping

The argument to `obfuscate()` is an array keyed by `'table.column'`. Each value is a callback that receives the Faker generator and the **full original row**:

```php
->obfuscate([
    'users.email' => fn (Faker $faker, array $row) => $faker->unique()->safeEmail(),

    // The whole row is available — derive a value from another column:
    'users.full_name' => fn (Faker $faker, array $row) => $row['salutation'] . ' ' . $faker->lastName(),
])
```

### NULL values are preserved

If a cell is `NULL` in the source row, it stays `NULL` — the callback is **not** invoked for it. This keeps the nullability distribution of your production data intact (an optional field that is empty in production stays empty in the anonymized copy). Columns that are not part of the mapping are copied exactly as-is, including their `NULL`s.

### Uniqueness is your responsibility

A column with a `UNIQUE` constraint (like `users.email`) must receive unique values, otherwise the restore will fail on a duplicate key. Use Faker's unique modifier explicitly for those columns:

```php
'users.email' => fn (Faker $faker, array $row) => $faker->unique()->safeEmail(),
```

::: warning Don't obfuscate keys
Do not obfuscate primary keys or foreign keys. Replacing them breaks referential integrity between the dumped tables. Target descriptive columns (email, name, address, phone, …) instead.
:::

## Validation

The mapping is validated and **fails hard** on any inconsistency, so problems surface immediately instead of producing a broken dump:

* The key is not in `'table.column'` format.
* The value is not callable.
* The table is listed in both `obfuscate()` and `excludeTables()` / `excludeTableData()`.
* The table or column does not exist on the connection.

## Queued backups

Obfuscation works with queued backups (`->onQueue(...)`). The callbacks are wrapped so they can be serialized and executed by your queue worker — no extra configuration required.

## Foreign key handling

Because the anonymized `INSERT` statements are appended at the end of the dump file, they are wrapped in driver-specific foreign-key toggles (e.g. `SET FOREIGN_KEY_CHECKS = 0; … = 1;` for MySQL) so the restore order never trips a constraint.

## A complete example

```php
use Aaix\LaravelEasyBackups\Facades\Backup;
use Faker\Generator as Faker;

// Pull a production-shaped, fully anonymized snapshot for the dev team.
Backup::database('mysql')
    ->obfuscate([
        'users.email'      => fn (Faker $faker, array $row) => $faker->unique()->safeEmail(),
        'users.name'       => fn (Faker $faker, array $row) => $faker->name(),
        'users.phone'      => fn (Faker $faker, array $row) => $faker->phoneNumber(),
        'customers.iban'   => fn (Faker $faker, array $row) => $faker->iban(),
        'addresses.street' => fn (Faker $faker, array $row) => $faker->streetAddress(),
    ])
    ->excludeTableData(['password_resets', 'sessions']) // structure only, no rows at all
    ->compress()
    ->saveTo('backup')
    ->setName('anonymized-snapshot')
    ->run();
```
