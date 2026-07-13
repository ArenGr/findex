<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Organizations serve customers across all three site locales
 * (config('localization.available')) - the profile description used to be
 * a single column, so every visitor saw whichever language it was written
 * in. Covers Organization::getDescriptionAttribute()'s per-locale
 * resolution and fallback, plus the dashboard edit form saving all three.
 */
class OrganizationLocalizedDescriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_page_shows_the_description_matching_the_current_locale(): void
    {
        $organization = Organization::create([
            'name' => 'Locale Test Bank',
            'slug' => 'locale-test-bank',
            'type' => 'bank',
            'country_code' => 'AM',
            'is_active' => true,
            'description_hy' => 'Հայերեն նկարագրություն',
            'description_en' => 'English description',
            'description_ru' => 'Русское описание',
        ]);

        $this->get("/en/organizations/{$organization->slug}")->assertSee('English description');
        $this->get("/hy/organizations/{$organization->slug}")->assertSee('Հայերեն նկարագրություն');
        $this->get("/ru/organizations/{$organization->slug}")->assertSee('Русское описание');
    }

    public function test_missing_locale_falls_back_to_default_locale_then_any_other(): void
    {
        $organization = Organization::create([
            'name' => 'Fallback Test Bank',
            'slug' => 'fallback-test-bank',
            'type' => 'bank',
            'country_code' => 'AM',
            'is_active' => true,
            'description_hy' => 'Հայերեն նկարագրություն', // default locale, no 'en'/'ru' written
        ]);

        // No English description was ever written - falls back to the
        // site's default locale (hy) rather than showing nothing.
        $this->get("/en/organizations/{$organization->slug}")->assertSee('Հայերեն նկարագրություն');
    }

    public function test_no_description_in_any_locale_renders_without_error(): void
    {
        $organization = Organization::create([
            'name' => 'No Description Bank',
            'slug' => 'no-description-bank',
            'type' => 'bank',
            'country_code' => 'AM',
            'is_active' => true,
        ]);

        $this->get("/en/organizations/{$organization->slug}")->assertOk();
    }

    public function test_dashboard_profile_update_saves_all_three_locale_descriptions(): void
    {
        $organization = Organization::create([
            'name' => 'Dashboard Bank',
            'slug' => 'dashboard-bank',
            'type' => 'bank',
            'country_code' => 'AM',
            'is_active' => true,
        ]);
        $user = User::factory()->organization($organization)->create();

        $this->actingAs($user, 'organization')->put('/en/org/dashboard/profile', [
            'name' => 'Dashboard Bank',
            'description_hy' => 'Նոր նկարագրություն',
            'description_en' => 'New description',
            'description_ru' => 'Новое описание',
        ])->assertRedirect();

        $organization->refresh();
        $this->assertSame('Նոր նկարագրություն', $organization->description_hy);
        $this->assertSame('New description', $organization->description_en);
        $this->assertSame('Новое описание', $organization->description_ru);
    }
}
