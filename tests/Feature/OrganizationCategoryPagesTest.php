<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationCategoryPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_banks_page_lists_only_active_bank_organizations(): void
    {
        Organization::create(['name' => 'Real Bank', 'slug' => 'real-bank', 'type' => 'bank', 'country_code' => 'AM', 'is_active' => true]);
        Organization::create(['name' => 'Inactive Bank', 'slug' => 'inactive-bank', 'type' => 'bank', 'country_code' => 'AM', 'is_active' => false]);
        Organization::create(['name' => 'Some Agency', 'slug' => 'some-agency', 'type' => 'tourism', 'country_code' => 'AM', 'is_active' => true]);

        $response = $this->get('/en/banks/all');

        $response->assertOk();
        $response->assertSee('Real Bank');
        $response->assertDontSee('Inactive Bank');
        $response->assertDontSee('Some Agency');
    }

    /** An insurer with a review average, so the orderings have something to differ on. */
    private function insurerRated(string $name, string $slug, int $rating, int $reviews): Organization
    {
        $organization = Organization::create([
            'name' => $name, 'slug' => $slug, 'type' => 'insurance',
            'country_code' => 'AM', 'is_active' => true,
        ]);

        for ($i = 0; $i < $reviews; $i++) {
            Review::create([
                'organization_id' => $organization->id,
                'user_id' => User::factory()->create()->id,
                'rating' => $rating,
                'comment' => 'Review '.$i,
            ]);
        }

        return $organization;
    }

    public function test_the_insurance_listing_can_be_reordered(): void
    {
        $this->insurerRated('Aaa Insurance', 'aaa-insurance', 3, 1);
        $this->insurerRated('Zzz Insurance', 'zzz-insurance', 5, 4);

        $first = fn (string $sort) => strpos(
            $this->get('/en/insurance/companies?sort='.$sort)->assertOk()->getContent(),
            'Zzz Insurance',
        ) < strpos(
            $this->get('/en/insurance/companies?sort='.$sort)->getContent(),
            'Aaa Insurance',
        );

        $this->assertTrue($first('rated'), 'best rated should lead');
        $this->assertTrue($first('reviewed'), 'most reviewed should lead');
        $this->assertFalse($first('name'), 'A-Z should put Aaa first');
    }

    public function test_an_unknown_insurance_sort_falls_back_rather_than_erroring(): void
    {
        $this->insurerRated('Aaa Insurance', 'aaa-insurance', 4, 1);

        $this->get('/en/insurance/companies?sort='.urlencode("' OR 1=1"))->assertOk();
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
        $response = $this->get('/en/banks/all');

        $response->assertOk();
        $response->assertSee(__('meta.banks_title'));
    }

    public function test_travel_agencies_page_has_its_own_meta_title(): void
    {
        $response = $this->get('/en/travel-agencies');

        $response->assertOk();
        $response->assertSee(__('meta.travel_agencies_title'));
    }

    public function test_insurance_companies_page_lists_only_active_insurance_organizations(): void
    {
        Organization::create(['name' => 'Real Insurer', 'slug' => 'real-insurer', 'type' => 'insurance', 'country_code' => 'AM', 'is_active' => true]);
        Organization::create(['name' => 'Inactive Insurer', 'slug' => 'inactive-insurer', 'type' => 'insurance', 'country_code' => 'AM', 'is_active' => false]);
        Organization::create(['name' => 'Real Bank', 'slug' => 'real-bank', 'type' => 'bank', 'country_code' => 'AM', 'is_active' => true]);

        $response = $this->get('/en/insurance/companies');

        $response->assertOk();
        $response->assertSee('Real Insurer');
        $response->assertDontSee('Inactive Insurer');
        $response->assertDontSee('Real Bank');
    }

    public function test_insurance_companies_page_has_its_own_meta_title(): void
    {
        $response = $this->get('/en/insurance/companies');

        $response->assertOk();
        $response->assertSee(__('meta.insurance_companies_title'));
    }
}
