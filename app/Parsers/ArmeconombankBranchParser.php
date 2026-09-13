<?php

namespace App\Parsers;

use App\Support\OpeningHours;

class ArmeconombankBranchParser implements BranchParser
{
    private const BRANCH_KEY = 'branches';

    // The response numbers its days the way JavaScript's getDay() does, Sunday first.
    private const WEEKDAYS = [
        0 => 'sun', 1 => 'mon', 2 => 'tue', 3 => 'wed',
        4 => 'thu', 5 => 'fri', 6 => 'sat',
    ];

    public function parse(string $html): array
    {
        $data = json_decode($html, true);
        $points = data_get($data, self::BRANCH_KEY.'.points');

        if (! is_array($points)) {
            return [];
        }

        $branches = [];

        foreach ($points as $point) {
            $branch = is_array($point) ? $this->buildBranch($point) : null;

            if ($branch !== null) {
                $branches[] = $branch;
            }
        }

        return $branches;
    }

    /**
     * @param  array<string, mixed>  $point
     * @return array{name: string, address: string, city: ?string, latitude: ?float, longitude: ?float, opening_hours: array<string, array{0: string, 1: string}|null>|null}|null
     */
    private function buildBranch(array $point): ?array
    {
        $address = trim((string) ($point['Address'] ?? ''));

        if ($address === '') {
            return null;
        }

        $name = ucwords(mb_strtolower(trim((string) ($point['Name'] ?? ''))));

        return [
            'name' => $name !== '' ? $name : $address,
            'address' => $address,
            'city' => null,
            'latitude' => $this->coordinate($point['Latitude'] ?? null),
            'longitude' => $this->coordinate($point['Longitude'] ?? null),
            'opening_hours' => OpeningHours::fromDays($this->days($point['WorkingDays'] ?? null)),
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}|null>
     */
    private function days(mixed $workingDays): array
    {
        if (! is_array($workingDays)) {
            return [];
        }

        $days = [];

        foreach ($workingDays as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $day = self::WEEKDAYS[$entry['DayOfWeek'] ?? -1] ?? null;

            if ($day === null) {
                continue;
            }

            $from = $entry['WorkTimeFrom'] ?? null;
            $to = $entry['WorkTimeTo'] ?? null;

            // A holiday, or a day with no hours against it, is closed.
            $days[$day] = ($entry['IsHoliday'] ?? false) || $from === null || $to === null
                ? null
                : [(string) $from, (string) $to];
        }

        return $days;
    }

    private function coordinate(mixed $value): ?float
    {
        if (! is_numeric($value) || (float) $value === 0.0) {
            return null;
        }

        return (float) $value;
    }
}
