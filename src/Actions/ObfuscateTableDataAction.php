<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Actions;

use Aaix\LaravelEasyBackups\Exceptions\ObfuscationException;
use Closure;
use Faker\Factory as FakerFactory;
use Faker\Generator as Faker;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Laravel\SerializableClosure\SerializableClosure;

final class ObfuscateTableDataAction
{
   private const CHUNK_SIZE = 1000;

   public function execute(string $connectionName, string $dumpPath, array $map): void
   {
      if (empty($map)) {
         return;
      }

      if (!class_exists(FakerFactory::class)) {
         throw ObfuscationException::fakerMissing();
      }

      $connection = DB::connection($connectionName);
      $schema = Schema::connection($connectionName);
      $faker = FakerFactory::create();

      $tables = $this->groupByTable($connectionName, $map, $schema);

      File::append($dumpPath, $this->fkConstraintsLine($connection, false));

      foreach ($tables as $table => $columnMap) {
         $this->dumpObfuscatedTable($connection, $schema, $table, $columnMap, $faker, $dumpPath);
      }

      File::append($dumpPath, $this->fkConstraintsLine($connection, true));
   }

   private function groupByTable(string $connectionName, array $map, $schema): array
   {
      $grouped = [];

      foreach ($map as $key => $callback) {
         [$table, $column] = explode('.', $key, 2);

         if (!$schema->hasTable($table)) {
            throw ObfuscationException::missingTable($table);
         }

         if (!$schema->hasColumn($table, $column)) {
            throw ObfuscationException::missingColumn($table, $column);
         }

         $grouped[$table][$column] = $this->resolveCallback($callback);
      }

      return $grouped;
   }

   private function resolveCallback(mixed $callback): Closure
   {
      if ($callback instanceof SerializableClosure) {
         $callback = $callback->getClosure();
      }

      return Closure::fromCallable($callback);
   }

   private function dumpObfuscatedTable(
      Connection $connection,
      $schema,
      string $table,
      array $columnMap,
      Faker $faker,
      string $dumpPath
   ): void {
      $columns = $schema->getColumnListing($table);

      if (empty($columns)) {
         return;
      }

      $grammar = $connection->getQueryGrammar();
      $wrappedTable = $grammar->wrapTable($table);
      $wrappedColumns = implode(', ', array_map(fn(string $c): string => $grammar->wrap($c), $columns));

      $buffer = [];

      $connection->table($table)->orderBy($columns[0])->chunk(
         self::CHUNK_SIZE,
         function ($rows) use ($connection, $columns, $columnMap, $faker, $dumpPath, $wrappedTable, $wrappedColumns, &$buffer): void {
            foreach ($rows as $row) {
               $row = (array) $row;
               $values = [];

               foreach ($columns as $column) {
                  $value = $row[$column] ?? null;

                  if (isset($columnMap[$column]) && $value !== null) {
                     $value = ($columnMap[$column])($faker, $row);
                  }

                  $values[] = $this->quoteValue($connection, $value);
               }

               $buffer[] = '(' . implode(', ', $values) . ')';
            }

            $this->flush($dumpPath, $wrappedTable, $wrappedColumns, $buffer);
         }
      );
   }

   private function flush(string $dumpPath, string $wrappedTable, string $wrappedColumns, array &$buffer): void
   {
      if (empty($buffer)) {
         return;
      }

      $statement = sprintf(
         "INSERT INTO %s (%s) VALUES\n%s;\n",
         $wrappedTable,
         $wrappedColumns,
         implode(",\n", $buffer)
      );

      File::append($dumpPath, $statement);

      $buffer = [];
   }

   private function quoteValue(Connection $connection, mixed $value): string
   {
      if ($value === null) {
         return 'NULL';
      }

      if (is_bool($value)) {
         return $value ? '1' : '0';
      }

      if (is_int($value) || is_float($value)) {
         return (string) $value;
      }

      return $connection->getPdo()->quote((string) $value);
   }

   private function fkConstraintsLine(Connection $connection, bool $enable): string
   {
      return match ($connection->getDriverName()) {
         'pgsql' => $enable
            ? "SET session_replication_role = 'origin';\n"
            : "SET session_replication_role = 'replica';\n",
         'sqlite' => $enable
            ? "PRAGMA foreign_keys = ON;\n"
            : "PRAGMA foreign_keys = OFF;\n",
         default => $enable
            ? "SET FOREIGN_KEY_CHECKS = 1;\n"
            : "SET FOREIGN_KEY_CHECKS = 0;\n",
      };
   }
}
