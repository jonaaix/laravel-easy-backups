<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Contracts;

interface ProcessExecutor
{
   public function execute(string $command, string $cwd, array $env = [], ?float $timeout = 3600): void;
}
