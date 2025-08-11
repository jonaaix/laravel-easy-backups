<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups;

final class ConnectionConfig
{
   public function __construct(
      public readonly string $driver,
      public readonly string $host,
      public readonly int $port,
      public readonly string $database,
      public readonly string $username,
      public readonly string $password,
   ) {
   }
}
