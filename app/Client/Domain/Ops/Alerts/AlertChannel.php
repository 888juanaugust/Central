<?php

declare(strict_types=1);

namespace App\Client\Domain\Ops\Alerts;

/** A way to reach a person: mail, WhatsApp, whatever comes next. */
interface AlertChannel
{
    public function name(): string;

    /** Delivers or throws; the dispatcher keeps one channel's failure from the others. */
    public function send(Alert $alert): void;
}
