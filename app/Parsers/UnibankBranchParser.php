<?php

namespace App\Parsers;

use App\Support\OpeningHours;
use Symfony\Component\DomCrawler\Crawler;

class UnibankBranchParser implements BranchParser
{
    private const ADDRESS_LABEL = 'address';

    private const HOURS_LABELS = ['work days', 'working days', 'work hours'];

    public function parse(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $branches = [];

        (new Crawler($html))->filter('.accordion')->each(function (Crawler $accordion) use (&$branches) {
            $city = $this->city($accordion);

            $accordion->filter('.branches__card')->each(function (Crawler $card) use (&$branches, $city) {
                $rows = $this->rows($card);
                $address = $rows[self::ADDRESS_LABEL] ?? '';

                if ($address === '') {
                    return;
                }

                $hours = null;

                foreach (self::HOURS_LABELS as $label) {
                    $hours ??= OpeningHours::parse($rows[$label] ?? null);
                }

                $branches[] = [
                    // Names are published in quotes and in capitals: "HEAD OFFICE".
                    'name' => $this->name($card) ?: $address,
                    'address' => $address,
                    'city' => $city,
                    'latitude' => null,
                    'longitude' => null,
                    'opening_hours' => $hours,
                ];
            });
        });

        return $branches;
    }

    /** @return array<string, string> */
    private function rows(Crawler $card): array
    {
        $rows = [];

        $card->filter('.branches__row')->each(function (Crawler $row) use (&$rows) {
            $label = $row->filter('.branches__row-label');
            $value = $row->filter('.branches__row-value');

            if ($label->count() === 0 || $value->count() === 0) {
                return;
            }

            $rows[mb_strtolower(trim($label->first()->text('')))] = trim(
                preg_replace('/\s+/u', ' ', $value->first()->text('')) ?? ''
            );
        });

        return $rows;
    }

    private function name(Crawler $card): string
    {
        $title = $card->filter('.branches__card-title');

        if ($title->count() === 0) {
            return '';
        }

        $name = trim(str_replace('"', '', $title->first()->text('')));

        return $name !== '' ? ucwords(mb_strtolower($name)) : '';
    }

    private function city(Crawler $accordion): ?string
    {
        $button = $accordion->filter('.accordion__button');

        if ($button->count() === 0) {
            return null;
        }

        $city = trim(preg_replace('/\s+/u', ' ', $button->first()->text('')) ?? '');

        return $city !== '' ? ucwords(mb_strtolower($city)) : null;
    }
}
