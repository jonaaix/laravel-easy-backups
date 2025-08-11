<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Contracts;

use Aaix\LaravelEasyBackups\Exceptions\ImportFailedException;

interface Importer
{
   /**
    * @throws ImportFailedException
    */
   public function importFromFile(string $path): void;
}
