<?php

namespace App\Parsers;

interface BranchParser
{
    /**
     * Parse a bank's branch listing into normalized branch records.
     *
     * Only real branches - an ATM or a payment terminal standing in a
     * supermarket is not somewhere you can change money over a counter, and
     * the comparison pages treat every row here as a place you can walk into.
     *
     * `name` is what the bank calls the branch ("Avan branch"); where it
     * publishes no name, the address doubles as one. Coordinates and hours
     * are optional - plenty of banks publish an address and nothing else,
     * and a null is honest where a guess would not be.
     *
     * `opening_hours` follows App\Support\OpeningHours: a day mapped to null
     * means closed, and a day left out means unknown.
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
