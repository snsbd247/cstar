<?php

namespace App\Services\Messaging;

/** What a gateway reports back for one message. */
final class SendResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?string $ref = null,
        public readonly ?string $error = null,
        public readonly ?string $raw = null,
    ) {}
}
