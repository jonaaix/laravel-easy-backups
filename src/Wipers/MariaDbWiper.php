<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Wipers;

use Aaix\LaravelEasyBackups\Contracts\Wiper;
use Illuminate\Support\Facades\DB;

class MariaDbWiper implements Wiper
{
   public function __construct(protected string $connectionName)
   {
   }

   public function wipe(): void
   {
      $connection = DB::connection($this->connectionName);
      $connection->statement('SET FOREIGN_KEY_CHECKS=0;');

      $tables = $connection->select('SHOW TABLES');
      $droplist = [];
      $key = 'Tables_in_' . $connection->getDatabaseName();
      foreach ($tables as $table) {
         $droplist[] = $table->$key;
      }

      if (!empty($droplist)) {
         $connection->statement('DROP TABLE IF EXISTS ' . implode(',', $droplist));
      }

      $connection->statement('SET FOREIGN_KEY_CHECKS=1;');
   }
}
