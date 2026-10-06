<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One notification type for every in-app message (bell) — and email when it is switched on and the person
 * has an address (Plan §৩৪). SMS/WhatsApp channels plug in here once a gateway is chosen.
 */
class AppNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public string $kind,
        public string $title,
        public string $body,
        public string $url,
        public bool $email = false,
    ) {}

    /**
     * The bell entry is written at once; the email waits in the queue, so a slow or failing mail server
     * never slows down or breaks the booking / payment that triggered it (cron runs queue:work every minute).
     */
    public function viaConnections(): array
    {
        return ['database' => 'sync', 'mail' => config('queue.default')];
    }

    public function via(object $notifiable): array
    {
        return $this->email && filled($notifiable->email ?? null) ? ['database', 'mail'] : ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['kind' => $this->kind, 'title' => $this->title, 'body' => $this->body, 'url' => $this->url];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title.' — C-STAR')
            ->greeting($this->title)
            ->line($this->body)
            ->action('Open C-STAR', rtrim((string) config('app.url'), '/').$this->url);
    }
}
