<?php

namespace App\Services\Messaging;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * WhatsApp Business Cloud API (Meta). Messages to people who have not written first must use an approved
 * template; C-STAR uses one template with a single body parameter that carries the whole message.
 */
final class WhatsAppCloudGateway implements Gateway
{
    public const API = 'https://graph.facebook.com/v21.0';

    public function __construct(private string $phoneNumberId, private string $token, private string $template, private string $language) {}

    public function send(string $to, string $text): SendResult
    {
        if ($this->phoneNumberId === '' || $this->token === '') {
            return new SendResult(false, null, 'WhatsApp phone number ID or access token is not set.');
        }
        try {
            $response = Http::withToken($this->token)->timeout(20)->post(self::API."/{$this->phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => '88'.$to,
                'type' => 'template',
                'template' => [
                    'name' => $this->template,
                    'language' => ['code' => $this->language],
                    'components' => [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $text]]]],
                ],
            ]);
        } catch (Throwable $e) {
            return new SendResult(false, null, 'Could not reach WhatsApp: '.$e->getMessage());
        }
        $raw = mb_substr($response->body(), 0, 2000);

        return $response->successful() && ($id = $response->json('messages.0.id'))
            ? new SendResult(true, $id, null, $raw)
            : new SendResult(false, null, 'WhatsApp: '.($response->json('error.message') ?? "HTTP {$response->status()}"), $raw);
    }
}
