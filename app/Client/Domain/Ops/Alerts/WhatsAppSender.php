<?php

declare(strict_types=1);

namespace App\Client\Domain\Ops\Alerts;

/**
 * Sends one WhatsApp text to one number. The provider behind it (Fonnte,
 * Twilio, …) is chosen by ops.whatsapp.provider and bound in
 * ClientServiceProvider; until one is chosen, NullSender stands here.
 */
interface WhatsAppSender
{
    /** @param  string  $to  an E.164 number, digits only after the plus */
    public function send(string $to, string $text): void;
}
