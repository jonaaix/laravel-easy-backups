<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Commands;

use Illuminate\Console\Command;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\confirm;

class EasyBackupsCommand extends Command
{
   protected $signature = 'easy-backups';

   protected $description = 'Interactive wizard for managing backups and restores.';

   public function handle(): int
   {
      $this->info('Welcome to Laravel Easy Backups Wizard');

      $action = select(
         label: 'What would you like to do?',
         options: [
            'create' => 'Create a new backup',
            'restore' => 'Restore from a backup',
         ],
         default: 'create'
      );

      return match ($action) {
         'create' => $this->handleCreate(),
         'restore' => $this->handleRestore(),
      };
   }

   private function handleCreate(): int
   {
      // 1. Database Selection
      $connection = text(
         label: 'Which database connection should be backed up?',
         default: config('database.default'),
         required: true
      );

      // 2. Target Selection
      $target = select(
         label: 'Where should the backup be stored?',
         options: [
            'remote' => 'Upload to Remote Disk (Recommended)',
            'local' => 'Local Disk Only (Snapshot)',
         ],
         default: 'remote'
      );

      // 3. Compression
      $compress = confirm(
         label: 'Should the backup be compressed?',
         default: true
      );

      // 4. Custom Name
      $useCustomName = confirm(
         label: 'Do you want to add a custom name suffix?',
         default: false
      );

      $name = $useCustomName ? text(
         label: 'Enter a name suffix (e.g. "pre-deploy")',
         required: true
      ) : null;

      // Construct arguments for the actual command
      $arguments = [
         '--of-database' => $connection,
      ];

      if ($target === 'local') {
         $arguments['--local'] = true;
      }

      if ($compress) {
         $arguments['--compress'] = true;
      }

      if ($name) {
         $arguments['--name'] = $name;
      }

      $this->info('Delegating to [easy-backups:db:create]...');

      return $this->call('easy-backups:db:create', $arguments);
   }

   private function handleRestore(): int
   {
      // The restore command is already fully interactive by design.
      // We just pass control to it.
      $this->info('Delegating to [easy-backups:db:restore]...');

      return $this->call('easy-backups:db:restore');
   }
}
