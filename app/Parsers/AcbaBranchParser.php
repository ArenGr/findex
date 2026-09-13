<?php

namespace App\Parsers;

use App\Support\OpeningHours;

class AcbaBranchParser implements BranchParser
{
    private const CAPITAL = 'Yerevan';

    public function parse(string $html): array
    {
        if (! preg_match('#<script id="ng-state"[^>]*>(.*?)</script>#s', $html, $match)) {
            return [];
        }

        $state = json_decode(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5), true);

        if (! is_array($state)) {
            return [];
        }

        $branches = [];

        foreach ($this->provinces($state) as $province) {
            $provinceName = trim((string) ($province['title'] ?? ''));

            foreach ($province['cities'] ?? [] as $city) {
                if (! is_array($city)) {
                    continue;
                }

                foreach ($city['branches'] ?? [] as $branch) {
                    if (! is_array($branch)) {
                        continue;
                    }

                    $record = $this->buildBranch($branch, $provinceName, trim((string) ($city['title'] ?? '')));

                    if ($record !== null) {
                        $branches[] = $record;
                    }
                }
            }
        }

        return $branches;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<int, array<string, mixed>>
     */
    private function provinces(array $state): array
    {
        foreach ($state as $entry) {
            $provinces = $entry['b'] ?? null;

            if (is_array($provinces) && isset($provinces[0]['cities'])) {
                return $provinces;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $branch
     * @return array{name: string, address: string, city: ?string, latitude: ?float, longitude: ?float, opening_hours: array<string, array{0: string, 1: string}|null>|null}|null
     */
    private function buildBranch(array $branch, string $province, string $city): ?array
    {
        $address = trim((string) ($branch['address'] ?? ''));

        if ($address === '') {
            return null;
        }

        return [
            // The bank wraps branch names in guillemets: «Arabkir» Branch.
            'name' => trim(str_replace(['«', '»'], '', (string) ($branch['title'] ?? ''))) ?: $address,
            'address' => $address,
            'city' => $this->city($province, $city),
            'latitude' => $this->coordinate($branch['latitude'] ?? null),
            'longitude' => $this->coordinate($branch['longitude'] ?? null),
            'opening_hours' => OpeningHours::parse($this->hours($branch['working_hours'] ?? null)),
        ];
    }

    private function city(string $province, string $city): ?string
    {
        if ($province === self::CAPITAL) {
            return self::CAPITAL;
        }

        // A few entries carry an empty city; the province is the better answer there than nothing at all.
        $name = $city !== '' ? $city : $province;

        return $name !== '' ? $name : null;
    }

    // Hours arrive as one string with a literal "[n]" where the line breaks should be.
    private function hours(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return str_replace('[n]', "\n", $value);
    }

    private function coordinate(mixed $value): ?float
    {
        if (! is_numeric($value) || (float) $value === 0.0) {
            return null;
        }

        return (float) $value;
    }
}
