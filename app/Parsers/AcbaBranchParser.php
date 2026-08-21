<?php

namespace App\Parsers;

use App\Support\OpeningHours;

class AcbaBranchParser implements BranchParser
{
    /**
     * ACBA's branch page is an Angular app, and it ships the data it
     * hydrated from in a <script id="ng-state"> block. The branches sit
     * nested province -> city -> branch:
     *
     *   {"<cache-key>":{"b":[
     *      {"title":"Yerevan","cities":[
     *          {"title":"Arabkir","branches":[
     *              {"title":"«Arabkir» Branch",
     *               "working_hours":"Monday-Friday: 09:30-17:30[n]Cash register service: 09:30-16:30",
     *               "address":"64a Sundukyan Street, 2/1",
     *               "latitude":"40.199","longitude":"44.500", ...}]}]}]}}
     *
     * The outer key is a generated cache id that changes between builds, so
     * the search is for any entry shaped like the branch tree rather than
     * for a fixed key.
     */
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

    /**
     * Yerevan is both a province and a city, so under it this tree's second
     * level holds districts - Arabkir, Kentron, Shengavit. Filing those as
     * cities would split the capital into ten "cities" in the branch filter
     * and leave no way to ask for Yerevan itself.
     */
    private function city(string $province, string $city): ?string
    {
        if ($province === self::CAPITAL) {
            return self::CAPITAL;
        }

        // A few entries carry an empty city; the province is the better
        // answer there than nothing at all.
        $name = $city !== '' ? $city : $province;

        return $name !== '' ? $name : null;
    }

    /**
     * Hours arrive as one string with a literal "[n]" where the line breaks
     * should be. The second line is usually the cash desk ("Cash register
     * service: 09:30-16:30"), which closes an hour before the branch does -
     * splitting the lines keeps OpeningHours from reading the two as one
     * rule.
     */
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
