<?php

namespace App\Parsers;

use App\Support\OpeningHours;

class AmioBranchParser implements BranchParser
{
    public function parse(string $html): array
    {
        if (! preg_match('#<script id="__NEXT_DATA__"[^>]*>(.*?)</script>#s', $html, $match)) {
            return [];
        }

        $data = json_decode($match[1], true);
        $rows = data_get($data, 'props.pageProps.data.branches');

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
     * @param  array<string, mixed>  $row
     * @return array{name: string, address: string, city: ?string, latitude: ?float, longitude: ?float, opening_hours: array<string, array{0: string, 1: string}|null>|null}|null
     */
    private function buildBranch(array $row): ?array
    {
        $address = trim((string) ($row['address'] ?? ''));

        if ($address === '') {
            return null;
        }

        return [
            'name' => $this->name($address),
            'address' => $address,
            'city' => null,
            'latitude' => $this->coordinate($row['gpsLatitude'] ?? null),
            'longitude' => $this->coordinate($row['gpsLongitude'] ?? null),
            'opening_hours' => OpeningHours::fromDays($this->days($row['openingInfo'] ?? null)),
        ];
    }

    private function name(string $address): string
    {
        if (preg_match('/^\s*"([^"]+)"/', $address, $match)) {
            return trim($match[1]);
        }

        return $address;
    }

    /**
     * @return array<string, array{0: string, 1: string}|null>
     */
    private function days(mixed $openingInfo): array
    {
        if (! is_array($openingInfo)) {
            return [];
        }

        $days = [];

        foreach ($openingInfo as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $weekday = $entry['weekdayEn'] ?? null;

            if (! is_string($weekday)) {
                continue;
            }

            $days[$weekday] = ($entry['isClosed'] ?? false)
                ? null
                : [(string) ($entry['opensAt'] ?? ''), (string) ($entry['closesAt'] ?? '')];
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
