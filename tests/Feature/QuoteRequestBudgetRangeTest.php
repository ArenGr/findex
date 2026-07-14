<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\QuoteRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteRequestBudgetRangeTest extends TestCase
{
    use RefreshDatabase;

    private function tourismPartner(): Organization
    {
        $organization = Organization::create([
            'name' => 'Budget Test Agency', 'slug' => 'budget-test-agency', 'type' => 'tourism',
            'country_code' => 'AM', 'is_active' => true, 'telegram_chat_id' => '123456',
        ]);
        $organization->tourismDestinations()->create(['country_code' => 'GE']);

        return $organization;
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'destination_country' => 'GE',
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(17)->toDateString(),
            'adults' => 2,
            'guest_name' => 'Test Guest',
            'guest_email' => 'guest@example.com',
            'consent' => '1',
        ], $overrides);
    }

    public function test_a_full_range_is_saved_on_the_request(): void
    {
        $this->tourismPartner();

        $this->post(route('tourism.request.store', ['locale' => 'en']), $this->validPayload([
            'budget_min_amd' => 800000,
            'budget_max_amd' => 1000000,
        ]))->assertRedirect();

        $quoteRequest = QuoteRequest::sole();
        $this->assertEquals(800000, $quoteRequest->budget_min_amd);
        $this->assertEquals(1000000, $quoteRequest->budget_max_amd);
    }

    public function test_budget_is_entirely_optional(): void
    {
        $this->tourismPartner();

        $this->post(route('tourism.request.store', ['locale' => 'en']), $this->validPayload())
            ->assertRedirect();

        $quoteRequest = QuoteRequest::sole();
        $this->assertNull($quoteRequest->budget_min_amd);
        $this->assertNull($quoteRequest->budget_max_amd);
    }

    public function test_max_below_min_is_rejected(): void
    {
        $this->tourismPartner();

        $response = $this->post(route('tourism.request.store', ['locale' => 'en']), $this->validPayload([
            'budget_min_amd' => 1000000,
            'budget_max_amd' => 500000,
        ]));

        $response->assertSessionHasErrors('budget_max_amd');
        $this->assertSame(0, QuoteRequest::count());
    }

    public function test_budget_for_filtering_prefers_max_over_min(): void
    {
        $quoteRequest = QuoteRequest::create([
            'guest_name' => 'Test Guest', 'guest_email' => 'guest@example.com', 'locale' => 'en',
            'destination_country' => 'GE', 'check_in' => now()->addDays(10), 'check_out' => now()->addDays(17),
            'adults' => 2, 'children' => 0, 'all_inclusive' => false, 'insurance' => false,
            'expires_at' => now()->addDays(14), 'budget_min_amd' => 500000, 'budget_max_amd' => 700000,
        ]);

        $this->assertEquals(700000, $quoteRequest->budget_for_filtering);
    }

    public function test_budget_for_filtering_falls_back_to_min_when_max_is_unset(): void
    {
        $quoteRequest = QuoteRequest::create([
            'guest_name' => 'Test Guest', 'guest_email' => 'guest@example.com', 'locale' => 'en',
            'destination_country' => 'GE', 'check_in' => now()->addDays(10), 'check_out' => now()->addDays(17),
            'adults' => 2, 'children' => 0, 'all_inclusive' => false, 'insurance' => false,
            'expires_at' => now()->addDays(14), 'budget_min_amd' => 500000,
        ]);

        $this->assertEquals(500000, $quoteRequest->budget_for_filtering);
    }
}
