<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Wipers;

use Aaix\LaravelEasyBackups\Contracts\Wiper;
use Illuminate\Support\Facades\File;

class SqliteWiper implements Wiper
{
   public function __construct(protected string $dbPath)
   {
   }

   public function wipe(): void
   {
      if ($this->dbPath === ':memory:') {
         return; // Cannot wipe an in-memory database this way.
      }

      if (File::exists($this->dbPath)) {
         File::delete($this->dbPath);
      }

      File::put($this->dbPath, '');
   }
}
