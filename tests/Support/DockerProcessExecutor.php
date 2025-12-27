<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Tests\Support;

use Aaix\LaravelEasyBackups\Contracts\ProcessExecutor;
use Aaix\LaravelEasyBackups\Exceptions\DumpFailedException;
use Symfony\Component\Process\Process;

class DockerProcessExecutor implements ProcessExecutor
{
   private string $projectRoot;

   public function __construct(private readonly string $serviceName)
   {
      $this->projectRoot = realpath(__DIR__ . '/../..');
   }

   public function execute(string $command, string $cwd, array $env = [], ?float $timeout = 3600): void
   {
      $composePath = $this->projectRoot . '/compose.testing.yml';

      // 1. Path Translation: Host path -> Container path
      // We explicitly map the volume defined in compose.testing.yml.
      // We do NOT use $cwd here because generic replacements (like replacing '/') are dangerous.
      $hostTempDir = $this->projectRoot . '/tests/temp';
      $containerTempDir = '/app/temp';

      // Replace the absolute host path with the absolute container path in the command string.
      $containerCommand = str_replace(
         $hostTempDir,
         $containerTempDir,
         $command
      );

      // 2. Network Translation: External Host/Port -> Internal Container Host/Port
      $config = config("database.connections.{$this->serviceName}");

      $externalHost = $config['host'];
      $externalPort = $config['port'];

      // Inside the service container, the DB is strictly on localhost
      $internalHost = 'localhost';
      $internalPort = match ($config['driver']) {
         'pgsql' => 5432,
         default => 3306,
      };

      // Precision Replacement for Dumpers (escapeshellarg handling):
      // e.g. --host='127.0.0.1' -> --host='localhost'
      $escapedExtHost = escapeshellarg($externalHost);
      $escapedIntHost = escapeshellarg($internalHost);

      $escapedExtPort = escapeshellarg((string) $externalPort);
      $escapedIntPort = escapeshellarg((string) $internalPort);

      $containerCommand = str_replace(
         "--host={$escapedExtHost}",
         "--host={$escapedIntHost}",
         $containerCommand
      );

      $containerCommand = str_replace(
         "--port={$escapedExtPort}",
         "--port={$escapedIntPort}",
         $containerCommand
      );

      // 3. Environment Variables
      $envString = collect($env)
         ->map(fn($value, $key) => '-e ' . escapeshellarg("{$key}={$value}"))
         ->implode(' ');

      // 4. Execute via Docker Compose
      $dockerCommand = sprintf(
         'docker compose -f %s exec -T %s %s sh -c %s',
         escapeshellarg($composePath),
         $envString,
         $this->serviceName,
         escapeshellarg($containerCommand)
      );

      $process = Process::fromShellCommandline($dockerCommand, $this->projectRoot, null, null, $timeout);
      $process->run();

      if (!$process->isSuccessful()) {
         throw new DumpFailedException(
            "Docker command execution failed.\nError: {$process->getErrorOutput()}\nFull command: {$dockerCommand}"
         );
      }
   }
}
