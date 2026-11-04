<?php

declare(strict_types=1);

namespace App\Client\Domain\Ops\Security;

use App\Client\Domain\Ops\Health\OpsStatus;

/**
 * One thing the security watch noticed: which rule, how serious, what it
 * says, and the subject (an address, an account, a document) that keeps the
 * same finding from being reported again within the throttle.
 */
final readonly class SecurityFinding
{
    public function __construct(public string $rule, public OpsStatus $severity, public string $title, public string $detail, public string $subject) {}

    public static function warning(string $rule, string $title, string $detail, string $subject): self
    {
        return new self($rule, OpsStatus::Warning, $title, $detail, $subject);
    }

    public static function critical(string $rule, string $title, string $detail, string $subject): self
    {
        return new self($rule, OpsStatus::Critical, $title, $detail, $subject);
    }

    public function line(): string
    {
        return $this->title.': '.$this->detail;
    }

    public function throttleKey(): string
    {
        return 'ops:security:'.$this->rule.':'.sha1($this->subject);
    }
}
