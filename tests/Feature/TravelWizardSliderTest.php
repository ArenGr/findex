<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The wizard shows one step at a time.
 *
 * This used to be a carousel: all three steps absolutely positioned on top of
 * one another, the inactive ones at opacity 0, inside a box whose height was
 * measured and animated between them. It read as the first step growing to
 * take in the second one's fields rather than as moving to the next screen,
 * and it crossfaded two different forms over each other on the way.
 *
 * These assert the structure that replaced it, because the failure mode is a
 * quiet one: any of it can be undone and three working steps still render -
 * they just stop replacing each other.
 */
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
            // Object form, so Alpine takes away whichever of the two the
            // server printed once it no longer applies.
            $this->assertStringContainsString(
                "{ 'flex': step === ".$step.", 'hidden': step !== ".$step.' }',
                $html,
            );
        }
    }

    public function test_only_the_opening_step_is_on_screen_in_the_first_frame(): void
    {
        $html = $this->page();

        // One step shown and two hidden, server-rendered - so the right step
        // is on screen before Alpine boots rather than after it.
        $this->assertSame(1, substr_count($html, 'travel-step w-full flex-col gap-4 flex'));
        $this->assertSame(2, substr_count($html, 'travel-step w-full flex-col gap-4 hidden'));
    }

    public function test_no_step_is_left_stacked_on_another_at_opacity_zero(): void
    {
        $html = $this->page();

        // The carousel's tells: a screen taken out of flow over the one on
        // show, and a viewport height driven off a measurement.
        $this->assertStringNotContainsString('absolute inset-x-0 top-0 opacity-0', $html);
        $this->assertStringNotContainsString('slideHeight', $html);
        $this->assertStringNotContainsString('sliderReady', $html);
        $this->assertStringNotContainsString('slideClass(', $html);
    }

    public function test_a_rejected_field_reopens_its_own_step_and_only_that_step(): void
    {
        // budget_max below budget_min is a step 2 field, so the page has to
        // come back on step 2 - with step 1 and step 3 off screen, not with
        // step 1 shown and its own error invisible somewhere below.
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

        // ...and it is step 2 that is the one shown. Each chunk after a
        // split on the marker opens with that step's own attributes, so the
        // chunk carrying the `flex` class names the step on screen.
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

        // The hero is the page's head on all three steps. There is exactly
        // one h1 on the page - the hero's - because the bare heading that
        // used to stand in for it on steps 2 and 3 is gone. Losing the hero
        // mid-flow left the wizard hanging under a single line of text.
        $this->assertStringContainsString(__('tourism.request.hero_line1'), $html);
        $this->assertSame(1, substr_count($html, '<h1'));

        // And the hero closes with the hairline every other main page draws
        // under its own - see x-page-hero - rather than fading into the form.
        $this->assertStringContainsString('border-b border-placeholder', $html);
        $this->assertStringNotContainsString("'lg:-mt-[78px]': step === 1", $html);
    }

    public function test_the_step_that_arrives_is_animated_in_on_its_own(): void
    {
        // An animation, not a transition: it has to play on the way out of
        // `display: none`, which a transition cannot do.
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
