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
 * Covers surfacing an org's contact info (entered on the response form, see
 * PartnerResponseController::store) to the customer on the results page -
 * the step after a customer sees an offer they like, and to the org on
 * their own dashboard.
 */
class TourismContactInfoTest extends TestCase
{
    use RefreshDatabase;

    private function respondedRequest(array $contactOverrides = []): QuoteRequest
    {
        $organization = Organization::create([
            'name' => 'Contact Test Agency', 'slug' => 'contact-test-agency-' . uniqid(), 'type' => 'tourism',
            'country_code' => 'AM', 'is_active' => true,
        ]);

        $quoteRequest = QuoteRequest::create([
            'guest_name' => 'Test Guest', 'guest_email' => 'guest@example.com', 'locale' => 'en',
            'destination_country' => 'GE', 'check_in' => now()->addDays(10), 'check_out' => now()->addDays(17),
            'adults' => 2, 'children' => 0, 'all_inclusive' => false, 'insurance' => false,
            'expires_at' => now()->addDays(14),
        ]);

        $response = QuoteResponse::create(array_merge([
            'quote_request_id' => $quoteRequest->id,
            'organization_id' => $organization->id,
            'response_token' => Str::random(40),
            'status' => QuoteResponse::STATUS_RESPONDED,
            'responded_at' => now(),
        ], $contactOverrides));

        $response->suggestions()->create(['price_amount' => 400000, 'price_currency' => 'AMD']);

        return $quoteRequest;
    }

    public function test_results_page_shows_contact_buttons_when_org_provided_them(): void
    {
        $quoteRequest = $this->respondedRequest([
            'contact_phone' => '+37499123456',
            'contact_whatsapp' => '+37499123456',
            'contact_telegram' => 'my_agency',
            'contact_instagram' => 'my_agency',
        ]);
        $user = User::factory()->create();
        $quoteRequest->update(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('tourism.show', ['locale' => 'en', 'quoteRequest' => $quoteRequest->id]));

        $response->assertOk();
        $response->assertSee(__('tourism.results.contact_heading'));
        $response->assertSee('tel:+37499123456', false);
        $response->assertSee('https://wa.me/37499123456', false);
        $response->assertSee('https://t.me/my_agency', false);
        $response->assertSee('https://instagram.com/my_agency', false);
    }

    public function test_results_page_hides_contact_section_when_org_provided_nothing(): void
    {
        $quoteRequest = $this->respondedRequest();
        $user = User::factory()->create();
        $quoteRequest->update(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('tourism.show', ['locale' => 'en', 'quoteRequest' => $quoteRequest->id]));

        $response->assertOk();
        $response->assertDontSee(__('tourism.results.contact_heading'));
    }

    public function test_org_dashboard_shows_the_contact_info_it_saved(): void
    {
        $quoteRequest = $this->respondedRequest([
            'contact_phone' => '+37499123456',
            'contact_telegram' => '@my_agency',
        ]);
        $organization = $quoteRequest->responses->first()->organization;
        $user = User::factory()->organization($organization)->create();

        $response = $this->actingAs($user, 'organization')->get(route('org.dashboard.tourism.index', ['locale' => 'en']));

        $response->assertOk();
        $response->assertSee('+37499123456');
        $response->assertSee('@my_agency');
    }
}
