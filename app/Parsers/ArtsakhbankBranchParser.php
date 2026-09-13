<?php

namespace App\Parsers;

use App\Support\OpeningHours;
use Symfony\Component\DomCrawler\Crawler;

class ArtsakhbankBranchParser implements BranchParser
{
    public function parse(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $branches = [];

        (new Crawler($html))->filter('li[id^="branch-"]')->each(function (Crawler $node) use (&$branches) {
            $address = $this->text($node, '.street_block');

            if ($address === '') {
                return;
            }

            $name = $this->text($node, '.name_block');

            $branches[] = [
                'name' => $name !== '' ? $name : $address,
                'address' => $address,
                'city' => null,
                'latitude' => null,
                'longitude' => null,
                'opening_hours' => OpeningHours::parse($this->text($node, '.date_work')),
            ];
        });

        return $branches;
    }

    private function text(Crawler $node, string $selector): string
    {
        $found = $node->filter($selector);

        if ($found->count() === 0) {
            return '';
        }

        return trim(preg_replace('/\s+/u', ' ', $found->first()->text('')) ?? '');
    }
}
