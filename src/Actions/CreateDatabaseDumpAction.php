<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Actions;

use Aaix\LaravelEasyBackups\Contracts\Dumper;

final class CreateDatabaseDumpAction
{
   public function execute(Dumper $dumper, string $path): void
   {
      $dumper->dumpToFile($path);
   }
}
