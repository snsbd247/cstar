<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/** In-app notification for new website appointment requests and contact messages. */
class WebsiteEnquiryReceived extends Notification
{
    public function __construct(
        public string $kind,
        public string $title,
        public string $body,
        public string $url,
    ) {}

    /** SMS / WhatsApp / email channels plug in here later (Plan §৩৪). */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
        ];
    }
}
