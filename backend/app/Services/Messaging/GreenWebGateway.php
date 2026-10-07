<?php

namespace App\Services\Messaging;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * GreenWeb BulkSMS (bdbulksms.com / api.greenweb.com.bd). POST token + to + message; "?json" returns a JSON
 * list with a status per number ("SENT" on success). The plain-text reply ("Ok: …" / "Error: …") is
 * understood too, in case an account answers in text.
 */
final class GreenWebGateway implements Gateway
{
    public const SEND_URL = 'https://api.greenweb.com.bd/api.php?json';

    public const BALANCE_URL = 'https://api.greenweb.com.bd/g_api.php';

    public function __construct(private string $token) {}

    public function send(string $to, string $text): SendResult
    {
        if ($this->token === '') {
            return new SendResult(false, null, 'GreenWeb token is not set (Settings → SMS & WhatsApp).');
        }
        try {
            $response = Http::asForm()->timeout(20)->post(self::SEND_URL, ['token' => $this->token, 'to' => $to, 'message' => $text]);
        } catch (Throwable $e) {
            return new SendResult(false, null, 'Could not reach GreenWeb: '.$e->getMessage());
        }
        $raw = mb_substr($response->body(), 0, 2000);
        if (! $response->successful()) {
            return new SendResult(false, null, "GreenWeb HTTP {$response->status()}", $raw);
        }

        $json = json_decode($response->body(), true);
        if (is_array($json)) {
            $first = array_is_list($json) ? ($json[0] ?? []) : $json;
            $status = strtoupper((string) ($first['status'] ?? ''));
            $message = (string) ($first['statusmsg'] ?? $first['message'] ?? $first['msg'] ?? '');

            return $status === 'SENT' || $status === 'SUCCESS'
                ? new SendResult(true, (string) ($first['id'] ?? $first['msgid'] ?? '') ?: null, null, $raw)
                : new SendResult(false, null, trim("GreenWeb: {$status} {$message}") ?: 'GreenWeb refused the message', $raw);
        }

        $body = trim($response->body());

        return str_starts_with(strtolower($body), 'ok')
            ? new SendResult(true, null, null, $raw)
            : new SendResult(false, null, 'GreenWeb: '.mb_substr($body, 0, 200), $raw);
    }

    /** Remaining balance as GreenWeb reports it (plain text), or null when it cannot be read. */
    public function balance(): ?string
    {
        try {
            $response = Http::timeout(15)->get(self::BALANCE_URL, ['token' => $this->token, 'balance' => '']);

            return $response->successful() ? trim(strip_tags($response->body())) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
