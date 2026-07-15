<?php

namespace Tests\Feature;

use App\Mail\VerifyEmailAddress;
use App\Models\Organization;
use App\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Covers the whole verification loop: sending on registration, the
 * guard-agnostic signed link (see VerifyEmailController), resending per
 * guard, and the two places verification status is actually enforced -
 * an unverified org never gets matched to leads (see
 * Organization::tourismPartnersForDestination), and a logged-in-but-
 * unverified customer can't submit a quote request or review (guests are
 * unaffected either way).
 */
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function verificationUrl(User $user): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'locale' => 'en',
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);
    }

    public function test_registering_as_a_customer_sends_a_verification_email(): void
    {
        Mail::fake();

        $this->post('/en/register/customer', [
            'name' => 'Test Customer',
            'email' => 'new-customer@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect();

        $user = User::where('email', 'new-customer@example.com')->firstOrFail();
        $this->assertFalse($user->hasVerifiedEmail());
        Mail::assertSent(VerifyEmailAddress::class, fn ($mail) => $mail->user->is($user));
    }

    public function test_registering_an_organization_sends_a_verification_email(): void
    {
        Mail::fake();

        $this->post('/en/org/register', [
            'name' => 'New Test Agency',
            'email' => 'new-org@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'type' => 'tourism',
        ])->assertRedirect();

        $user = User::where('email', 'new-org@example.com')->firstOrFail();
        $this->assertFalse($user->hasVerifiedEmail());
        Mail::assertSent(VerifyEmailAddress::class, fn ($mail) => $mail->user->is($user));
    }

    public function test_a_valid_signed_link_marks_the_account_verified(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get($this->verificationUrl($user))->assertRedirect();

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_an_organization_accounts_link_redirects_to_the_org_dashboard(): void
    {
        $organization = Organization::create([
            'name' => 'Verify Test Agency', 'slug' => 'verify-test-agency', 'type' => 'tourism',
            'country_code' => 'AM', 'is_active' => true,
        ]);
        $user = User::factory()->unverified()->organization($organization)->create();

        $this->get($this->verificationUrl($user))
            ->assertRedirect(route('org.dashboard.index', ['locale' => 'en']));
    }

    public function test_a_tampered_hash_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'locale' => 'en', 'id' => $user->id, 'hash' => sha1('not-their-email'),
        ]);

        $this->get($url)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_an_unsigned_link_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get("/en/email/verify/{$user->id}/" . sha1($user->email))->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_clicking_an_already_verified_accounts_link_is_a_no_op(): void
    {
        $user = User::factory()->create();
        $verifiedAt = $user->email_verified_at;

        $this->get($this->verificationUrl($user))->assertRedirect();

        $this->assertTrue($verifiedAt->equalTo($user->fresh()->email_verified_at));
    }

    public function test_customer_can_resend_their_verification_email(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->post(route('verification.send', ['locale' => 'en']))
            ->assertRedirect();

        Mail::assertSent(VerifyEmailAddress::class, fn ($mail) => $mail->user->is($user));
    }

    public function test_organization_can_resend_their_verification_email(): void
    {
        Mail::fake();
        $organization = Organization::create([
            'name' => 'Resend Test Agency', 'slug' => 'resend-test-agency', 'type' => 'tourism',
            'country_code' => 'AM', 'is_active' => true,
        ]);
        $user = User::factory()->unverified()->organization($organization)->create();

        $this->actingAs($user, 'organization')
            ->post(route('org.verification.send', ['locale' => 'en']))
            ->assertRedirect();

        Mail::assertSent(VerifyEmailAddress::class, fn ($mail) => $mail->user->is($user));
    }

    public function test_resend_is_a_no_op_once_already_verified(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('verification.send', ['locale' => 'en']));

        Mail::assertNothingSent();
    }

    public function test_an_organization_with_no_verified_team_member_is_excluded_from_lead_matching(): void
    {
        $organization = Organization::create([
            'name' => 'Unverified Agency', 'slug' => 'unverified-agency', 'type' => 'tourism',
            'country_code' => 'AM', 'is_active' => true, 'telegram_chat_id' => '999',
        ]);
        $organization->tourismDestinations()->create(['country_code' => 'GE']);
        User::factory()->unverified()->organization($organization)->create();

        $matches = Organization::tourismPartnersForDestination('GE')->get();

        $this->assertFalse($matches->contains($organization));
    }

    public function test_an_organization_with_at_least_one_verified_team_member_is_matched(): void
    {
        $organization = Organization::create([
            'name' => 'Verified Agency', 'slug' => 'verified-agency', 'type' => 'tourism',
            'country_code' => 'AM', 'is_active' => true, 'telegram_chat_id' => '999',
        ]);
        $organization->tourismDestinations()->create(['country_code' => 'GE']);
        User::factory()->unverified()->organization($organization)->create();
        User::factory()->organization($organization)->create();

        $matches = Organization::tourismPartnersForDestination('GE')->get();

        $this->assertTrue($matches->contains($organization));
    }

    public function test_a_logged_in_unverified_customer_cannot_submit_a_quote_request(): void
    {
        $organization = Organization::create([
            'name' => 'Blocked Test Agency', 'slug' => 'blocked-test-agency', 'type' => 'tourism',
            'country_code' => 'AM', 'is_active' => true, 'telegram_chat_id' => '999',
        ]);
        $organization->tourismDestinations()->create(['country_code' => 'GE']);
        User::factory()->organization($organization)->create();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->post(route('tourism.request.store', ['locale' => 'en']), [
            'destination_country' => 'GE',
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(17)->toDateString(),
            'adults' => 2,
            'consent' => '1',
        ])->assertRedirect(route('tourism.request', ['locale' => 'en']));

        $this->assertSame(0, QuoteRequest::count());
    }

    public function test_a_verified_customer_can_submit_a_quote_request(): void
    {
        $organization = Organization::create([
            'name' => 'Allowed Test Agency', 'slug' => 'allowed-test-agency', 'type' => 'tourism',
            'country_code' => 'AM', 'is_active' => true, 'telegram_chat_id' => '999',
        ]);
        $organization->tourismDestinations()->create(['country_code' => 'GE']);
        User::factory()->organization($organization)->create();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('tourism.request.store', ['locale' => 'en']), [
            'destination_country' => 'GE',
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(17)->toDateString(),
            'adults' => 2,
            'consent' => '1',
        ])->assertRedirect();

        $this->assertSame(1, QuoteRequest::count());
    }

    public function test_a_guest_can_still_submit_a_quote_request_without_any_account(): void
    {
        $organization = Organization::create([
            'name' => 'Guest Test Agency', 'slug' => 'guest-test-agency', 'type' => 'tourism',
            'country_code' => 'AM', 'is_active' => true, 'telegram_chat_id' => '999',
        ]);
        $organization->tourismDestinations()->create(['country_code' => 'GE']);
        User::factory()->organization($organization)->create();

        $this->post(route('tourism.request.store', ['locale' => 'en']), [
            'destination_country' => 'GE',
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(17)->toDateString(),
            'adults' => 2,
            'guest_name' => 'Guest Person',
            'guest_email' => 'guestperson@example.com',
            'consent' => '1',
        ])->assertRedirect();

        $this->assertSame(1, QuoteRequest::count());
    }

    public function test_a_logged_in_unverified_customer_cannot_submit_a_review(): void
    {
        $organization = Organization::create([
            'name' => 'Review Blocked Agency', 'slug' => 'review-blocked-agency', 'type' => 'bank',
            'country_code' => 'AM', 'is_active' => true,
        ]);
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->post(route('reviews.store', ['locale' => 'en', 'organization' => $organization]), [
            'rating' => 5,
            'comment' => 'A perfectly fine comment about this bank.',
        ])->assertRedirect(route('organizations.show', ['locale' => 'en', 'organization' => $organization]));

        $this->assertSame(0, $organization->reviews()->count());
    }

    public function test_a_guest_can_still_submit_a_review_without_any_account(): void
    {
        $organization = Organization::create([
            'name' => 'Review Guest Agency', 'slug' => 'review-guest-agency', 'type' => 'bank',
            'country_code' => 'AM', 'is_active' => true,
        ]);

        $this->post(route('reviews.store', ['locale' => 'en', 'organization' => $organization]), [
            'rating' => 5,
            'comment' => 'A perfectly fine comment about this bank.',
            'guest_name' => 'Guest Reviewer',
        ])->assertRedirect();

        $this->assertSame(1, $organization->reviews()->count());
    }
}
