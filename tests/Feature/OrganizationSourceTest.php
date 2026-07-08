<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationSourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_url_combines_website_and_relative_path(): void
    {
        $organization = Organization::create([
            'name' => 'Bank', 'slug' => 'bank', 'type' => 'bank',
            'country_code' => 'AM', 'is_active' => true, 'website' => 'https://bank.am/',
        ]);
        $source = OrganizationSource::create([
            'organization_id' => $organization->id, 'source_type' => 'currency_rates',
            'url' => '/rates', 'is_active' => true,
        ]);

        $this->assertSame('https://bank.am/rates', $source->getFullUrl());
    }

    public function test_full_url_passes_through_an_already_absolute_url(): void
    {
        $organization = Organization::create([
            'name' => 'Bank', 'slug' => 'bank', 'type' => 'bank',
            'country_code' => 'AM', 'is_active' => true, 'website' => null,
        ]);
        $source = OrganizationSource::create([
            'organization_id' => $organization->id, 'source_type' => 'currency_rates',
            'url' => 'https://bank.am/rates', 'is_active' => true,
        ]);

        $this->assertSame('https://bank.am/rates', $source->getFullUrl());
    }

    public function test_relative_url_without_a_website_fails_loudly_instead_of_erroring(): void
    {
        // website is intentionally nullable (self-registered orgs may not
        // have one yet) - a relative source URL is meaningless without it.
        $organization = Organization::create([
            'name' => 'Bank', 'slug' => 'bank', 'type' => 'bank',
            'country_code' => 'AM', 'is_active' => true, 'website' => null,
        ]);
        $source = OrganizationSource::create([
            'organization_id' => $organization->id, 'source_type' => 'currency_rates',
            'url' => '/rates', 'is_active' => true,
        ]);

        $this->expectException(\RuntimeException::class);

        $source->getFullUrl();
    }
}
