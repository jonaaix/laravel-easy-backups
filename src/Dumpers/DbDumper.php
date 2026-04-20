<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Dumpers;

use Aaix\LaravelEasyBackups\ConnectionConfig;
use Aaix\LaravelEasyBackups\Contracts\Dumper;
use Aaix\LaravelEasyBackups\Contracts\ProcessExecutor;
use Illuminate\Support\Facades\File;

abstract class DbDumper implements Dumper
{
   protected array $excludeTables = [];
   protected array $excludeTableData = [];

   public function __construct(
      protected ConnectionConfig $config,
      protected ProcessExecutor $executor
   ) {
   }

   public function excludeTables(array $tables): static
   {
      $this->excludeTables = array_values(array_filter(array_map('trim', $tables)));
      return $this;
   }

   public function excludeTableData(array $tables): static
   {
      $this->excludeTableData = array_values(array_filter(array_map('trim', $tables)));
      return $this;
   }

   abstract public function getDumpCommand(string $path): string;

   public function dumpToFile(string $path): void
   {
      File::ensureDirectoryExists(dirname($path));
      $command = $this->getDumpCommand($path);

      // CWD for direct execution should be the output path.
      // For Docker, the executor's CWD (project root) is used.
      $this->executor->execute(
         command: $command,
         cwd: dirname($path),
         timeout: null
      );
   }
}
