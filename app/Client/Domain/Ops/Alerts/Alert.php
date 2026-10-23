<?php

declare(strict_types=1);

namespace App\Client\Domain\Ops\Alerts;

use App\Client\Domain\Ops\Health\OpsCheck;
use App\Client\Domain\Ops\Health\OpsStatus;
use App\Client\Domain\Ops\Security\SecurityFinding;
use App\Client\Mail\SecurityAlertMessage;
use App\Client\Mail\SystemAlertMessage;
use Illuminate\Mail\Mailable;

/**
 * One thing somebody must be told, whatever the channel: a severity, a
 * title, the lines that say what was found, and the mail that carries it.
 * Health and security build theirs here; the channels never know which.
 */
final readonly class Alert
{
    /** @param  list<string>  $lines */
    public function __construct(
        public string $kind,
        public OpsStatus $severity,
        public string $title,
        public array $lines,
        public Mailable $mailable,
    ) {}

    /** @param  list<OpsCheck>  $failing */
    public static function health(array $failing): self
    {
        $worst = OpsStatus::Healthy;
        foreach ($failing as $check) {
            if ($check->status->worseThan($worst)) {
                $worst = $check->status;
            }
        }

        return new self(
            'health',
            $worst,
            __('System health'),
            array_map(fn (OpsCheck $c) => strtoupper($c->status->label()).': '.$c->title.'. '.$c->finding, $failing),
            new SystemAlertMessage($failing),
        );
    }

    /** @param  list<SecurityFinding>  $findings */
    public static function security(array $findings): self
    {
        $worst = OpsStatus::Healthy;
        foreach ($findings as $finding) {
            if ($finding->severity->worseThan($worst)) {
                $worst = $finding->severity;
            }
        }

        return new self(
            'security',
            $worst,
            __('Security watch'),
            array_map(fn (SecurityFinding $f) => $f->line(), $findings),
            new SecurityAlertMessage($findings),
        );
    }

    /** The alert as plain text, for a channel that carries no markup. */
    public function text(): string
    {
        return implode("\n", [
            '['.strtoupper($this->severity->label()).'] '.$this->title.' · '.config('app.name'),
            ...array_map(fn (string $l) => '- '.$l, $this->lines),
            (string) config('app.url'),
        ]);
    }
}
