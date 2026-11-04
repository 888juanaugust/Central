<?php

declare(strict_types=1);

namespace App\Client\Mail;

use App\Client\Domain\Ops\Security\SecurityFinding;
use App\Domain\Company\CompanyIdentity;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The security watch's findings: one line each, and where to look. */
class SecurityAlertMessage extends Mailable
{
    /** @param  list<SecurityFinding>  $findings */
    public function __construct(public array $findings) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('[SECURITY] :company: :count finding(s)', ['company' => app(CompanyIdentity::class)->letterhead()['name'], 'count' => count($this->findings)]));
    }

    public function content(): Content
    {
        return new Content(text: 'client.mail.security-alert', with: ['company' => app(CompanyIdentity::class)->letterhead()['name']]);
    }
}
