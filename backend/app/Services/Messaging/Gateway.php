<?php

namespace App\Services\Messaging;

/** An SMS or WhatsApp provider. */
interface Gateway
{
    /** @param  string  $to  01XXXXXXXXX */
    public function send(string $to, string $text): SendResult;
}
