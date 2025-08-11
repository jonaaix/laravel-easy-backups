<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Contracts;

use Aaix\LaravelEasyBackups\Exceptions\DumpFailedException;

interface Dumper
{
   /**
    * @throws DumpFailedException
    */
   public function dumpToFile(string $path): void;
}
