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
      public readonly string $path,
      public readonly string $disk,
      public readonly int $sizeInBytes
   ) {
   }

   public function via(mixed $notifiable): array
   {
      return array_keys($notifiable->routes);
   }

   public function toMail(mixed $notifiable): MailMessage
   {
      $sizeInMB = number_format($this->sizeInBytes / 1024 / 1024, 2);

      return (new MailMessage())
         ->subject('✅ Backup was successful!')
         ->greeting('Hello!')
         ->line('A new backup has been created successfully.')
         ->line('**Backup File:** ' . basename($this->path))
         ->line('**Disk:** ' . $this->disk)
         ->line('**Size:** ' . $sizeInMB . ' MB')
         ->success();
   }
}
