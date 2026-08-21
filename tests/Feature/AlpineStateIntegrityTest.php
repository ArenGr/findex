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
 * These pages hold their whole Alpine component in an x-data="{...}"
 * attribute, which fails in a way nothing else here would catch: a single
 * double quote inside it - in a string, or just in a code comment - closes
 * the attribute early. The page still returns 200 and still looks right,
 * but every property defined past that point is silently dropped and the
 * browser throws "X is not defined" on first interaction.
 *
 * So these tests assert the attribute survives rendering intact, and that
 * the properties the markup actually reads are still in it.
 */
class AlpineStateIntegrityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The x-data attribute of the first element on the page that has one
     * containing $marker, read the way a browser would: up to the first
     * unescaped double quote.
     */
    private function alpineState(string $html, string $marker): string
    {
        $markerPosition = strpos($html, $marker);

        $this->assertNotFalse($markerPosition, "Expected to find {$marker} in the rendered page.");

        $start = strrpos(substr($html, 0, $markerPosition + strlen($marker)), 'x-data="');
        $this->assertNotFalse($start, "Expected {$marker} to sit inside an x-data attribute.");

        $start += strlen('x-data="');

        return substr($html, $start, strpos($html, '"', $start) - $start);
    }

    private function assertStateIsComplete(string $state, array $expectedProperties): void
    {
        // Balanced braces is the tell-tale: a truncated attribute always
        // ends mid-object, so it can never close everything it opened.
        $this->assertSame(
            substr_count($state, '{'),
            substr_count($state, '}'),
            'The x-data object was cut short - most likely a double quote inside the attribute.'
        );

        foreach ($expectedProperties as $property) {
            $this->assertStringContainsString($property, $state);
        }
    }

    /**
     * The branch filter on an organization page holds its whole component in
     * the attribute - search, region, open-now and the show-all toggle. A
     * stray double quote there takes the filtering down silently, and with it
     * the only route to the branches past the sixth.
     */
    public function test_the_branch_filters_state_survives_rendering(): void
    {
        $organization = Organization::create([
            'name' => 'Acba', 'slug' => 'acba', 'type' => 'bank',
            'country_code' => 'AM', 'is_active' => true,
        ]);

        foreach (range(1, 8) as $n) {
            $organization->branches()->create([
                'name' => "Branch {$n}",
                'address' => "{$n} Test Street",
                'city' => 'Yerevan',
                'is_active' => true,
                'opening_hours' => ['mon' => ['09:00', '17:00']],
            ]);
        }

        $html = $this->get('/en/organizations/acba')->assertOk()->getContent();

        $state = $this->alpineState($html, 'openNow');

        $this->assertStateIsComplete($state, ['search', 'city', 'openNow', 'expanded', 'preview', 'refresh']);

        // A bare "<" inside the attribute is legal HTML but reads as a tag to
        // anything parsing the page roughly, so the comparison is written the
        // other way round.
        $this->assertStringNotContainsString('<', $state, 'The x-data attribute must not contain a raw "<".');
    }

    /**
     * The request form's behaviour now lives in a JS module and the
     * attribute only carries its config as JSON (see
     * resources/js/travel-request-form.js). That removes most of the
     * truncation risk - Blade's @js() escapes quotes - but the config still
     * has to arrive whole, because a summary panel bound to a half-parsed
     * config fails exactly as silently as before.
     */
    public function test_the_request_forms_state_survives_rendering(): void
    {
        $html = $this->get(route('tourism.request', ['locale' => 'en']))->assertOk()->getContent();

        $state = $this->alpineState($html, 'travelRequestForm(');

        $this->assertStateIsComplete($state, [
            'maxDestinations',
            'maxPriorities',
            'childAges',
            'dateFlexibility',
            // Last key in the config - if the JSON were cut short anywhere,
            // this is what would go missing.
            'labels',
        ]);
    }

    public function test_the_offers_pages_state_survives_rendering(): void
    {
        $owner = User::factory()->create();

        $quoteRequest = QuoteRequest::create([
            'user_id' => $owner->id,
            'locale' => 'en',
            'destination_country' => 'GE',
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(17)->toDateString(),
            'adults' => 2,
            'children' => 0,
            'insurance' => false,
            'expires_at' => now()->addDays(14),
        ]);

        $organization = Organization::create([
            'name' => 'Alpine Test Agency', 'slug' => 'alpine-test-agency', 'type' => 'tourism',
            'country_code' => 'AM', 'is_active' => true, 'telegram_chat_id' => '1',
        ]);

        $response = QuoteResponse::create([
            'quote_request_id' => $quoteRequest->id,
            'organization_id' => $organization->id,
            'response_token' => Str::random(40),
            'status' => QuoteResponse::STATUS_RESPONDED,
            'responded_at' => now(),
        ]);

        $response->suggestions()->create([
            'price_amount' => 500000, 'price_currency' => 'AMD', 'offered_hotel_name' => 'Test Hotel',
        ]);

        $html = $this->actingAs($owner)
            ->get(route('tourism.offers', ['locale' => 'en', 'quoteRequest' => $quoteRequest->id]))
            ->assertOk()
            ->getContent();

        $this->assertStateIsComplete($this->alpineState($html, 'selected:'), ['toggle', 'compareUrl']);
    }

    public function test_the_agency_offer_forms_state_survives_rendering(): void
    {
        $organization = Organization::create([
            'name' => 'Alpine Form Agency', 'slug' => 'alpine-form-agency', 'type' => 'tourism',
            'country_code' => 'AM', 'is_active' => true,
        ]);
        $organization->tourismDestinations()->create(['country_code' => 'GE']);

        $user = User::factory()->organization($organization)->create();

        $quoteRequest = QuoteRequest::create([
            'guest_name' => 'Guest', 'guest_email' => 'guest@example.com', 'locale' => 'en',
            'destination_country' => 'GE',
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(17)->toDateString(),
            'adults' => 2, 'children' => 0, 'insurance' => false,
            'expires_at' => now()->addDays(14),
        ]);

        $assignment = QuoteResponse::create([
            'quote_request_id' => $quoteRequest->id,
            'organization_id' => $organization->id,
            'response_token' => Str::random(40),
            'status' => QuoteResponse::STATUS_PENDING,
        ]);

        $html = $this->actingAs($user, 'organization')
            ->get(route('org.dashboard.travel-requests.show', ['locale' => 'en', 'response' => $assignment->id]))
            ->assertOk()
            ->getContent();

        $this->assertStateIsComplete(
            $this->alpineState($html, 'maxSuggestions:'),
            ['addSuggestion', 'removeSuggestion', 'applyTemplate']
        );
    }
}
