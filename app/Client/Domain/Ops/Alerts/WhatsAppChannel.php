<?php

declare(strict_types=1);

namespace App\Client\Domain\Ops\Alerts;

/** The alert as one plain text to each configured number. */
final class WhatsAppChannel implements AlertChannel
{
    public function __construct(private readonly WhatsAppSender $sender) {}

    public function name(): string
    {
        return 'whatsapp';
    }

    public function send(Alert $alert): void
    {
        foreach (self::recipients() as $to) {
            $this->sender->send($to, $alert->text());
        }
    }

    public static function configured(): bool
    {
        return trim((string) config('ops.whatsapp.provider')) !== '' && self::recipients() !== [];
    }

    /** @return list<string> */
    public static function recipients(): array
    {
        $numbers = array_map(fn (string $n) => preg_replace('/[^\d+]/', '', trim($n)) ?? '', explode(',', (string) config('ops.whatsapp.recipients')));

        return array_values(array_unique(array_filter($numbers, fn (string $n) => $n !== '' && $n !== '+')));
    }
}
