<?php

namespace Tests\Feature;

use App\Filament\Resources\ScrapingJobs\Pages\ListScrapingJobs;
use App\Jobs\RunAllScrapersJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class RunAllScrapersActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_run_all_scrapers_button_queues_the_job(): void
    {
        Queue::fake();

        $admin = User::factory()->admin()->create(['name' => 'Test Admin', 'email' => 'test-admin@example.com']);
        $this->actingAs($admin, 'admin');

        Livewire::test(ListScrapingJobs::class)
            ->callAction('runAllScrapers');

        Queue::assertPushed(RunAllScrapersJob::class, 1);
    }
}
