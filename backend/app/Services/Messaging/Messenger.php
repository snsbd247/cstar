<?php

namespace App\Services\Messaging;

use App\Models\OutboundMessage;
use App\Models\User;

/**
 * Sends one SMS or WhatsApp message and records it in outbound_messages (Notifications → Logs).
 * Called from the queue (TextMessageChannel) so a slow gateway never holds up the person at the desk.
 */
class Messenger
{
    public function __construct(private MessagingSettings $settings) {}

    public function gateway(string $channel): Gateway
    {
        $driver = $this->settings->get($channel === 'whatsapp' ? 'whatsapp_driver' : 'sms_driver');

        return match ($driver) {
            'greenweb' => new GreenWebGateway($this->settings->get('greenweb_token')),
            'whatsapp_cloud' => new WhatsAppCloudGateway(
                $this->settings->get('whatsapp_phone_number_id'), $this->settings->get('whatsapp_token'),
                $this->settings->get('whatsapp_template'), $this->settings->get('whatsapp_language'),
            ),
            default => new LogGateway,
        };
    }

    /** The SMS text: "C-STAR: <title> — <body>". */
    public function compose(string $title, string $body): string
    {
        $prefix = trim($this->settings->get('prefix'));

        return trim(($prefix !== '' ? "{$prefix}: " : '').$title.($body !== '' ? " — {$body}" : ''));
    }

    public function send(string $channel, string $to, string $text, ?string $kind = null, ?User $user = null): OutboundMessage
    {
        $message = OutboundMessage::create([
            'channel' => $channel, 'driver' => $this->settings->get($channel === 'whatsapp' ? 'whatsapp_driver' : 'sms_driver'),
            'to' => $to, 'body' => $text, 'segments' => self::segments($text), 'kind' => $kind, 'user_id' => $user?->id, 'status' => 'queued',
        ]);

        return $this->deliver($message);
    }

    /** (Re)sends a recorded message — also used by "Send again" on a failed one. */
    public function deliver(OutboundMessage $message): OutboundMessage
    {
        $result = $this->gateway($message->channel)->send($message->to, $message->body);
        $message->update([
            'status' => $result->ok ? 'sent' : 'failed', 'provider_ref' => $result->ref, 'error' => $result->error ? mb_substr($result->error, 0, 500) : null,
            'response' => $result->raw, 'sent_at' => $result->ok ? now() : null,
        ]);

        return $message;
    }

    /** 01XXXXXXXXX, or null when the number cannot receive SMS. */
    public static function mobile(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if (str_starts_with($digits, '880')) {
            $digits = substr($digits, 2);
        }

        return preg_match('/^01[3-9]\d{8}$/', $digits) ? $digits : null;
    }

    /** SMS parts the gateway charges for: Bangla (Unicode) 70 / 67 characters, English 160 / 153. */
    public static function segments(string $text): int
    {
        $length = mb_strlen($text);
        if (preg_match('/[^\x00-\x7F]/', $text)) {
            return $length <= 70 ? 1 : (int) ceil($length / 67);
        }

        return $length <= 160 ? 1 : (int) ceil($length / 153);
    }
}
