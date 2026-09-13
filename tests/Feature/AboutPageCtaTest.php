<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AboutPageCtaTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_sign_up_cta(): void
    {
        $response = $this->get('/en/about');

        $response->assertSee(__('about.cta.button'));
        $response->assertDontSee(__('about.cta.button_authenticated'));
    }

    public function test_authenticated_user_does_not_see_sign_up_cta(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/en/about');

        $response->assertDontSee(__('about.cta.button'));
        $response->assertSee(__('about.cta.button_authenticated'));
    }
}
