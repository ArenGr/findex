<?php

namespace App\Parsers;

use App\Models\Organization;

class RateParserFactory
{
    /**
     * Map of organization slug => parser class. Add an entry here (and a
     * matching parser class) for each new organization we support.
     *
     * @var array<string, class-string<RateParser>>
     */
    private array $parsers = [
        'acba'    => AcbaRateParser::class,
        'ineco'   => InecoRateParser::class,
        'ameria'  => AmeriaRateParser::class,
        'unibank' => UnibankRateParser::class,
    ];

    /**
     * Resolve the parser for a given organization.
     *
     * @throws \InvalidArgumentException when no parser is configured.
     */
    public function for(Organization $organization): RateParser
    {
        $slug = $organization->slug;

        if (!isset($this->parsers[$slug])) {
            throw new \InvalidArgumentException("No rate parser configured for organization '{$slug}'.");
        }

        return app($this->parsers[$slug]);
    }
}
