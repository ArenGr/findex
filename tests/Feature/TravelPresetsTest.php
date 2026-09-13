<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\QuoteRequest;
use App\Support\TravelPresets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

// The popular trips above the form.
class TravelPresetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_preset_carries_values_the_form_itself_allows(): void
    {
        $presets = TravelPresets::all();

        $this->assertNotEmpty($presets);

        foreach ($presets as $preset) {
            $this->assertContains($preset['country'], QuoteRequest::DESTINATIONS, $preset['key']);
            $this->assertContains($preset['flight'], QuoteRequest::FLIGHT_PREFERENCES, $preset['key']);
            $this->assertContains($preset['hotel'], QuoteRequest::HOTEL_PREFERENCES, $preset['key']);
            $this->assertContains($preset['meals'], QuoteRequest::MEAL_PREFERENCES, $preset['key']);

            $this->assertLessThanOrEqual(QuoteRequest::MAX_PRIORITIES, count($preset['priorities']), $preset['key']);

            foreach ($preset['priorities'] as $priority) {
                $this->assertContains($priority, QuoteRequest::PRIORITIES, $preset['key']);
            }
        }
    }

    public function test_preset_dates_are_far_enough_ahead_to_be_quotable(): void
    {
        foreach (TravelPresets::all() as $preset) {
            $checkIn = Carbon::parse($preset['check_in']);
            $checkOut = Carbon::parse($preset['check_out']);

            $this->assertTrue($checkIn->isFuture(), $preset['key']);
            $this->assertSame($preset['nights'], (int) $checkIn->diffInDays($checkOut), $preset['key']);
        }
    }

    public function test_a_preset_carries_a_typical_price_only_where_there_is_one(): void
    {
        $presets = TravelPresets::all(['EG' => 450000]);

        $egypt = collect($presets)->firstWhere('country', 'EG');
        $other = collect($presets)->first(fn ($preset) => $preset['country'] !== 'EG');

        $this->assertSame(450000, $egypt['typical_price']);
        $this->assertNull($other['typical_price']);
    }

    public function test_the_request_page_offers_the_presets(): void
    {
        $response = $this->get(route('tourism.request', ['locale' => 'en']));
        $response->assertOk();

        $response->assertSee(__('tourism.presets.heading'));

        foreach (TravelPresets::all() as $preset) {
            $response->assertSee($preset['title']);
        }
    }

    /** An agency covering the destination, so the request has somewhere to go. */
    private function agencyFor(string $countryCode): void
    {
        $organization = Organization::create([
            'name' => 'Preset Travel',
            'slug' => Str::slug('Preset Travel'),
            'type' => 'tourism',
            'country_code' => 'AM',
            'is_active' => true,
            'telegram_chat_id' => '1'.crc32($countryCode),
        ]);

        $organization->tourismDestinations()->create(['country_code' => $countryCode]);
    }

    public function test_a_preset_submits_with_nothing_added_but_contact_details(): void
    {
        Mail::fake();

        $preset = TravelPresets::all()[0];
        $this->agencyFor($preset['country']);

        $response = $this->post(route('tourism.request.store', ['locale' => 'en']), [
            'departure_location' => __('tourism.request.departure_default'),
            'destination_countries' => [$preset['country']],
            'check_in' => $preset['check_in'],
            'check_out' => $preset['check_out'],
            'adults' => $preset['adults'],
            'children' => 0,
            'flight_preference' => $preset['flight'],
            'hotel_preference' => $preset['hotel'],
            'meal_preference' => $preset['meals'],
            'priorities' => $preset['priorities'],
            'guest_name' => 'Test Person',
            'guest_email' => 'traveller@gmail.com',
            'consent' => '1',
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('quote_requests', [
            'guest_email' => 'traveller@gmail.com',
            'destination_country' => $preset['country'],
            'flight_preference' => $preset['flight'],
            'hotel_preference' => $preset['hotel'],
            'meal_preference' => $preset['meals'],
        ]);
    }
}
