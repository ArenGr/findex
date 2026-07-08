<?php

namespace App\Services\Report;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class LlmReportAnalyzer implements ReportAnalyzerInterface
{
    /**
     * Concrete request/response mapping is deferred until an LLM provider is
     * chosen (config('services.llm.url') / .key / .model). The interface is
     * what the rest of the system depends on, so callers don't need to wait
     * on that choice.
     *
     * Deliberately never throws: a report's review-count/rating breakdown
     * (computed in GenerateReportJob before this is called) is useful on its
     * own, so an unconfigured or failing LLM call degrades to an empty
     * summary/themes instead of permanently failing the whole report
     * request. The report view already hides the summary/themes sections
     * when they're empty.
     *
     * @param string[] $comments
     * @return array{summary: string, themes: array<int, string>}
     */
    public function analyze(array $comments): array
    {
        $endpoint = config('services.llm.url');

        if (!$endpoint || !config('services.llm.key')) {
            return ['summary' => '', 'themes' => []];
        }

        try {
            $response = Http::withToken(config('services.llm.key'))
                ->post($endpoint, [
                    'model' => config('services.llm.model'),
                    'comments' => $comments,
                ])
                ->throw()
                ->json();

            return [
                'summary' => $response['summary'] ?? '',
                'themes' => $response['themes'] ?? [],
            ];
        } catch (Throwable $e) {
            Log::warning('LLM report analysis failed, continuing without an AI summary', [
                'error' => $e->getMessage(),
            ]);

            return ['summary' => '', 'themes' => []];
        }
    }
}
