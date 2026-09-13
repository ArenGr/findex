<?php

namespace App\Services\Insurance;

use LogicException;

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
