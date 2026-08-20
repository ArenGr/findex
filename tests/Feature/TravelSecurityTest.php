<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use App\Models\QuoteSuggestion;
use App\Models\User;
use App\Support\SafeRedirectUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The security properties of the travel flow that are easy to regress
 * silently: where an offer attachment lives and who may fetch it, and
 * whether a browser-supplied URL can bounce a visitor off the site.
 */
class TravelSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private QuoteRequest $quoteRequest;

    private QuoteResponse $response;

    private QuoteSuggestion $offer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();

        $this->quoteRequest = QuoteRequest::create([
            'user_id' => $this->owner->id,
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
            'name' => 'Attachment Agency', 'slug' => 'attachment-agency', 'type' => 'tourism',
            'country_code' => 'AM', 'is_active' => true, 'telegram_chat_id' => '1',
        ]);

        $this->response = QuoteResponse::create([
            'quote_request_id' => $this->quoteRequest->id,
            'organization_id' => $organization->id,
            'response_token' => Str::random(40),
            'status' => QuoteResponse::STATUS_RESPONDED,
            'responded_at' => now(),
        ]);

        $this->offer = $this->response->suggestions()->create([
            'price_amount' => 500000,
            'price_currency' => 'AMD',
            'offered_hotel_name' => 'Test Hotel',
        ]);
    }

    private function attachFile(): void
    {
        Storage::fake('local');

        $path = UploadedFile::fake()->create('quote.pdf', 12, 'application/pdf')->store('quote-attachments');

        $this->offer->forceFill(['attachment_path' => $path])->save();
    }

    /* -----------------------------------------------------------------
     * Attachments
     * ----------------------------------------------------------------- */

    /**
     * The point of the whole change: a quote attachment is one traveller's
     * pricing, so it must not sit on the public disk where its URL works
     * forever for anyone who ever receives it.
     */
    public function test_an_uploaded_attachment_is_not_stored_on_the_public_disk(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $organization = Organization::create([
            'name' => 'Uploading Agency', 'slug' => 'uploading-agency', 'type' => 'tourism',
            'country_code' => 'AM', 'is_active' => true,
        ]);
        $organization->tourismDestinations()->create(['country_code' => 'GE']);
        $agent = User::factory()->organization($organization)->create();

        $pending = QuoteResponse::create([
            'quote_request_id' => $this->quoteRequest->id,
            'organization_id' => $organization->id,
            'response_token' => Str::random(40),
            'status' => QuoteResponse::STATUS_PENDING,
        ]);

        $this->actingAs($agent, 'organization')->post(
            route('org.dashboard.travel-requests.offer.store', ['locale' => 'en', 'response' => $pending->id]),
            [
                'suggestions' => [[
                    'price_amount' => '700000',
                    'price_currency' => 'AMD',
                    'attachment' => UploadedFile::fake()->create('quote.pdf', 12, 'application/pdf'),
                ]],
            ]
        )->assertRedirect();

        $stored = QuoteSuggestion::whereNotNull('attachment_path')->sole();

        $this->assertTrue(Storage::disk('local')->exists($stored->attachment_path));
        $this->assertFalse(Storage::disk('public')->exists($stored->attachment_path));
    }

    public function test_the_owner_can_download_an_attachment(): void
    {
        $this->attachFile();

        $this->actingAs($this->owner)
            ->get($this->quoteRequest->signedUrlFor('tourism.offers.attachment', ['suggestion' => $this->offer->id]))
            ->assertOk()
            ->assertDownload();
    }

    public function test_a_stranger_cannot_download_an_attachment(): void
    {
        $this->attachFile();

        $this->actingAs(User::factory()->create())
            ->get(route('tourism.offers.attachment', [
                'locale' => 'en',
                'quoteRequest' => $this->quoteRequest->id,
                'suggestion' => $this->offer->id,
            ]))
            ->assertForbidden();
    }

    public function test_an_unsigned_anonymous_request_cannot_download_an_attachment(): void
    {
        $this->attachFile();

        $this->get(route('tourism.offers.attachment', [
            'locale' => 'en',
            'quoteRequest' => $this->quoteRequest->id,
            'suggestion' => $this->offer->id,
        ]))->assertForbidden();
    }

    /**
     * The response token is one agency's credential - it must not reach
     * another agency's file by guessing a suggestion id.
     */
    public function test_an_agency_token_cannot_fetch_another_agencys_attachment(): void
    {
        $this->attachFile();

        $otherOrganization = Organization::create([
            'name' => 'Other Agency', 'slug' => 'other-agency', 'type' => 'tourism',
            'country_code' => 'AM', 'is_active' => true, 'telegram_chat_id' => '2',
        ]);

        $otherResponse = QuoteResponse::create([
            'quote_request_id' => $this->quoteRequest->id,
            'organization_id' => $otherOrganization->id,
            'response_token' => Str::random(40),
            'status' => QuoteResponse::STATUS_RESPONDED,
            'responded_at' => now(),
        ]);

        $this->get(route('tourism.respond.attachment', [
            'locale' => 'en',
            'token' => $otherResponse->response_token,
            'suggestion' => $this->offer->id,
        ]))->assertNotFound();
    }

    public function test_an_agency_can_download_its_own_attachment(): void
    {
        $this->attachFile();

        $this->get(route('tourism.respond.attachment', [
            'locale' => 'en',
            'token' => $this->response->response_token,
            'suggestion' => $this->offer->id,
        ]))->assertOk()->assertDownload();
    }

    /* -----------------------------------------------------------------
     * Open redirect
     * ----------------------------------------------------------------- */

    /**
     * Referer is browser-supplied. Honouring it unchecked turns a link that
     * starts on this domain into one that silently lands somewhere else.
     */
    public function test_selecting_an_offer_ignores_an_off_site_referer(): void
    {
        $response = $this->actingAs($this->owner)->post(
            route('tourism.offers.select', [
                'locale' => 'en',
                'quoteRequest' => $this->quoteRequest->id,
                'suggestion' => $this->offer->id,
            ]),
            [],
            ['referer' => 'https://evil.test/phish'],
        );

        $response->assertRedirect();
        $this->assertStringNotContainsString('evil.test', $response->headers->get('Location'));
    }

    public function test_selecting_an_offer_honours_an_on_site_referer(): void
    {
        $onSite = route('tourism.offers', ['locale' => 'en', 'quoteRequest' => $this->quoteRequest->id]);

        $this->actingAs($this->owner)->post(
            route('tourism.offers.select', [
                'locale' => 'en',
                'quoteRequest' => $this->quoteRequest->id,
                'suggestion' => $this->offer->id,
            ]),
            [],
            ['referer' => $onSite],
        )->assertRedirect($onSite);
    }

    /**
     * The guard itself, including the shapes that look relative but aren't.
     */
    public function test_the_redirect_guard_rejects_every_off_site_shape(): void
    {
        $request = Request::create('https://findex.am/en/tourism', 'GET');

        foreach ([
            'https://evil.test/x',
            '//evil.test/x',
            'https://findex.am.evil.test/x',
            'javascript:alert(1)',
            'data:text/html,x',
            'en/relative-without-slash',
            '',
            null,
        ] as $candidate) {
            $this->assertSame(
                'FALLBACK',
                SafeRedirectUrl::resolve($request, $candidate, 'FALLBACK'),
                'Expected '.var_export($candidate, true).' to be refused.',
            );
        }

        $this->assertSame('/en/ok', SafeRedirectUrl::resolve($request, '/en/ok', 'FALLBACK'));
        $this->assertSame('https://findex.am/x', SafeRedirectUrl::resolve($request, 'https://findex.am/x', 'FALLBACK'));
    }
}
