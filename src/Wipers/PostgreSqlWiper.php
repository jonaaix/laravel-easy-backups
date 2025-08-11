<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Wipers;

use Aaix\LaravelEasyBackups\Contracts\Wiper;
use Illuminate\Support\Facades\DB;

class PostgreSqlWiper implements Wiper
{
   public function __construct(protected string $connectionName)
   {
   }

   public function wipe(): void
   {
      $connection = DB::connection($this->connectionName);
      $connection->statement('DROP SCHEMA public CASCADE;');
      $connection->statement('CREATE SCHEMA public;');
   }
}
