<?php

namespace App\Parsers;

use App\Models\Organization;

class BranchParserFactory
{
    /**
     * Map of organization slug => branch parser class. Mirrors
     * RateParserFactory; a bank appears here only once its branch listing
     * has a parser, which is not the same set as the banks we take rates
     * from.
     *
     * @var array<string, class-string<BranchParser>>
     */
    private array $parsers = [
        'acba' => AcbaBranchParser::class,
        'aeb' => ArmeconombankBranchParser::class,
        'amio' => AmioBranchParser::class,
        'araratbank' => AraratbankBranchParser::class,
        'artsakhbank' => ArtsakhbankBranchParser::class,
        'evoca' => EvocaBranchParser::class,
        'unibank' => UnibankBranchParser::class,
        'conversebank' => ConverseBranchParser::class,
    ];

    /**
     * @throws \InvalidArgumentException when no parser is configured.
     */
    public function for(Organization $organization): BranchParser
    {
        $slug = $organization->slug;

        if (! isset($this->parsers[$slug])) {
            throw new \InvalidArgumentException("No branch parser configured for organization '{$slug}'.");
        }

        return app($this->parsers[$slug]);
    }

    public function supports(Organization $organization): bool
    {
        return isset($this->parsers[$organization->slug]);
    }
}
