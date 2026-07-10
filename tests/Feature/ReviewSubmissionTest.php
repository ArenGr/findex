<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function organization(): Organization
    {
        return Organization::create([
            'name' => 'Test Bank',
            'slug' => 'test-bank',
            'type' => 'bank',
            'country_code' => 'AM',
            'is_active' => true,
        ]);
    }

    public function test_guest_can_submit_a_review_with_a_display_name(): void
    {
        $organization = $this->organization();

        $response = $this->post(route('reviews.store', ['locale' => 'en', 'organization' => $organization]), [
            'rating' => 4,
            'comment' => 'Friendly staff and a solid mobile app.',
            'guest_name' => 'Nara K.',
        ]);

        $response->assertRedirect(route('organizations.show', ['locale' => 'en', 'organization' => $organization]));

        $review = Review::sole();
        $this->assertNull($review->user_id);
        $this->assertSame('Nara K.', $review->guest_name);
        $this->assertSame('Nara K.', $review->reviewer_name);
    }

    public function test_guest_submission_without_a_name_fails_validation(): void
    {
        $organization = $this->organization();

        $response = $this->post(route('reviews.store', ['locale' => 'en', 'organization' => $organization]), [
            'rating' => 4,
            'comment' => 'Missing a name on purpose.',
        ]);

        $response->assertSessionHasErrors('guest_name');
        $this->assertSame(0, Review::count());
    }

    public function test_honeypot_field_silently_discards_the_submission(): void
    {
        $organization = $this->organization();

        $response = $this->post(route('reviews.store', ['locale' => 'en', 'organization' => $organization]), [
            'rating' => 5,
            'comment' => 'This should never be saved.',
            'guest_name' => 'Bot',
            'company' => 'Acme Corp',
        ]);

        $response->assertRedirect(route('organizations.show', ['locale' => 'en', 'organization' => $organization]));
        $this->assertSame(0, Review::count());
    }

    public function test_authenticated_user_review_is_updated_not_duplicated_on_resubmit(): void
    {
        $organization = $this->organization();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('reviews.store', ['locale' => 'en', 'organization' => $organization]), [
            'rating' => 3,
            'comment' => 'First impression, still learning the app.',
        ])->assertRedirect(route('organizations.show', ['locale' => 'en', 'organization' => $organization]));

        $this->actingAs($user)->post(route('reviews.store', ['locale' => 'en', 'organization' => $organization]), [
            'rating' => 5,
            'comment' => 'Updating after using it for a month - much better.',
        ])->assertRedirect(route('organizations.show', ['locale' => 'en', 'organization' => $organization]));

        $this->assertSame(1, Review::where('organization_id', $organization->id)->count());
        $review = Review::sole();
        $this->assertSame($user->id, $review->user_id);
        $this->assertSame(5, $review->rating);
        $this->assertNull($review->guest_name);
    }

    public function test_guest_reviews_are_rate_limited_per_ip(): void
    {
        $organization = $this->organization();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('reviews.store', ['locale' => 'en', 'organization' => $organization]), [
                'rating' => 5,
                'comment' => "Review number {$i} from the same visitor.",
                'guest_name' => "Visitor {$i}",
            ])->assertRedirect(route('organizations.show', ['locale' => 'en', 'organization' => $organization]));
        }

        $this->post(route('reviews.store', ['locale' => 'en', 'organization' => $organization]), [
            'rating' => 5,
            'comment' => 'This one should be throttled.',
            'guest_name' => 'Visitor 6',
        ])->assertStatus(429);

        $this->assertSame(5, Review::count());
    }
}
