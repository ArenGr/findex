<?php

namespace App\Services\Insurance;

use LogicException;

/**
 * Shared handling for the values in this namespace that must never be
 * written down: the vehicle owner's ID number, and the bank account number
 * Sil Insurance requires (QuoteIdentity, MarketQuoteDetails).
 *
 * Every route by which a value normally escapes a PHP process is closed off
 * here, so a new carrier class gets the whole set by using this trait rather
 * than by remembering four separate things:
 *
 *   - __serialize() throws rather than redacting. serialize() is what a
 *     queued job's payload is built from, and that payload is written to the
 *     jobs table in plain text. Failing loudly means "quote in the
 *     background" gets caught while someone is writing it, instead of
 *     quietly starting to log ID numbers to the database.
 *   - __debugInfo() and jsonSerialize() cover dd(), var_dump(), a Log call
 *     with this in the context array, and anything that JSON-encodes its way
 *     into a response or a log line.
 *   - __toString() covers string interpolation into a message.
 *
 * Stack traces are handled separately, by #[\SensitiveParameter] on the
 * constructor arguments of the using class - that is where these values
 * would otherwise surface first, and travel to Sentry attached to an
 * exception that had nothing to do with insurance.
 */
trait RedactsSensitiveValues
{
    private const REDACTED = '[redacted]';

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        throw new LogicException(
            static::class.' must not be serialized: it would be written to the queue or session in plain text. '
            .'Pass it through a synchronous call instead.'
        );
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return array_map(
            fn ($value) => $value === null ? null : self::REDACTED,
            get_object_vars($this),
        );
    }

    /**
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        return $this->__debugInfo();
    }

    public function __toString(): string
    {
        return self::REDACTED;
    }
}
