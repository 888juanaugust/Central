<?php

declare(strict_types=1);

namespace App\Client\Domain\Ops\Health;

use App\Client\Domain\Ops\Alerts\Alert;
use App\Client\Domain\Ops\Alerts\AlertDispatcher;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The hourly sweep: when anything is critical, one alert per incident to
 * every configured channel (the mail to every active administrator and the
 * extra address, WhatsApp once a provider is set), throttled for the
 * configured hours and re-armed the moment the box is no longer critical.
 * When the cache that holds the throttle is itself down, the alert goes
 * anyway: that is the incident.
 */
class OpsAlerter
{
    public const THROTTLE_KEY = 'ops:alert-throttled';

    public function __construct(private readonly OpsHealth $health, private readonly AlertDispatcher $dispatcher) {}

    /** @return bool whether an alert went out */
    public function sweep(): bool
    {
        if ($this->health->worst() !== OpsStatus::Critical) {
            try {
                Cache::forget(self::THROTTLE_KEY);
            } catch (Throwable) {
            }

            return false;
        }
        if (! $this->claimTheIncident()) {
            return false;
        }
        $failing = $this->health->failing();
        $delivered = $this->dispatcher->dispatch(Alert::health($failing));
        Log::warning('System health alert sent', ['channels' => $delivered, 'to' => self::recipients(), 'findings' => array_map(fn (OpsCheck $c) => "{$c->key}: {$c->finding}", $failing)]);

        return true;
    }

    /** Every active administrator's address, plus the configured extra one, each once. @return list<string> */
    public static function recipients(): array
    {
        $addresses = User::query()->where('access_type', 'administrator')->where('is_active', true)->pluck('email')->all();
        $addresses[] = (string) config('ops.alert.extra_email');

        return array_values(array_unique(array_filter(array_map(fn ($a) => trim((string) $a), $addresses), fn (string $a) => $a !== '')));
    }

    private function claimTheIncident(): bool
    {
        try {
            return Cache::add(self::THROTTLE_KEY, time(), now()->addHours((int) config('ops.alert.throttle_hours')));
        } catch (Throwable) {
            return true;
        }
    }
}
