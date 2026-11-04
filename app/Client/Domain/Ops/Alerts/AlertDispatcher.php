<?php

declare(strict_types=1);

namespace App\Client\Domain\Ops\Alerts;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Hands one alert to every channel that is configured: mail always,
 * WhatsApp once a provider and numbers are set. Each channel fails on its
 * own, so a dead SMTP never silences the phone or the other way round.
 */
final class AlertDispatcher
{
    public function __construct(private readonly MailChannel $mail, private readonly WhatsAppChannel $whatsapp) {}

    /** @return list<AlertChannel> */
    public function channels(): array
    {
        $channels = [$this->mail];
        if (WhatsAppChannel::configured()) {
            $channels[] = $this->whatsapp;
        }

        return $channels;
    }

    /** @return list<string> the names of the channels that delivered */
    public function dispatch(Alert $alert): array
    {
        $delivered = [];
        foreach ($this->channels() as $channel) {
            try {
                $channel->send($alert);
                $delivered[] = $channel->name();
            } catch (Throwable $e) {
                Log::error('Alert channel failed', ['channel' => $channel->name(), 'alert' => $alert->kind, 'error' => $e->getMessage()]);
            }
        }

        return $delivered;
    }
}
