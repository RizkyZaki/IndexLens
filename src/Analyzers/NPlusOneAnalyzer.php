<?php

namespace IndexLens\IndexLens\Analyzers;

use IndexLens\IndexLens\DTOs\QuerySample;

class NPlusOneAnalyzer
{
    public function analyze(array $queries): array
    {
        $threshold = (int) config('indexlens.n_plus_one_repeat_threshold', 5);
        $grouped = [];

        foreach ($queries as $query) {
            if (! $query instanceof QuerySample) {
                continue;
            }

            $key = $query->normalizedSql;
            $grouped[$key] ??= ['count' => 0, 'sample' => $query];
            $grouped[$key]['count']++;
        }

        $findings = [];
        foreach ($grouped as $normalized => $row) {
            if ($row['count'] < $threshold) {
                continue;
            }

            $sample = $row['sample'];
            $relation = $this->guessRelation($sample->sql);
            $findings[] = [
                'type' => 'n_plus_one',
                'severity' => $row['count'] > ($threshold * 3) ? 'high' : 'medium',
                'message' => sprintf('Repeated query detected %d times', $row['count']),
                'route' => $sample->route,
                'normalized_sql' => $normalized,
                'suggestion' => $relation
                    ? sprintf("Use %s::with('%s') before iterating", $this->guessModel($sample->sql), $relation)
                    : 'Consider eager loading relations and pre-fetching dependent records.',
                'suggested_code' => $relation
                    ? sprintf("%s::query()->with('%s')->get();", $this->guessModel($sample->sql), $relation)
                    : null,
                'relation_candidate' => $relation,
            ];
        }

        return $findings;
    }

    public function duplicateSummary(array $queries): array
    {
        $threshold = (int) config('indexlens.duplicate_query_threshold', 3);
        $counts = [];

        foreach ($queries as $query) {
            if (! $query instanceof QuerySample) {
                continue;
            }

            $counts[$query->fingerprint] ??= [
                'normalized_sql' => $query->normalizedSql,
                'count' => 0,
            ];
            $counts[$query->fingerprint]['count']++;
        }

        return array_values(array_filter($counts, fn ($item) => $item['count'] >= $threshold));
    }

    protected function guessRelation(string $sql): ?string
    {
        if (preg_match('/from\s+([`\"\[]?[a-zA-Z0-9_]+[`\"\]]?)/i', $sql, $matches)) {
            $table = trim($matches[1], '`"[]');
            if (str_ends_with($table, 's')) {
                return rtrim($table, 's');
            }

            return $table;
        }

        return null;
    }

    protected function guessModel(string $sql): string
    {
        if (! preg_match('/from\s+([`\"\[]?[a-zA-Z0-9_]+[`\"\]]?)/i', $sql, $matches)) {
            return 'Model';
        }

        $table = trim($matches[1], '`"[]');
        $model = str_replace(' ', '', ucwords(str_replace('_', ' ', rtrim($table, 's'))));

        return $model !== '' ? $model : 'Model';
    }
}
