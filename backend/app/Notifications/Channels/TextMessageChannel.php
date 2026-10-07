<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\Messaging\Messenger;
use App\Services\Messaging\MessagingSettings;

/**
 * SMS and/or WhatsApp copy of an in-app notification (Sprint 18). Runs from the queue (see
 * AppNotification::viaConnections), so families get the text even if the gateway is slow.
 */
class TextMessageChannel
{
    public function __construct(private Messenger $messenger, private MessagingSettings $settings) {}

    public function send(User $notifiable, AppNotification $notification): void
    {
        $to = Messenger::mobile($notifiable->phone);
        if (! $to) {
            return;
        }
        $text = $this->messenger->compose($notification->title, $notification->body);
        foreach (['sms', 'whatsapp'] as $channel) {
            if ($this->settings->flag("{$channel}_enabled")) {
                $this->messenger->send($channel, $to, $text, $notification->kind, $notifiable);
            }
        }
    }

    /** Whether this notification should also go out as a text to this person. */
    public static function wanted(User $notifiable, string $kind): bool
    {
        $settings = app(MessagingSettings::class);
        if (! $settings->flag('sms_enabled') && ! $settings->flag('whatsapp_enabled')) {
            return false;
        }
        if (! in_array($kind, $settings->kinds(), true) || ! Messenger::mobile($notifiable->phone)) {
            return false;
        }

        return $notifiable->user_type?->value === 'parent' || $settings->flag('staff_sms');
    }
}
