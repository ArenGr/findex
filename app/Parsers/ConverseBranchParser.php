<?php

namespace App\Parsers;

use App\Support\OpeningHours;

class ConverseBranchParser implements BranchParser
{
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
     * The response mixes branches in with 164 ATMs and payment terminals, separated only by `type`.
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

    private function coordinate(mixed $value): ?float
    {
        if (! is_numeric($value) || (float) $value === 0.0) {
            return null;
        }

        return (float) $value;
    }
}
