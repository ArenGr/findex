<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\Organization;
use App\Models\OrganizationSource;
use App\Models\Report;
use App\Models\ReportRequest;
use App\Models\Review;
use App\Models\ReviewReply;
use App\Models\ScrapingJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_panel_pages(): void
    {
        $admin = Admin::create([
            'name' => 'Test Admin',
            'email' => 'test-admin@example.com',
            'password' => 'password',
        ]);

        $this->actingAs($admin, 'admin');

        $this->get('/admin')->assertOk();
        $this->get('/admin/users')->assertOk();
        $this->get('/admin/organizations')->assertOk();
        $this->get('/admin/reviews')->assertOk();
        $this->get('/admin/branches')->assertOk();
        $this->get('/admin/currencies')->assertOk();
        $this->get('/admin/currency-rates')->assertOk();
        $this->get('/admin/scraping-jobs')->assertOk();
        $this->get('/admin/report-requests')->assertOk();

        $user = User::factory()->create();
        $organization = Organization::create([
            'name' => 'Test Bank',
            'slug' => 'test-bank',
            'type' => 'bank',
            'country_code' => 'AM',
            'is_active' => false,
        ]);
        $branch = Branch::create(['organization_id' => $organization->id, 'name' => 'Main Branch']);
        $review = Review::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'rating' => 4,
            'comment' => 'Pretty good service overall.',
        ]);
        ReviewReply::create([
            'review_id' => $review->id,
            'organization_id' => $organization->id,
            'body' => 'Thanks for the feedback!',
        ]);
        OrganizationSource::create([
            'organization_id' => $organization->id,
            'source_type' => 'currency_rates',
            'url' => '/en',
            'is_active' => true,
        ]);
        $currency = Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'sort_order' => 1]);
        $currencyRate = CurrencyRate::create([
            'organization_id' => $organization->id,
            'currency_id' => $currency->id,
            'rate_type' => 'cash',
            'buy_rate' => 384.5,
            'sell_rate' => 387.0,
        ]);
        $scrapingJob = ScrapingJob::create([
            'organization_id' => $organization->id,
            'source_type' => 'currency_rates',
            'status' => 'success',
            'records_found' => 1,
        ]);
        $scrapingJob->log('info', 'Scraped successfully');
        $reportRequest = ReportRequest::create(['organization_id' => $organization->id, 'status' => 'completed']);
        Report::create([
            'report_request_id' => $reportRequest->id,
            'organization_id' => $organization->id,
            'review_count' => 1,
            'positive_pct' => 100,
            'neutral_pct' => 0,
            'negative_pct' => 0,
            'summary' => 'Mostly positive feedback.',
        ]);

        // Renders all five relation managers (branches, reviews, report
        // requests, currency rates, sources) in one page - the most surface
        // area for a PHP error among these resources.
        $this->get("/admin/organizations/{$organization->id}")->assertOk();
        $this->get("/admin/organizations/{$organization->id}/edit")->assertOk();
        $this->get("/admin/reviews/{$review->id}")->assertOk();
        $this->get("/admin/users/{$user->id}/edit")->assertOk();
        $this->get("/admin/branches/{$branch->id}/edit")->assertOk();
        $this->get("/admin/currency-rates/{$currencyRate->id}/edit")->assertOk();
        $this->get("/admin/scraping-jobs/{$scrapingJob->id}")->assertOk();
        $this->get("/admin/report-requests/{$reportRequest->id}")->assertOk();
        $this->get("/admin/report-requests/{$reportRequest->id}/edit")->assertOk();

        $organization->update(['is_active' => true]);
        $this->assertTrue($organization->fresh()->is_active);

        $user->update(['banned_at' => now()]);
        $this->assertTrue($user->fresh()->isBanned());
    }

    public function test_guest_is_redirected_from_admin_panel(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }
}
