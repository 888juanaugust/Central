<?php

declare(strict_types=1);

namespace App\Client\Domain\Ops\Alerts;

use Illuminate\Support\Facades\Log;

/** No provider yet: the message goes to the debug log and nowhere else. */
final class NullSender implements WhatsAppSender
{
    public function send(string $to, string $text): void
    {
        Log::debug('WhatsApp alert not sent: no provider configured', ['to' => $to, 'text' => $text]);
    }
}
