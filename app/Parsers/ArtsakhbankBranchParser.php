<?php

namespace App\Parsers;

use App\Support\OpeningHours;
use Symfony\Component\DomCrawler\Crawler;

class ArtsakhbankBranchParser implements BranchParser
{
    /**
     * Artsakhbank renders its branches server-side as a plain list:
     *
     *   <li id="branch-2" data-city="armenia">
     *     <div class="name_block">Head Office</div>
     *     <div class="street_block"><a>1b, Charents Str., Yerevan 0025, RA</a></div>
     *     <div class="phone_info">...</div>
     *     <div class="date_work"><span>Monday - Friday: 09:00 - 18:00</span></div>
     *   </li>
     *
     * A short list - this is a small bank, and the page carries no
     * coordinates, so its branches are stored with an address and hours but
     * no position on the map.
     */
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
                // data-city holds "armenia" or "kotayk" - a country and a
                // province, neither of which is the city this sits in. The
                // address names it, but pulling it out of free text would be
                // guesswork.
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
