<?php

use Aaix\LaravelEasyBackups\Actions\ObfuscateTableDataAction;
use Aaix\LaravelEasyBackups\Exceptions\ObfuscationException;
use Aaix\LaravelEasyBackups\Facades\Backup;
use Faker\Generator as Faker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Laravel\SerializableClosure\SerializableClosure;

function seedObfuscationTable(): void
{
   $schema = DB::connection('sqlite_test')->getSchemaBuilder();
   $schema->dropIfExists('users');
   $schema->create('users', function ($table) {
      $table->integer('id');
      $table->string('name');
      $table->string('email')->nullable();
      $table->string('phone')->nullable();
   });

   DB::connection('sqlite_test')->table('users')->insert([
      ['id' => 1, 'name' => 'Real One', 'email' => 'real.one@example.com', 'phone' => '111'],
      ['id' => 2, 'name' => 'Real Two', 'email' => 'real.two@example.com', 'phone' => null],
      ['id' => 3, 'name' => 'Real Three', 'email' => null, 'phone' => '333'],
   ]);
}

it('replaces mapped columns, keeps others, and preserves nulls', function () {
   $this->setupTemporarySqliteDatabase();
   seedObfuscationTable();

   $dumpPath = $this->setupTemporaryDirectory() . '/obfuscation-test.sql';
   File::delete($dumpPath);

   app(ObfuscateTableDataAction::class)->execute('sqlite_test', $dumpPath, [
      'users.name' => fn(Faker $faker, array $row) => 'ANON',
      'users.email' => fn(Faker $faker, array $row) => 'anon@anon.test',
   ]);

   $contents = File::get($dumpPath);

   expect($contents)->toContain('INSERT INTO "users"');

   expect($contents)
      ->not->toContain('real.one@example.com')
      ->not->toContain('real.two@example.com')
      ->not->toContain('Real One');

   expect(substr_count($contents, "'anon@anon.test'"))->toBe(2);
   expect(substr_count($contents, "'ANON'"))->toBe(3);

   expect($contents)
      ->toContain("'111'")
      ->toContain("'333'");

   expect(substr_count($contents, 'NULL'))->toBeGreaterThanOrEqual(2);
});

it('wraps appended data in foreign key toggles', function () {
   $this->setupTemporarySqliteDatabase();
   seedObfuscationTable();

   $dumpPath = $this->setupTemporaryDirectory() . '/obfuscation-fk.sql';
   File::delete($dumpPath);

   app(ObfuscateTableDataAction::class)->execute('sqlite_test', $dumpPath, [
      'users.email' => fn(Faker $faker, array $row) => 'x@y.z',
   ]);

   $contents = File::get($dumpPath);

   expect($contents)
      ->toContain('PRAGMA foreign_keys = OFF;')
      ->toContain('PRAGMA foreign_keys = ON;');
});

it('survives serialization for queued backups', function () {
   $this->setupTemporarySqliteDatabase();
   seedObfuscationTable();

   $map = [
      'users.email' => new SerializableClosure(fn(Faker $faker, array $row) => 'queued@anon.test'),
   ];

   $restored = unserialize(serialize($map));

   $dumpPath = $this->setupTemporaryDirectory() . '/obfuscation-queue.sql';
   File::delete($dumpPath);

   app(ObfuscateTableDataAction::class)->execute('sqlite_test', $dumpPath, $restored);

   expect(File::get($dumpPath))->toContain("'queued@anon.test'");
});

it('throws on an invalid obfuscation key format', function () {
   Backup::database('sqlite_test')->obfuscate([
      'invalid_key' => fn(Faker $faker, array $row) => 'x',
   ]);
})->throws(ObfuscationException::class);

it('throws when an obfuscation value is not callable', function () {
   Backup::database('sqlite_test')->obfuscate([
      'users.email' => 12345,
   ]);
})->throws(ObfuscationException::class);

it('throws when a table is both excluded and obfuscated', function () {
   Backup::database('sqlite_test')
      ->excludeTables(['users'])
      ->obfuscate(['users.email' => fn(Faker $faker, array $row) => 'x'])
      ->onlyLocal()
      ->run();
})->throws(ObfuscationException::class);

it('throws when obfuscating an unknown table', function () {
   $this->setupTemporarySqliteDatabase();
   seedObfuscationTable();

   $dumpPath = $this->setupTemporaryDirectory() . '/obfuscation-missing.sql';
   File::delete($dumpPath);

   app(ObfuscateTableDataAction::class)->execute('sqlite_test', $dumpPath, [
      'ghosts.email' => fn(Faker $faker, array $row) => 'x',
   ]);
})->throws(ObfuscationException::class);

it('throws when obfuscating an unknown column', function () {
   $this->setupTemporarySqliteDatabase();
   seedObfuscationTable();

   $dumpPath = $this->setupTemporaryDirectory() . '/obfuscation-missing-col.sql';
   File::delete($dumpPath);

   app(ObfuscateTableDataAction::class)->execute('sqlite_test', $dumpPath, [
      'users.unknown_column' => fn(Faker $faker, array $row) => 'x',
   ]);
})->throws(ObfuscationException::class);
