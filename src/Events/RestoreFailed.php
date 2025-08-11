<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Throwable;

final class RestoreFailed
{
    use Dispatchable;

    public function __construct(
        public readonly array $config,
        public readonly Throwable $exception,
    ) {
    }
}
