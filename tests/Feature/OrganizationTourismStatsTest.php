<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the tourism performance stats card on the org dashboard overview
 * (response rate / avg response time) - only shown for tourism-type orgs,
 * and only once there's at least one lead.
 */
class OrganizationTourismStatsTest extends TestCase
{
    use RefreshDatabase;

    private function quoteResponse(Organization $organization, array $overrides = []): QuoteResponse
    {
        $quoteRequest = QuoteRequest::create([
            'guest_name' => 'Test Guest',
            'guest_email' => 'guest@example.com',
            'locale' => 'en',
            'destination_country' => 'GE',
            'check_in' => now()->addDays(10),
            'check_out' => now()->addDays(17),
            'adults' => 2,
            'children' => 0,
            'all_inclusive' => false,
            'insurance' => false,
            'expires_at' => now()->addDays(14),
        ]);

        $response = QuoteResponse::create(array_merge([
            'quote_request_id' => $quoteRequest->id,
            'organization_id' => $organization->id,
            'response_token' => Str::random(40),
            'status' => QuoteResponse::STATUS_PENDING,
        ], $overrides));

        // created_at isn't in QuoteResponse::$fillable (only touched
        // through normal Eloquent timestamps in real usage), so a
        // create()-time override is silently dropped - forceFill it
        // afterward to backdate it for a controlled response-time test.
        if (isset($overrides['created_at'])) {
            $response->forceFill(['created_at' => $overrides['created_at']])->save();
        }

        return $response;
    }

    public function test_tourism_org_sees_performance_stats_on_the_overview_page(): void
    {
        $organization = Organization::create([
            'name' => 'Stats Test Agency', 'slug' => 'stats-test-agency', 'type' => 'tourism',
            'country_code' => 'AM', 'is_active' => true,
        ]);
        $user = User::factory()->organization($organization)->create();

        $this->quoteResponse($organization, [
            'status' => QuoteResponse::STATUS_RESPONDED,
            'created_at' => now()->subHours(5),
            'responded_at' => now()->subHours(3),
        ]);
        $this->quoteResponse($organization, ['status' => QuoteResponse::STATUS_DECLINED]);
        $this->quoteResponse($organization, ['status' => QuoteResponse::STATUS_PENDING]);

        $response = $this->actingAs($user, 'organization')->get(route('org.dashboard.index', ['locale' => 'en']));

        $response->assertOk();
        $response->assertSee(__('org.overview.tourism_performance_heading'));
        $response->assertSee('3'); // total leads
        $response->assertSee('33%'); // 1 of 3 responded
        $response->assertSee(__('org.overview.tourism_hours', ['count' => 2])); // 5h - 3h = 2h avg
    }

    public function test_non_tourism_org_does_not_see_performance_stats(): void
    {
        $organization = Organization::create([
            'name' => 'Stats Test Bank', 'slug' => 'stats-test-bank', 'type' => 'bank',
            'country_code' => 'AM', 'is_active' => true,
        ]);
        $user = User::factory()->organization($organization)->create();

        $response = $this->actingAs($user, 'organization')->get(route('org.dashboard.index', ['locale' => 'en']));

        $response->assertOk();
        $response->assertDontSee(__('org.overview.tourism_performance_heading'));
    }

    public function test_tourism_org_with_no_leads_yet_sees_an_empty_state(): void
    {
        $organization = Organization::create([
            'name' => 'New Tourism Agency', 'slug' => 'new-tourism-agency', 'type' => 'tourism',
            'country_code' => 'AM', 'is_active' => true,
        ]);
        $user = User::factory()->organization($organization)->create();

        $response = $this->actingAs($user, 'organization')->get(route('org.dashboard.index', ['locale' => 'en']));

        $response->assertOk();
        $response->assertSee(__('org.overview.tourism_no_leads_yet'));
    }
}
