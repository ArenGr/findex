<?php

namespace App\Parsers;

use App\Support\OpeningHours;
use Symfony\Component\DomCrawler\Crawler;

abstract class MapBoxBranchParser implements BranchParser
{
    private const BRANCH_GROUP = 'bank-branches';

    public function parse(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $branches = [];

        (new Crawler($html))->filter('.map-box__info')->each(function (Crawler $node) use (&$branches) {
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
