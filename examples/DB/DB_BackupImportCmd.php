<?php

namespace App\Console\Commands\DB;

use App\Enums\DiskEnum;
use App\Helpers\NiceFile;
use Illuminate\Console\Command;
use Storage;
use Database\Seeders\DevUserSeeder;

class DB_BackupImportCmd extends Command {
   /**
    * The name and signature of the console command.
    */
   protected $signature = 'db:backup:import {--local : Import a local backup}';

   /**
    * The console command description.
    */
   protected $description = 'Import a backup of the database';

   /**
    * Execute the console command.
    */
   public function handle(): void {
      $backupFile = null;

      if ($this->option('local')) {
         // Show selection of available local backups
         $this->info('Available local backups:');
         $backups = Storage::disk(DiskEnum::LOCAL_BACKUP_DB)->files();
         $choice = $this->choice('Select a backup to import', $backups);
         $backupFile = Storage::disk(DiskEnum::LOCAL_BACKUP_DB)->path($choice);
      }

      if (!$backupFile) {
         // Download latest S3 backup
         $this->info('Checking latest backup from S3');
         $latestBackup = collect(Storage::disk(DiskEnum::BACKUP)->files('prod/mysql'))
            ->sort()
            ->last();

         if (!$latestBackup) {
            $this->error('No S3 backups found');
            return;
         }

         $this->info("Latest backup: {$latestBackup}");
         $this->comment('Size: ' . number_format(Storage::disk(DiskEnum::BACKUP)->size($latestBackup) / 1024 / 1024, 3) . ' MB');
         $this->info('Downloading...');

         $niceFile = NiceFile::make(basename($latestBackup), Storage::disk(DiskEnum::LOCAL_BACKUP_DB)->path(''));
         $niceFile->putFromS3(Storage::disk(DiskEnum::BACKUP), $latestBackup);
         $backupFile = $niceFile->filePath;
      }

      $this->info("Importing backup from {$backupFile}");

      // Delete all tables in the target database
      $tables = \DB::select('SHOW TABLES');
      foreach ($tables as $table) {
         $tableName = array_values((array) $table)[0];
         \DB::statement('SET FOREIGN_KEY_CHECKS=0;');
         \DB::statement("DROP TABLE IF EXISTS $tableName");
         \DB::statement('SET FOREIGN_KEY_CHECKS=1;');
      }

      // Import the backup
      $host = config('database.connections.mysql.host');
      $username = config('database.connections.mysql.username');
      $password = config('database.connections.mysql.password');
      $database = config('database.connections.mysql.database');

      $password = $password ? "-p$password" : '';
      $command = sprintf('mysql -h%s -u%s %s %s --force < %s', $host, $username, $password, $database, $backupFile);

      exec($command);

      $this->info("Backup imported from {$backupFile}");
   }
}
