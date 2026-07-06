<?php

namespace App\Jobs;

use App\Models\Report;
use App\Models\ReportRequest;
use App\Services\Report\ReportAnalyzerInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateReportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public ReportRequest $reportRequest)
    {
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(ReportAnalyzerInterface $analyzer): void
    {
        $this->reportRequest->markAsProcessing();

        $reviews = $this->reportRequest->organization->reviews()
            ->when($this->reportRequest->branch_id, fn ($query) => $query->where('branch_id', $this->reportRequest->branch_id))
            ->when($this->reportRequest->period_from, fn ($query) => $query->whereDate('created_at', '>=', $this->reportRequest->period_from))
            ->when($this->reportRequest->period_to, fn ($query) => $query->whereDate('created_at', '<=', $this->reportRequest->period_to))
            ->get();

        if ($reviews->isEmpty()) {
            Report::create([
                'report_request_id' => $this->reportRequest->id,
                'organization_id' => $this->reportRequest->organization_id,
                'branch_id' => $this->reportRequest->branch_id,
                'review_count' => 0,
            ]);

            $this->reportRequest->markAsCompleted();

            return;
        }

        try {
            $total = $reviews->count();
            $positive = $reviews->where('rating', '>=', 4)->count();
            $negative = $reviews->where('rating', '<=', 2)->count();
            $neutral = $total - $positive - $negative;

            $analysis = $analyzer->analyze($reviews->pluck('comment')->all());

            Report::create([
                'report_request_id' => $this->reportRequest->id,
                'organization_id' => $this->reportRequest->organization_id,
                'branch_id' => $this->reportRequest->branch_id,
                'review_count' => $total,
                'positive_pct' => round($positive / $total * 100, 2),
                'neutral_pct' => round($neutral / $total * 100, 2),
                'negative_pct' => round($negative / $total * 100, 2),
                'summary' => $analysis['summary'],
                'themes' => $analysis['themes'],
            ]);

            $this->reportRequest->markAsCompleted();
        } catch (Throwable $e) {
            Log::error('Report generation failed', ['report_request_id' => $this->reportRequest->id, 'error' => $e->getMessage()]);
            $this->reportRequest->markAsFailed($e->getMessage());

            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        $this->reportRequest->markAsFailed($e->getMessage());
    }
}
