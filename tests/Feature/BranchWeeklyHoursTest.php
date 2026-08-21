<?php

namespace Tests\Feature;

use App\Models\Branch;
use Tests\TestCase;

class BranchWeeklyHoursTest extends TestCase
{
    private function branch(?array $hours): Branch
    {
        return new Branch(['opening_hours' => $hours]);
    }

    public function test_it_collapses_days_that_share_the_same_hours_into_one_run(): void
    {
        $week = $this->branch([
            'mon' => ['09:00', '17:30'], 'tue' => ['09:00', '17:30'], 'wed' => ['09:00', '17:30'],
            'thu' => ['09:00', '17:30'], 'fri' => ['09:00', '17:30'],
            'sat' => ['10:00', '14:00'], 'sun' => null,
        ])->weeklyHours();

        $this->assertSame([
            ['from' => 'mon', 'to' => 'fri', 'hours' => ['09:00', '17:30']],
            ['from' => 'sat', 'to' => 'sat', 'hours' => ['10:00', '14:00']],
            ['from' => 'sun', 'to' => 'sun', 'hours' => null],
        ], $week);
    }

    /**
     * Two days with identical hours either side of a different one are two
     * runs, not one. Merging them would print "Mon - Fri" over a branch that
     * shuts on Wednesday.
     */
    public function test_it_does_not_span_a_run_across_a_day_that_differs(): void
    {
        $week = $this->branch([
            'mon' => ['09:00', '17:00'], 'tue' => ['09:00', '17:00'],
            'wed' => null,
            'thu' => ['09:00', '17:00'], 'fri' => ['09:00', '17:00'],
            'sat' => null, 'sun' => null,
        ])->weeklyHours();

        $this->assertCount(4, $week);
        $this->assertSame(['from' => 'mon', 'to' => 'tue', 'hours' => ['09:00', '17:00']], $week[0]);
        $this->assertSame(['from' => 'wed', 'to' => 'wed', 'hours' => null], $week[1]);
        $this->assertSame(['from' => 'thu', 'to' => 'fri', 'hours' => ['09:00', '17:00']], $week[2]);
    }

    public function test_it_gives_nothing_for_a_branch_whose_hours_were_never_published(): void
    {
        $this->assertSame([], $this->branch(null)->weeklyHours());
        $this->assertSame([], $this->branch([])->weeklyHours());
    }

    /**
     * A day the source never mentioned is absent, not closed - the page must
     * not print "Sunday: closed" on a branch that may well open.
     */
    public function test_it_leaves_out_a_day_the_bank_never_published(): void
    {
        $week = $this->branch(['mon' => ['09:00', '17:00']])->weeklyHours();

        $this->assertSame([['from' => 'mon', 'to' => 'mon', 'hours' => ['09:00', '17:00']]], $week);
    }
}
