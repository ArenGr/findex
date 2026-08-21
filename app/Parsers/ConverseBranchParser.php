<?php

namespace App\Parsers;

use App\Support\OpeningHours;

class ConverseBranchParser implements BranchParser
{
    /**
     * Converse publishes every location it has through one endpoint:
     *
     *   https://sapi.conversebank.am/api/v2/branches
     *
     *   [{"title":"Khudyakov 161/2","branch":"\"Avan\" branch",
     *     "body":"<p>Mon. - Fri.: 9:15-17:30</p>","phone":"+374 10 511 211",
     *     "lat":40.23,"lng":44.43,"type":"1","status":"1","city":11}, ...]
     *
     * `title` is the street address and `branch` the name - except for the
     * ATMs, where `branch` holds the host business ("Outside of 'Emmy'
     * florist's") rather than a branch of the bank at all.
     */
    private const BRANCH_TYPE = '1';

    private const ACTIVE = '1';

    public function parse(string $html): array
    {
        $rows = json_decode($html, true);

        if (! is_array($rows)) {
            return [];
        }

        $branches = [];

        foreach ($rows as $row) {
            $branch = is_array($row) ? $this->buildBranch($row) : null;

            if ($branch !== null) {
                $branches[] = $branch;
            }
        }

        return $branches;
    }

    /**
     * The response mixes branches in with 164 ATMs and payment terminals,
     * separated only by `type`. Types 2 and 3 stand in supermarkets, clinics
     * and florists' shops - real places, but not counters where anyone
     * changes money, and listing them as branches would roughly quintuple
     * this bank's apparent footprint.
     *
     * @param  array<string, mixed>  $row
     * @return array{name: string, address: string, city: ?string, latitude: ?float, longitude: ?float, opening_hours: array<string, array{0: string, 1: string}|null>|null}|null
     */
    private function buildBranch(array $row): ?array
    {
        if ((string) ($row['type'] ?? '') !== self::BRANCH_TYPE) {
            return null;
        }

        if ((string) ($row['status'] ?? self::ACTIVE) !== self::ACTIVE) {
            return null;
        }

        $address = trim((string) ($row['title'] ?? ''));

        if ($address === '') {
            return null;
        }

        // The bank wraps its branch names in literal quotes ("Avan" branch).
        $name = trim(str_replace('"', '', (string) ($row['branch'] ?? '')));

        return [
            'name' => $name !== '' ? $name : $address,
            'address' => $address,
            'city' => null,
            'latitude' => $this->coordinate($row['lat'] ?? null),
            'longitude' => $this->coordinate($row['lng'] ?? null),
            'opening_hours' => OpeningHours::parse($row['body'] ?? null),
        ];
    }

    /**
     * A 0 is not a coordinate anywhere this bank operates - it is the
     * default a missing value falls back to, and it would drop the branch
     * into the Gulf of Guinea on the "find nearby" map.
     */
    private function coordinate(mixed $value): ?float
    {
        if (! is_numeric($value) || (float) $value === 0.0) {
            return null;
        }

        return (float) $value;
    }
}
