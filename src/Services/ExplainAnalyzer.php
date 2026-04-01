<?php

namespace IndexLens\IndexLens\Services;

use Illuminate\Support\Facades\DB;
use IndexLens\IndexLens\DTOs\QuerySample;

class ExplainAnalyzer
{
    public function analyze(array $queries): array
    {
        if (! (bool) config('indexlens.run_explain', true)) {
            return [];
        }

        $slowQueryMs = (int) config('indexlens.slow_query_ms', 100);
        $results = [];

        foreach ($queries as $query) {
            if (! $query instanceof QuerySample || $query->timeMs < $slowQueryMs) {
                continue;
            }

            $plan = $this->runExplain($query->sql, $query->bindings);
            $insight = $this->interpret($plan);

            $results[] = [
                'sql' => $query->normalizedSql,
                'time_ms' => $query->timeMs,
                'route' => $query->route,
                'severity' => $insight['severity'],
                'score' => $insight['score'],
                'issues' => $insight['issues'],
                'explanation' => $insight['explanation'],
                'strategy' => $insight['strategy'],
                'plan' => $plan,
            ];
        }

        return $results;
    }

    protected function runExplain(string $sql, array $bindings): array
    {
        try {
            return array_map(fn ($row) => (array) $row, DB::select('EXPLAIN ' . $sql, $bindings));
        } catch (\Throwable) {
            return [];
        }
    }

    protected function interpret(array $plan): array
    {
        if ($plan === []) {
            return [
                'severity' => 'low',
                'score' => 25,
                'issues' => ['No explain data available.'],
                'explanation' => 'Explain plan could not be generated for this query.',
                'strategy' => 'Run this query manually with EXPLAIN in your database console.',
            ];
        }

        $issues = [];
        $score = 100;

        foreach ($plan as $row) {
            $type = strtolower((string) ($row['type'] ?? $row['access_type'] ?? ''));
            $extra = strtolower((string) ($row['Extra'] ?? $row['extra'] ?? ''));
            $possibleKeys = strtolower((string) ($row['possible_keys'] ?? ''));

            if (in_array($type, ['all', 'seq scan'], true)) {
                $issues[] = 'Full table scan detected.';
                $score -= 30;
            }

            if (str_contains($extra, 'filesort')) {
                $issues[] = 'Expensive filesort operation detected.';
                $score -= 20;
            }

            if (str_contains($extra, 'temporary')) {
                $issues[] = 'Temporary table usage detected.';
                $score -= 15;
            }

            if ($possibleKeys === '' || $possibleKeys === 'null') {
                $issues[] = 'No possible keys found for optimizer.';
                $score -= 20;
            }

            if (isset($row['rows']) && (int) $row['rows'] > 10000) {
                $issues[] = 'High row scan estimate suggests poor selectivity.';
                $score -= 15;
            }
        }

        $score = max(0, $score);
        $severity = $score < 45 ? 'critical' : ($score < 70 ? 'high' : ($score < 85 ? 'medium' : 'low'));

        return [
            'severity' => $severity,
            'score' => $score,
            'issues' => array_values(array_unique($issues)),
            'explanation' => $issues === []
                ? 'Execution plan looks healthy with no major optimizer warnings.'
                : 'The optimizer indicates bottlenecks that can be reduced with indexing and query refactoring.',
            'strategy' => $issues === []
                ? 'No immediate action required.'
                : 'Prioritize index coverage for WHERE/JOIN columns, then remove sort-heavy patterns and reduce scanned rows.',
        ];
    }
}
