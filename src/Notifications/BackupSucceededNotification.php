<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BackupSucceededNotification extends Notification
{
   use Queueable;

   public function __construct(
      private readonly array $paths,
      private readonly string $disk,
      private readonly int $sizeInBytes
   ) {
   }

   public function via(object $notifiable): array
   {
      return ['mail'];
   }

   public function toMail(object $notifiable): MailMessage
   {
      $fileCount = count($this->paths);
      $formattedSize = $this->formatSize($this->sizeInBytes);
      $appName = config('app.name');

      $pathList = collect($this->paths)
         ->map(fn(string $path) => '- `' . basename($path) . '`')
         ->implode("\n");

      return (new MailMessage())
         ->success()
         ->subject("{$appName}: Backup was successful")
         ->greeting('Backup Succeeded!')
         ->line("A new backup of your application has been successfully created on the disk '{$this->disk}'.")
         ->line("**Total Size:** {$formattedSize}")
         ->line("**Number of Files:** {$fileCount}")
         ->line('**Created Artefacts:**')
         ->line($pathList);
   }

   private function formatSize(int $bytes): string
   {
      if ($bytes === 0) {
         return '0 B';
      }

      $units = ['B', 'KB', 'MB', 'GB', 'TB'];
      $i = (int)floor(log($bytes, 1024));
      return round($bytes / (1024 ** $i), 2) . ' ' . $units[$i];
   }
}
