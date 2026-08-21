<?php

namespace Tests\Feature;

use App\Support\OpeningHours;
use Tests\TestCase;

class OpeningHoursTest extends TestCase
{
    public function test_it_spreads_a_day_range_across_every_day_in_it(): void
    {
        $hours = OpeningHours::parse('Mon. - Fri.: 9:15-17:30');

        $this->assertSame(['09:15', '17:30'], $hours['mon']);
        $this->assertSame(['09:15', '17:30'], $hours['fri']);
        $this->assertNull($hours['sat'], 'A weekday-only listing means the weekend is shut.');
        $this->assertNull($hours['sun']);
    }

    /**
     * The rules arrive as one HTML blob. strip_tags closes the gap between
     * them without a separator, so "17:30</p><p>Sat.: closed" reads as a
     * single rule ending in "closed" - which marked the whole Mon-Fri range
     * shut. Sending someone to a locked door is the worst thing this class
     * can do, so it gets its own test.
     */
    public function test_it_does_not_let_a_later_closed_day_shut_the_days_before_it(): void
    {
        foreach ([
            'Mon. - Fri.: 9:15-17:30<br>Sat.: 10:00-15:00<br>Sun.: closed',
            '<p>Mon. - Fri.: 9:15-17:30</p><p>Sat.: closed</p>',
        ] as $markup) {
            $hours = OpeningHours::parse($markup);

            $this->assertSame(['09:15', '17:30'], $hours['mon'], "Monday was lost in: {$markup}");
            $this->assertSame(['09:15', '17:30'], $hours['fri']);
            $this->assertNull($hours['sun']);
        }
    }

    /**
     * Araratbank prints both rules on one unbroken line, with nothing but a
     * space between them. Reading only the first marked Saturday closed on
     * every branch that opens then - the exact failure this class exists to
     * avoid, arrived at from the opposite direction.
     */
    public function test_it_reads_a_second_rule_that_follows_on_the_same_line(): void
    {
        $hours = OpeningHours::parse('Monday-Friday 09:00-17:00 Saturday 10:00-14:00');

        $this->assertSame(['09:00', '17:00'], $hours['mon']);
        $this->assertSame(['10:00', '14:00'], $hours['sat'], 'The Saturday rule was dropped.');
        $this->assertNull($hours['sun']);
    }

    /**
     * The Armenian full stop (U+0589) reads as a colon and is used as one.
     * Without it, half of one bank's branches published no hours at all.
     */
    public function test_it_reads_a_time_written_with_the_armenian_full_stop(): void
    {
        $hours = OpeningHours::parse("Monday-Friday 09\u{589}00-17\u{589}00");

        $this->assertSame(['09:00', '17:00'], $hours['mon']);
    }

    /**
     * ACBA lists the cash desk on its own line, closing an hour before the
     * branch does. It names no day, so it must not be mistaken for one.
     */
    public function test_it_ignores_a_second_line_that_names_no_day(): void
    {
        $hours = OpeningHours::parse("Monday-Friday: 09:30-17:30\nCash register service: 09:30-16:30");

        $this->assertSame(['09:30', '17:30'], $hours['mon']);
    }

    public function test_it_reads_a_second_rule_on_the_same_line(): void
    {
        $hours = OpeningHours::parse('Mon-Fri: 9:30-17:30, Sat: 10:00-14:00');

        $this->assertSame(['09:30', '17:30'], $hours['mon']);
        $this->assertSame(['10:00', '14:00'], $hours['sat']);
    }

    /**
     * Armenian bank sites are authored with the keyboard layout switching
     * constantly, and "around the clock" really does arrive with a Cyrillic
     * а - identical on screen, invisible to an ASCII pattern.
     */
    public function test_it_reads_round_the_clock_even_spelled_with_a_cyrillic_letter(): void
    {
        $ascii = OpeningHours::parse('Mon. - Sun.: around the clock');
        $cyrillic = OpeningHours::parse("Mon. - Sun.: \u{430}round the clock");

        $this->assertSame(['00:00', '23:59'], $ascii['mon']);
        $this->assertSame($ascii, $cyrillic, 'The Cyrillic spelling was not recognised.');
    }

    public function test_it_treats_a_bare_twenty_four_seven_as_the_whole_week(): void
    {
        $hours = OpeningHours::parse('24/7');

        foreach (OpeningHours::DAYS as $day) {
            $this->assertSame(['00:00', '23:59'], $hours[$day]);
        }
    }

    /**
     * Unknown is not the same as closed, and the UI renders them
     * differently. Text this cannot read must not become "closed all week".
     */
    public function test_it_returns_nothing_rather_than_guessing_at_text_it_cannot_read(): void
    {
        $this->assertNull(OpeningHours::parse('Call us for opening times'));
        $this->assertNull(OpeningHours::parse(''));
        $this->assertNull(OpeningHours::parse(null));
    }

    /**
     * Some banks publish hours per weekday rather than as a sentence, so
     * they skip the text parser entirely.
     */
    public function test_it_builds_the_same_shape_from_per_weekday_data(): void
    {
        $hours = OpeningHours::fromDays([
            'Monday' => ['09:15:00.000', '16:45:00.000'],
            'Friday' => ['09:15', '16:45'],
            'Saturday' => null,
        ]);

        $this->assertSame(['09:15', '16:45'], $hours['mon'], 'A database time column was not read.');
        $this->assertSame(['09:15', '16:45'], $hours['fri']);
        $this->assertNull($hours['sat']);
        $this->assertNull($hours['sun'], 'A day the source never listed is closed.');
        $this->assertNull(OpeningHours::fromDays([]));
    }

    public function test_it_pads_a_single_digit_hour(): void
    {
        $this->assertSame(['09:00', '18:00'], OpeningHours::parse('Mon: 9:00-18:00')['mon']);
    }
}
