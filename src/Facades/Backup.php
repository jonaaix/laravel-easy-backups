<?php

namespace Aaix\LaravelEasyBackups\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Aaix\LaravelEasyBackups\Backup database(string $connection)
 * @method static \Aaix\LaravelEasyBackups\Backup files()
 * @method static \Aaix\LaravelEasyBackups\Backup includeFiles(array $files)
 * @method static \Aaix\LaravelEasyBackups\Backup includeDirectories(array $directories)
 * @method static \Aaix\LaravelEasyBackups\Backup includeStorage(?string $path = null)
 * @method static \Aaix\LaravelEasyBackups\Backup includeEnv()
 * @method static \Aaix\LaravelEasyBackups\Backup saveTo(string $disk)
 * @method static \Aaix\LaravelEasyBackups\Backup keepLocal()
 * @method static \Aaix\LaravelEasyBackups\Backup maxRemoteBackups(int $count)
 * @method static \Aaix\LaravelEasyBackups\Backup maxLocalBackups(int $count)
 * @method static \Aaix\LaravelEasyBackups\Backup onConnection(string $connection)
 * @method static \Aaix\LaravelEasyBackups\Backup onQueue(string $queue)
 * @method static mixed run()
 */
class Backup extends Facade
{
   protected static function getFacadeAccessor(): string
   {
      return 'laravel-easy-backup';
   }
}
