<?php

namespace IndexLens\IndexLens\Services;

use Illuminate\Support\Facades\DB;
use IndexLens\IndexLens\DTOs\QuerySample;

class IndexRecommendationService
{
    public function recommend(array $queries): array
    {
        $candidates = [];

        foreach ($queries as $query) {
            if (! $query instanceof QuerySample) {
                continue;
            }

            foreach ($this->extractColumns($query->sql) as $candidate) {
                $key = $candidate['table'] . ':' . $candidate['column'];
                $candidates[$key] ??= $candidate + ['hits' => 0];
                $candidates[$key]['hits']++;
            }
        }

        $recommendations = [];
        foreach ($candidates as $candidate) {
            if ($candidate['hits'] < 2) {
                continue;
            }

            if ($this->hasIndex($candidate['table'], $candidate['column'])) {
                continue;
            }

            $indexName = 'idx_' . $candidate['table'] . '_' . $candidate['column'];
            $recommendations[] = [
                'table' => $candidate['table'],
                'column' => $candidate['column'],
                'reason' => sprintf('frequent %s usage (%d hits)', $candidate['reason'], $candidate['hits']),
                'suggestion' => sprintf('CREATE INDEX %s ON %s(%s)', $indexName, $candidate['table'], $candidate['column']),
                'severity' => $candidate['hits'] > 6 ? 'high' : 'medium',
            ];
        }

        return $recommendations;
    }

    protected function extractColumns(string $sql): array
    {
        $results = [];

        if (preg_match('/from\s+([`\"\[]?[a-zA-Z0-9_]+[`\"\]]?)/i', $sql, $tableMatch)) {
            $table = trim($tableMatch[1], '`"[]');
        } else {
            $table = 'unknown_table';
        }

        if (preg_match_all('/where\s+([a-zA-Z0-9_\.]+)\s*(=|>|<|in|like)/i', $sql, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $results[] = [
                    'table' => $table,
                    'column' => $this->cleanColumn($match[1]),
                    'reason' => 'WHERE',
                ];
            }
        }

        if (preg_match_all('/join\s+([a-zA-Z0-9_`\"\[\]]+)\s+on\s+([a-zA-Z0-9_\.]+)\s*=\s*([a-zA-Z0-9_\.]+)/i', $sql, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $joinTable = trim($match[1], '`"[]');
                $results[] = [
                    'table' => $joinTable,
                    'column' => $this->cleanColumn($match[2]),
                    'reason' => 'JOIN',
                ];
                $results[] = [
                    'table' => $table,
                    'column' => $this->cleanColumn($match[3]),
                    'reason' => 'JOIN',
                ];
            }
        }

        if (preg_match_all('/order\s+by\s+([a-zA-Z0-9_\.]+)/i', $sql, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $results[] = [
                    'table' => $table,
                    'column' => $this->cleanColumn($match[1]),
                    'reason' => 'ORDER BY',
                ];
            }
        }

        if (preg_match_all('/group\s+by\s+([a-zA-Z0-9_\.]+)/i', $sql, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $results[] = [
                    'table' => $table,
                    'column' => $this->cleanColumn($match[1]),
                    'reason' => 'GROUP BY',
                ];
            }
        }

        return array_filter($results, fn ($row) => $row['column'] !== '');
    }

    protected function hasIndex(string $table, string $column): bool
    {
        try {
            $driver = DB::connection()->getDriverName();

            if ($driver === 'mysql') {
                $indexes = DB::select('SHOW INDEX FROM ' . $table);
                foreach ($indexes as $index) {
                    if (isset($index->Column_name) && strtolower($index->Column_name) === strtolower($column)) {
                        return true;
                    }
                }
            }

            if ($driver === 'pgsql') {
                $indexes = DB::select(
                    "SELECT indexdef FROM pg_indexes WHERE tablename = ?",
                    [$table]
                );
                foreach ($indexes as $index) {
                    if (isset($index->indexdef) && str_contains(strtolower($index->indexdef), '(' . strtolower($column) . ')')) {
                        return true;
                    }
                }
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }

    protected function cleanColumn(string $column): string
    {
        $parts = explode('.', trim($column, '`"[] '));

        return trim(end($parts) ?: '', '`"[] ');
    }
}
