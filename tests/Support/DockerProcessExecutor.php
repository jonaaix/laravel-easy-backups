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

      // Translate the host path inside the command to the container path.
      $containerCommand = str_replace(
         $cwd,
         '/app/temp',
         $command
      );

      // Replace external host/port with internal Docker networking details
      $config = config("database.connections.{$this->serviceName}");
      $externalHost = $config['host'];
      $externalPort = $config['port'];

      $internalHost = 'localhost'; // Inside the service container, the DB is on localhost
      $internalPort = match ($config['driver']) {
         'pgsql' => 5432,
         default => 3306,
      };

      // Replace host and port in the command string for all drivers
      $containerCommand = str_replace("-h{$externalHost}", "-h{$internalHost}", $containerCommand);
      $containerCommand = str_replace("-P{$externalPort}", "-P{$internalPort}", $containerCommand); // For MySQL/MariaDB
      $containerCommand = str_replace("-p {$externalPort}", "-p {$internalPort}", $containerCommand); // For PostgreSQL with space

      $envString = collect($env)
         ->map(fn($value, $key) => '-e ' . escapeshellarg("{$key}={$value}"))
         ->implode(' ');

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
            "Docker command execution failed. Error: {$process->getErrorOutput()}" . " Full command: " . $dockerCommand
         );
      }
   }
}
