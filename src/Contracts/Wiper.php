<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Contracts;

interface Wiper
{
   /**
    * Wipes all data from the database.
    */
   public function wipe(): void;
}
