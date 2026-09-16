<?php

namespace App\Enums;

/**
 * Where a visa support request stands. EXPIRED is derived from expires_at and
 * never stored - see VisaRequest::currentStatus().
 */
enum VisaRequestStatus: string
{
    case SUBMITTED = 'submitted';
    case OFFERS_RECEIVED = 'offers_received';
    case CLOSED = 'closed';
    case EXPIRED = 'expired';

    public function isOpen(): bool
    {
        return $this === self::SUBMITTED || $this === self::OFFERS_RECEIVED;
    }

    public function label(): string
    {
        return __('visa.status.'.$this->value);
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::SUBMITTED => 'bg-primary/10 text-primary',
            self::OFFERS_RECEIVED => 'bg-primary text-white',
            self::CLOSED, self::EXPIRED => 'bg-placeholder/40 text-muted',
        };
    }
}
