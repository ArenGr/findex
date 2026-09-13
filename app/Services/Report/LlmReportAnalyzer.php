<?php

namespace App\Services\Report;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class LlmReportAnalyzer implements ReportAnalyzerInterface
{
    /**
     * @param  string[]  $comments
     * @return array{summary: string, themes: array<int, string>}
     */
    public function analyze(array $comments): array
    {
        $endpoint = config('services.llm.url');

        if (! $endpoint || ! config('services.llm.key')) {
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
