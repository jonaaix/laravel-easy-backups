<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Commands;

use Aaix\LaravelEasyBackups\Facades\Backup;
use Illuminate\Console\Command;

class RunBackupCommand extends Command
{
    protected $signature = 'backup:run
                            {--db=* : The databases to backup}
                            {--dir=* : The directories to backup}
                            {--file=* : The files to backup}
                            {--disk= : The disk to store the backup on}
                            {--password= : The password for encryption}
                            {--keep-local : Keep a local copy of the backup}';

    protected $description = 'Run a backup';

    public function handle(): int
    {
        $backup = Backup::create();

        if ($this->option('db')) {
            $backup->includeDatabases($this->option('db'));
        }

        if ($this->option('dir')) {
            $backup->includeDirectories($this->option('dir'));
        }

        if ($this->option('file')) {
            $backup->includeFiles($this->option('file'));
        }

        if ($this->option('disk')) {
            $backup->saveTo($this->option('disk'));
        }

        if ($this->option('password')) {
            $backup->encryptWithPassword($this->option('password'));
        }

        if ($this->option('keep-local')) {
            $backup->keepLocal();
        }

        $this->info('Starting backup...');

        $backup->run();

        $this->info('Backup process started.');

        return self::SUCCESS;
    }
}