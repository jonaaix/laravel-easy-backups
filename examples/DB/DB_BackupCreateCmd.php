<?php

namespace App\Console\Commands\DB;

use App\Enums\DiskEnum;
use Illuminate\Console\Command;
use Illuminate\Http\File;
use Illuminate\Support\Facades\DB; // Import DB Facade
use Illuminate\Support\Str; // Import Str Facade

class DB_BackupCreateCmd extends Command {
   /**
    * The name and signature of the console command.
    * ok
    */
   protected $signature = 'db:backup:create { --keep-last= : Keep the last n backups on s3 } { --local : Store backup locally } { --keep-local : Keep local copy } { --name= : Name of the backup }';

   /**
    * The console command description.
    */
   protected $description = 'Create a backup of the database';

   /**
    * Execute the console command.
    */
   public function handle(): void {
      $this->info('Creating backup...');
      if ($this->option('local')) {
         $this->line('Storing only locally');
      }

      $name = $this->option('name') ? '_' . Str::kebab($this->option('name')) : '';
      $timeStr = date('Y-m-d_H-i-s');
      $fileName = sprintf('db-backup_%s%s.sql',$timeStr, $name);
      $this->info('Filename: ' . $fileName);

      $disk = \Storage::disk(DiskEnum::LOCAL_BACKUP_DB);
      $path = $disk->path($fileName);

      $host = config('database.connections.mysql.host');
      $username = config('database.connections.mysql.username');
      $password = config('database.connections.mysql.password');
      $database = config('database.connections.mysql.database');

      // Determine database type and select dump command
      $dumpCommandExecutable = 'mysqldump';
      try {
         $version = DB::connection()->getPdo()->query('SELECT VERSION()')->fetchColumn();
         if (Str::contains($version, 'MariaDB', true)) {
            $dumpCommandExecutable = 'mariadb-dump';
            $this->info('MariaDB detected. Using mariadb-dump.');
         } else {
            $this->info('MySQL detected. Using mysqldump.');
         }
      } catch (\Exception $e) {
         $this->warn('Could not determine database version. Defaulting to mysqldump. Error: ' . $e->getMessage());
      }

      $passwordArg = $password ? "-p$password" : '';
      $command = sprintf('%s -h%s -u%s %s %s > %s', $dumpCommandExecutable, $host, $username, $passwordArg, $database, $path);
      exec($command);

      $this->info('Backup created at ' . $path);
      $this->info('Size: ' . number_format($disk->size($fileName) / 1024 / 1024, 3) . ' MB');

      if ($this->option('local')) {
         $this->info('Skipping upload to S3');
         return;
      }

      $this->info('Uploading to S3');
      $prefix = config('app.env') === 'production' ? 'prod' : 'dev';
      \Storage::disk(DiskEnum::BACKUP)->putFileAs("$prefix/mysql", new File($path), $fileName);

      if ($keepLast = $this->option('keep-last')) {
         $this->info('Triggering S3 backup cleanup...');
         $this->call('db:backup:clean-s3', [
             '--keep-last' => $keepLast
         ]);
      }

      if ($this->option('keep-local')) {
         $this->info('Keeping local copy');
      } else {
         $this->info('Deleting local copy');
         \Storage::disk(DiskEnum::LOCAL_BACKUP_DB)->delete($fileName);
      }
   }
}
