<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Events;

use Illuminate\Foundation\Events\Dispatchable;

final class BackupSucceeded
{
   use Dispatchable;

   public function __construct(
      public readonly string $path,
      public readonly string $disk,
      public readonly int $sizeInBytes,
   ) {
   }
}
