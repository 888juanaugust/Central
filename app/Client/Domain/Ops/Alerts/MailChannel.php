<?php

declare(strict_types=1);

namespace App\Client\Domain\Ops\Alerts;

use App\Client\Domain\Ops\Health\OpsAlerter;
use App\Domain\Shared\Locales;
use Illuminate\Support\Facades\Mail;

/** The mail every active administrator and the extra address get, in the company's language. */
final class MailChannel implements AlertChannel
{
    public function name(): string
    {
        return 'mail';
    }

    public function send(Alert $alert): void
    {
        $recipients = OpsAlerter::recipients();
        if ($recipients === []) {
            return;
        }
        Locales::using(Locales::companyDefault(), fn () => Mail::to($recipients)->send($alert->mailable));
    }
}
