<?php

namespace App\Enums;

/**
 * Where a travel request stands. Only the first three are ever written to
 * quote_requests.status - EXPIRED is a function of expires_at rather than a
 * stored value, so it can never go stale between the clock passing and
 * something getting round to updating the row (see
 * QuoteRequest::currentStatus(), which is the only thing that should be
 * asked "what state is this request in").
 *
 * There is deliberately no "draft": nothing in the flow saves a
 * half-finished request, so a state nothing can reach would only be a
 * status string for views to handle and never hit.
 */
enum QuoteRequestStatus: string
{
    /** Sent to the matched agencies; none has answered yet. */
    case SUBMITTED = 'submitted';

    /** At least one agency has come back with an offer. */
    case OFFERS_RECEIVED = 'offers_received';

    /** Ended by the traveler before the clock ran out. */
    case CLOSED = 'closed';

    /** Past expires_at without being closed - derived, never stored. */
    case EXPIRED = 'expired';

    /**
     * Whether agencies can still reply. Both terminal states look the same
     * from an agency's side; only the reason differs.
     */
    public function isOpen(): bool
    {
        return $this === self::SUBMITTED || $this === self::OFFERS_RECEIVED;
    }

    public function label(): string
    {
        return __('tourism.status.'.$this->value);
    }

    /**
     * Tailwind classes for the status pill, so the same state can't end up
     * styled three different ways across the status page, the request list
     * and the agency inbox.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::SUBMITTED => 'bg-accent-yellow/20 text-ink',
            self::OFFERS_RECEIVED => 'bg-primary/10 text-primary',
            self::CLOSED, self::EXPIRED => 'bg-placeholder/40 text-subtle',
        };
    }
}
