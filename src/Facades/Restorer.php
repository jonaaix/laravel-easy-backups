<?php

namespace Aaix\LaravelEasyBackups\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Aaix\LaravelEasyBackups\Restorer create()
 * @method static \Aaix\LaravelEasyBackups\Restorer fromDisk(string $disk)
 * @method static \Aaix\LaravelEasyBackups\Restorer fromPath(string $path)
 * @method static \Aaix\LaravelEasyBackups\Restorer toDatabase(string $connection)
 * @method static \Aaix\LaravelEasyBackups\Restorer withPassword(string $password)
 * @method static \Aaix\LaravelEasyBackups\Restorer disableWipe()
 * @method static \Aaix\LaravelEasyBackups\Restorer onConnection(string $connection)
 * @method static \Aaix\LaravelEasyBackups\Restorer onQueue(string $queue)
 * @method static mixed run()
 * @method static \Illuminate\Support\Collection getRecentBackups(string $disk, int $count = 30)
 */
class Restorer extends Facade
{
   protected static function getFacadeAccessor(): string
   {
      return 'laravel-easy-restore';
   }
}
