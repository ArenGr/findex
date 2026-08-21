<?php

namespace App\Parsers;

use App\Support\OpeningHours;
use Symfony\Component\DomCrawler\Crawler;

abstract class MapBoxBranchParser implements BranchParser
{
    /**
     * Two banks - Araratbank and Evoca - run the same CMS theme and publish
     * their locations through identical markup, so the reading of it lives
     * here once:
     *
     *   <div class="map-box__info" data-lat="40.27" data-lng="44.63"
     *        data-groupBy="bank-branches" data-city="kotayq">
     *     <h3 class="map-box__inner-title">Abovyan branch</h3>
     *     <ul class="map-box__inner-list">
     *       <li class="...--tel">+37460 37-67-13</li>
     *       <li class="...--location">1/21 Hanrapetutyan St.</li>
     *       <li class="...--working-days"><p>Monday-Friday 09:00-17:00</p></li>
     *     </ul>
     *   </div>
     *
     * Every kind of location shares that markup, so the group attribute is
     * what separates them - on Araratbank, 50 branches against 130 ATMs, 56
     * payment terminals and one exchange point.
     *
     * The two banks differ only in what they put in the hours line, which is
     * left to OpeningHours: Araratbank names its days, while most Evoca
     * entries print bare times with no day at all.
     */
    private const BRANCH_GROUP = 'bank-branches';

    public function parse(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $branches = [];

        (new Crawler($html))->filter('.map-box__info')->each(function (Crawler $node) use (&$branches) {
            // Lowercase: the page writes data-groupBy, but HTML attribute
            // names are case-insensitive and the DOM hands them back folded,
            // so asking for the camelCase spelling matches nothing.
            if ($node->attr('data-groupby') !== self::BRANCH_GROUP) {
                return;
            }

            $branch = $this->buildBranch($node);

            if ($branch !== null) {
                $branches[] = $branch;
            }
        });

        return $branches;
    }

    /**
     * @return array{name: string, address: string, city: ?string, latitude: ?float, longitude: ?float, opening_hours: array<string, array{0: string, 1: string}|null>|null}|null
     */
    private function buildBranch(Crawler $node): ?array
    {
        $address = $this->itemText($node, '--location');

        if ($address === '') {
            return null;
        }

        $name = trim($node->filter('.map-box__inner-title')->first()->text(''));

        return [
            'name' => $name !== '' ? $name : $address,
            'address' => $address,
            'city' => $this->city($node->attr('data-city')),
            'latitude' => $this->coordinate($node->attr('data-lat')),
            'longitude' => $this->coordinate($node->attr('data-lng')),
            'opening_hours' => OpeningHours::parse($this->itemText($node, '--working-days')),
        ];
    }

    private function itemText(Crawler $node, string $modifier): string
    {
        $item = $node->filter(".map-box__inner-list-item{$modifier}");

        return $item->count() > 0 ? trim(preg_replace('/\s+/u', ' ', $item->first()->text('')) ?? '') : '';
    }

    /**
     * Published lowercase and transliterated ("kotayq", "yerevan"), which
     * would show as-is in the branch filter beside other banks' properly
     * cased names.
     */
    private function city(?string $city): ?string
    {
        $city = trim((string) $city);

        return $city !== '' ? ucfirst($city) : null;
    }

    private function coordinate(?string $value): ?float
    {
        if ($value === null || ! is_numeric($value) || (float) $value === 0.0) {
            return null;
        }

        return (float) $value;
    }
}
