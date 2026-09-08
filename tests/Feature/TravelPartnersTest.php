<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Support\TravelPartners;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The "trusted by" strip.
 *
 * It makes a claim about real companies, so it is driven by partner records
 * rather than the design pack's mockup logos - those are invented brands. It
 * also has to disappear rather than look thin: a strip listing one agency
 * argues against itself.
 */
class TravelPartnersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('travel.partners');
    }

    private function makePartners(int $count): void
    {
        // Not range(1, $count): PHP counts *down* when the end is below the
        // start, so range(1, 0) is [1, 0] and "no partners" created two.
        foreach ($count > 0 ? range(1, $count) : [] as $n) {
            Organization::factory()->create([
                'type' => 'tourism',
                'name' => "Agency {$n}",
                'is_active' => true,
            ]);
        }

        Cache::forget('travel.partners');
    }

    public function test_the_strip_is_hidden_below_the_threshold(): void
    {
        $this->makePartners(TravelPartners::MIN_TO_SHOW - 1);

        $response = $this->get(route('tourism.request', ['locale' => 'en']));

        $response->assertOk();
        $response->assertDontSee(__('tourism.request.partners_heading'));
    }

    public function test_the_strip_appears_once_there_are_enough_partners(): void
    {
        $this->makePartners(TravelPartners::MIN_TO_SHOW);

        $response = $this->get(route('tourism.request', ['locale' => 'en']));

        $response->assertOk();
        $response->assertSee(__('tourism.request.partners_heading'));
        $response->assertSee('Agency 1');
        $response->assertSee('Agency '.TravelPartners::MIN_TO_SHOW);
    }

    public function test_only_active_tourism_organizations_are_listed(): void
    {
        $this->makePartners(3);
        Organization::factory()->create(['type' => 'bank', 'name' => 'A Bank', 'is_active' => true]);
        Organization::factory()->create(['type' => 'tourism', 'name' => 'Dormant Agency', 'is_active' => false]);
        Cache::forget('travel.partners');

        $response = $this->get(route('tourism.request', ['locale' => 'en']));

        $response->assertOk();
        $response->assertDontSee('A Bank');
        $response->assertDontSee('Dormant Agency');
    }

    public function test_a_partner_without_a_logo_falls_back_to_an_initial_never_a_stand_in_mark(): void
    {
        $this->makePartners(3);

        $partners = TravelPartners::all();

        $this->assertNull($partners->first()->logo);
        $this->assertSame('A', $partners->first()->initial);
    }
}
