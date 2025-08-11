<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ListBackupsCommand extends Command
{
    protected $signature = 'backup:list {--disk=}';

    protected $description = 'List all backups';

    public function handle(): int
    {
        $diskName = $this->option('disk') ?? Storage::getDefaultDriver();

        $disk = Storage::disk($diskName);

        $files = $disk->files(config('easy-backups.defaults.database.remote_storage_path'));

        if (empty($files)) {
            $this->info('No backups found.');
            return self::SUCCESS;
        }

        $this->info(sprintf('Backups on disk \'%s\':', $diskName));

        $backups = [];
        foreach ($files as $file) {
            $backups[] = [
                'name' => basename($file),
                'size' => $this->formatSize($disk->size($file)),
                'disk' => $diskName,
                'date' => date('Y-m-d H:i:s', $disk->lastModified($file)),
            ];
        }

        $this->table([
            'Filename',
            'Size',
            'Disk',
            'Created At',
        ], $backups);

        return self::SUCCESS;
    }

    private function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < 4) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
