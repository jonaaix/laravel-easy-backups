<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Exceptions;

use Exception;

final class ObfuscationException extends Exception
{
   public static function fakerMissing(): self
   {
      return new self(
         'Data obfuscation requires the "fakerphp/faker" package. Install it with: composer require fakerphp/faker'
      );
   }

   public static function invalidKey(string $key): self
   {
      return new self("Invalid obfuscation key '{$key}'. Expected format 'table.column'.");
   }

   public static function notCallable(string $key): self
   {
      return new self("The obfuscation value for '{$key}' must be a callable.");
   }

   public static function conflictingTable(string $table): self
   {
      return new self("Table '{$table}' cannot be obfuscated and excluded at the same time.");
   }

   public static function missingTable(string $table): self
   {
      return new self("Cannot obfuscate unknown table '{$table}'.");
   }

   public static function missingColumn(string $table, string $column): self
   {
      return new self("Cannot obfuscate unknown column '{$table}.{$column}'.");
   }
}
