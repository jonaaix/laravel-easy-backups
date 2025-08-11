<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Events;

use Illuminate\Foundation\Events\Dispatchable;

final class RestoreSucceeded
{
   use Dispatchable;

   public function __construct(
      public readonly string $sourceDisk,
      public readonly string $sourcePath,
      public readonly string $databaseConnection,
   ) {
   }
}
