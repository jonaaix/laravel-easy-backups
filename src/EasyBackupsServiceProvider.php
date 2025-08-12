<?php

namespace Aaix\LaravelEasyBackups;

use Aaix\LaravelEasyBackups\Contracts\ProcessExecutor;
use Aaix\LaravelEasyBackups\Services\DirectProcessExecutor;
use Illuminate\Support\ServiceProvider;

class EasyBackupsServiceProvider extends ServiceProvider
{
   public function boot(): void
   {
      if ($this->app->runningInConsole()) {
         $this->publishes(
            [
               __DIR__ . '/../config/easy-backups.php' => config_path('easy-backups.php'),
            ],
            'config',
         );
         $this->commands([
            \Aaix\LaravelEasyBackups\Commands\CreateDatabaseBackupCommand::class,
            \Aaix\LaravelEasyBackups\Commands\RestoreDatabaseBackupCommand::class,
         ]);
      }
   }

   public function register(): void
   {
      $this->mergeConfigFrom(__DIR__ . '/../config/easy-backups.php', 'easy-backups');

      $this->app->bind(ProcessExecutor::class, DirectProcessExecutor::class);

      $this->app->bind('laravel-easy-backup', function () {
         return new Backup();
      });

      $this->app->bind('laravel-easy-restore', function () {
         return new Restorer();
      });
   }
}
