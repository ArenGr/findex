<?php

namespace Tests\Feature;

use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the dedicated /banks and /travel-agencies SEO landing pages (see
 * OrganizationController::categoryPage()) - each is scoped to a single
 * organization type, unlike the generic /organizations directory's ?type=
 * filter.
 */
class OrganizationCategoryPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_banks_page_lists_only_active_bank_organizations(): void
    {
        Organization::create(['name' => 'Real Bank', 'slug' => 'real-bank', 'type' => 'bank', 'country_code' => 'AM', 'is_active' => true]);
        Organization::create(['name' => 'Inactive Bank', 'slug' => 'inactive-bank', 'type' => 'bank', 'country_code' => 'AM', 'is_active' => false]);
        Organization::create(['name' => 'Some Agency', 'slug' => 'some-agency', 'type' => 'tourism', 'country_code' => 'AM', 'is_active' => true]);

        $response = $this->get('/en/banks');

        $response->assertOk();
        $response->assertSee('Real Bank');
        $response->assertDontSee('Inactive Bank');
        $response->assertDontSee('Some Agency');
    }

    public function test_travel_agencies_page_lists_only_active_tourism_organizations(): void
    {
        Organization::create(['name' => 'Real Bank', 'slug' => 'real-bank', 'type' => 'bank', 'country_code' => 'AM', 'is_active' => true]);
        Organization::create(['name' => 'Sunny Travel', 'slug' => 'sunny-travel', 'type' => 'tourism', 'country_code' => 'AM', 'is_active' => true]);

        $response = $this->get('/en/travel-agencies');

        $response->assertOk();
        $response->assertSee('Sunny Travel');
        $response->assertDontSee('Real Bank');
    }

    public function test_banks_page_has_its_own_meta_title_distinct_from_the_generic_directory(): void
    {
        $response = $this->get('/en/banks');

        $response->assertOk();
        $response->assertSee(__('meta.banks_title'));
    }

    public function test_travel_agencies_page_has_its_own_meta_title(): void
    {
        $response = $this->get('/en/travel-agencies');

        $response->assertOk();
        $response->assertSee(__('meta.travel_agencies_title'));
    }
}
