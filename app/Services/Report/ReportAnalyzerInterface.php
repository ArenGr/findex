<?php

namespace App\Services\Report;

interface ReportAnalyzerInterface
{
    /**
     * Analyze a batch of review comments and summarize the recurring themes.
     *
     * @param string[] $comments
     * @return array{summary: string, themes: array<int, string>}
     */
    public function analyze(array $comments): array;
}
