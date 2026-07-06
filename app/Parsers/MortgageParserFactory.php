<?php

namespace App\Parsers;

use App\Models\Organization;

class MortgageParserFactory
{
    /**
     * Map of organization slug => parser class. Add an entry here (and a
     * matching parser class) for each new organization we support.
     *
     * @var array<string, class-string<MortgageParser>>
     */
    private array $parsers = [
        'acba' => AcbaMortgageParser::class,
        'ameria' => AmeriaMortgageParser::class,
    ];

    /**
     * Resolve the parser for a given organization.
     *
     * @throws \InvalidArgumentException when no parser is configured.
     */
    public function for(Organization $organization): MortgageParser
    {
        $slug = $organization->slug;

        if (!isset($this->parsers[$slug])) {
            throw new \InvalidArgumentException("No mortgage parser configured for organization '{$slug}'.");
        }

        return app($this->parsers[$slug]);
    }
}
