<?php

declare(strict_types=1);

namespace Aaix\LaravelEasyBackups\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Throwable;

class BackupFailedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly array $config,
        public readonly Throwable $exception
    ) {
    }

    public function via(mixed $notifiable): array
    {
        return array_keys($notifiable->routes);
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('❌ Backup failed!')
            ->greeting('Hello!')
            ->line('The backup process failed with an exception.')
            ->line('**Exception Message:** ' . $this->exception->getMessage())
            ->line('**File:** ' . $this->exception->getFile())
            ->line('**Line:** ' . $this->exception->getLine())
            ->error();
    }
}
