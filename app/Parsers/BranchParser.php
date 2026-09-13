<?php

namespace App\Parsers;

interface BranchParser
{
    /**
     * Parse a bank's branch listing into normalized branch records.
     *
     * @return array<int, array{
     *     name: string,
     *     address: string,
     *     city: ?string,
     *     latitude: ?float,
     *     longitude: ?float,
     *     opening_hours: array<string, array{0: string, 1: string}|null>|null,
     * }>
     */
    public function parse(string $html): array;
}
