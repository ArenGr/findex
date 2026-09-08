<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The wizard is a carousel inside one card, not three panels stacked down the
 * page.
 *
 * These assert the structure that makes that true, because the failure mode is
 * a quiet one: swapping the bindings back to plain x-show still renders three
 * working steps, it just puts steps 2 and 3 further down the page and leaves
 * the card growing to hold all of them.
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

    public function test_the_three_steps_share_one_slide_viewport(): void
    {
        $html = $this->page();

        // The viewport's height is bound, so it can follow the active screen
        // rather than reserving room for the tallest.
        $this->assertStringContainsString('height: ${slideHeight}px', $html);
        $this->assertStringContainsString('sliderReady', $html);
    }

    public function test_every_step_is_positioned_by_the_shared_slide_binding(): void
    {
        $html = $this->page();

        foreach ([1, 2, 3] as $step) {
            $this->assertStringContainsString('x-ref="slide'.$step.'"', $html);
            $this->assertStringContainsString('slideClass('.$step.')', $html);
            // Inactive screens are taken out of the tab order and the
            // accessibility tree, not merely made transparent.
            $this->assertStringContainsString('step !== '.$step, $html);
        }
    }

    public function test_only_the_opening_step_is_in_flow_on_the_server_rendered_paint(): void
    {
        $html = $this->page();

        // Steps 2 and 3 start out of flow and offset to the right; step 1 does
        // not, so the card has its true height before Alpine boots.
        $this->assertSame(2, substr_count($html, 'absolute inset-x-0 top-0 opacity-0 pointer-events-none'));
        $this->assertSame(2, substr_count($html, 'translate-x-10'));
    }

    public function test_the_slide_transitions_the_property_tailwind_actually_animates(): void
    {
        $html = $this->page();

        // Tailwind v4 compiles translate-x-* to the `translate` property, not
        // `transform`. Transitioning `transform` animates nothing and the
        // steps cut instead of sliding.
        $this->assertStringContainsString('transition-[opacity,translate]', $html);
        $this->assertStringNotContainsString('transition-[opacity,transform]', $html);
    }

    public function test_motion_is_dropped_for_anyone_who_asked_for_less_of_it(): void
    {
        $this->assertStringContainsString('motion-reduce:transition-none', $this->page());
    }
}
