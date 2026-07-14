<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuoteTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function tourismOrgUser(): array
    {
        $organization = Organization::create([
            'name' => 'Template Test Agency', 'slug' => 'template-test-agency-' . uniqid(), 'type' => 'tourism',
            'country_code' => 'AM', 'is_active' => true,
        ]);
        $user = User::factory()->organization($organization)->create();

        return [$organization, $user];
    }

    public function test_org_can_create_a_template(): void
    {
        [, $user] = $this->tourismOrgUser();

        $this->actingAs($user, 'organization')->post(route('org.dashboard.quote-templates.store', ['locale' => 'en']), [
            'name' => 'Standard Georgia Package',
            'destination_country' => 'GE',
            'price_amount' => 500,
            'price_currency' => 'USD',
            'offered_hotel_name' => 'Example Hotel',
        ])->assertRedirect();

        $this->assertDatabaseHas('quote_templates', [
            'name' => 'Standard Georgia Package',
            'destination_country' => 'GE',
        ]);
    }

    public function test_org_cannot_edit_another_orgs_template(): void
    {
        [$organization] = $this->tourismOrgUser();
        [, $otherUser] = $this->tourismOrgUser();
        $template = $organization->quoteTemplates()->create(['name' => 'Mine']);

        $this->actingAs($otherUser, 'organization')
            ->get(route('org.dashboard.quote-templates.edit', ['locale' => 'en', 'quoteTemplate' => $template->id]))
            ->assertNotFound();
    }

    public function test_org_can_delete_its_own_template(): void
    {
        [$organization, $user] = $this->tourismOrgUser();
        $template = $organization->quoteTemplates()->create(['name' => 'To delete']);

        $this->actingAs($user, 'organization')
            ->delete(route('org.dashboard.quote-templates.destroy', ['locale' => 'en', 'quoteTemplate' => $template->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('quote_templates', ['id' => $template->id]);
    }

    public function test_respond_page_only_shows_templates_matching_the_destination_or_generic(): void
    {
        $organization = Organization::create([
            'name' => 'Respond Template Agency', 'slug' => 'respond-template-agency', 'type' => 'tourism',
            'country_code' => 'AM', 'is_active' => true,
        ]);
        $organization->quoteTemplates()->create(['name' => 'Georgia Special', 'destination_country' => 'GE']);
        $organization->quoteTemplates()->create(['name' => 'Generic Offer', 'destination_country' => null]);
        $organization->quoteTemplates()->create(['name' => 'Thailand Special', 'destination_country' => 'TH']);

        $quoteRequest = QuoteRequest::create([
            'guest_name' => 'Test Guest', 'guest_email' => 'guest@example.com', 'locale' => 'en',
            'destination_country' => 'GE', 'check_in' => now()->addDays(10), 'check_out' => now()->addDays(17),
            'adults' => 2, 'children' => 0, 'all_inclusive' => false, 'insurance' => false,
            'expires_at' => now()->addDays(14),
        ]);
        $response = QuoteResponse::create([
            'quote_request_id' => $quoteRequest->id,
            'organization_id' => $organization->id,
            'response_token' => Str::random(40),
            'status' => QuoteResponse::STATUS_PENDING,
        ]);

        $page = $this->get(route('tourism.respond', ['locale' => 'en', 'token' => $response->response_token]));

        $page->assertSee('Georgia Special')->assertSee('Generic Offer')->assertDontSee('Thailand Special');
    }
}
