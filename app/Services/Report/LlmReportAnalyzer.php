<?php

namespace App\Services\Report;

use Illuminate\Support\Facades\Http;

class LlmReportAnalyzer implements ReportAnalyzerInterface
{
    /**
     * Concrete request/response mapping is deferred until an LLM provider is
     * chosen (config('services.llm.key') / config('services.llm.model')).
     * The interface is what the rest of the system depends on, so callers
     * don't need to wait on that choice.
     *
     * @param string[] $comments
     * @return array{summary: string, themes: array<int, string>}
     */
    public function analyze(array $comments): array
    {
        $response = Http::withToken(config('services.llm.key'))
            ->post('', [
                'model' => config('services.llm.model'),
                'comments' => $comments,
            ])
            ->throw()
            ->json();

        return [
            'summary' => $response['summary'] ?? '',
            'themes' => $response['themes'] ?? [],
        ];
    }
}
