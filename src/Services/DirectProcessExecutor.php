<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Services;

use Aaix\LaravelEasyBackups\Contracts\ProcessExecutor;
use Aaix\LaravelEasyBackups\Exceptions\DumpFailedException;
use Symfony\Component\Process\Process;

class DirectProcessExecutor implements ProcessExecutor
{
   public function execute(string $command, string $cwd, array $env = [], ?float $timeout = 3600): void
   {
      // For direct execution, environment variables need to be part of the command string or exported.
      // Prepending them is a reliable cross-platform way.
      $fullCommand = $this->prepareCommand($command, $env);

      $process = Process::fromShellCommandline($fullCommand, $cwd, null, null, $timeout);
      $process->run();

      if (!$process->isSuccessful()) {
         throw new DumpFailedException(
            "Command execution failed. Error: {$process->getErrorOutput()}" . " Full command: " . $fullCommand
         );
      }
   }

   private function prepareCommand(string $command, array $env): string
   {
      if (empty($env)) {
         return $command;
      }

      $envString = collect($env)
         ->map(fn($value, $key) => $key . '=' . escapeshellarg($value))
         ->implode(' ');

      return trim($envString . ' ' . $command);
   }
}
