<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Notifications;

use Aaix\LaravelEasyBackups\Services\ByteFormatter;
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
      $formattedSize = ByteFormatter::format($this->sizeInBytes);
      $appName = config('app.name');

      return (new MailMessage())
         ->success()
         ->subject("{$appName}: Backup was successful")
         ->greeting('Backup Succeeded!')
         ->line("A new backup of your application has been successfully created on the disk **{$this->disk}**.")
         ->line("**Total Size:** {$formattedSize}")
         ->line("**Number of Files:** {$fileCount}")
         ->line('**Created Artefacts:**')
         ->lines(collect($this->paths)->map(fn(string $path) => '- ' . basename($path))->toArray());
   }
}
