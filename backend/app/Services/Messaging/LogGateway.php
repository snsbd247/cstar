<?php

namespace App\Services\Messaging;

use Illuminate\Support\Facades\Log;

/** Test mode: nothing leaves the server; the message is written to the log and counted as sent. */
final class LogGateway implements Gateway
{
    public function send(string $to, string $text): SendResult
    {
        Log::info("[SMS test mode] to {$to}: {$text}");

        return new SendResult(true, 'test-'.uniqid(), null, 'logged (test mode)');
    }
}
