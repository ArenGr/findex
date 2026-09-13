<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// The wizard shows one step at a time.
class TravelWizardSliderTest extends TestCase
{
    use RefreshDatabase;

    private function page(): string
    {
        $response = $this->get(route('tourism.request', ['locale' => 'en']));
        $response->assertOk();

        return $response->getContent();
    }

    public function test_each_step_shows_and_hides_by_display(): void
    {
        $html = $this->page();

        foreach ([1, 2, 3] as $step) {
            $this->assertStringContainsString('x-ref="slide'.$step.'"', $html);
            $this->assertStringContainsString(
                "{ 'flex': step === ".$step.", 'hidden': step !== ".$step.' }',
                $html,
            );
        }
    }

    public function test_only_the_opening_step_is_on_screen_in_the_first_frame(): void
    {
        $html = $this->page();

        $this->assertSame(1, substr_count($html, 'travel-step w-full flex-col gap-4 flex'));
        $this->assertSame(2, substr_count($html, 'travel-step w-full flex-col gap-4 hidden'));
    }

    public function test_no_step_is_left_stacked_on_another_at_opacity_zero(): void
    {
        $html = $this->page();

        $this->assertStringNotContainsString('absolute inset-x-0 top-0 opacity-0', $html);
        $this->assertStringNotContainsString('slideHeight', $html);
        $this->assertStringNotContainsString('sliderReady', $html);
        $this->assertStringNotContainsString('slideClass(', $html);
    }

    public function test_a_rejected_field_reopens_its_own_step_and_only_that_step(): void
    {
        $html = $this->from(route('tourism.request', ['locale' => 'en']))
            ->post(route('tourism.request.store', ['locale' => 'en']), [
                'departure_location' => 'Yerevan',
                'open_to_suggestions' => '1',
                'check_in' => now()->addMonth()->toDateString(),
                'check_out' => now()->addMonth()->addWeek()->toDateString(),
                'adults' => 2,
                'children' => 0,
                'budget_min_amd' => 900000,
                'budget_max_amd' => 1000,
                'guest_name' => 'Test Person',
                'guest_email' => 'someone@gmail.com',
                'consent' => '1',
            ])
            ->assertRedirect()
            ->getSession();

        $page = $this->get(route('tourism.request', ['locale' => 'en']));
        $page->assertOk();
        $content = $page->getContent();

        $this->assertSame(1, substr_count($content, 'travel-step w-full flex-col gap-4 flex'));
        $this->assertSame(2, substr_count($content, 'travel-step w-full flex-col gap-4 hidden'));

        // ...and it is step 2 that is the one shown.
        $shown = [];
        foreach (array_slice(explode('data-step="', $content), 1) as $chunk) {
            if (str_contains($chunk, 'travel-step w-full flex-col gap-4 flex')) {
                $shown[] = $chunk[0];
            }
        }

        $this->assertSame(['2'], $shown);
    }

    public function test_the_hero_is_painted_on_every_step(): void
    {
        $html = $this->page();

        // The hero is the page's head on all three steps.
        $this->assertStringContainsString(__('tourism.request.hero_line1'), $html);
        $this->assertSame(1, substr_count($html, '<h1'));

        $this->assertStringContainsString('border-b border-placeholder', $html);
        $this->assertStringNotContainsString("'lg:-mt-[78px]': step === 1", $html);
    }

    public function test_the_step_that_arrives_is_animated_in_on_its_own(): void
    {
        $this->assertStringContainsString('travel-step', $this->page());
        $this->assertStringContainsString(
            'animation: travel-step-in',
            file_get_contents(resource_path('css/app.css')),
        );
    }

    public function test_motion_is_dropped_for_anyone_who_asked_for_less_of_it(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertMatchesRegularExpression(
            '/@media \(prefers-reduced-motion: reduce\) \{\s*\.travel-step \{\s*animation: none;/',
            $css,
        );
    }
}
