<?php

namespace IndexLens\IndexLens\Services;

use IndexLens\IndexLens\Repositories\ProfileRepository;

class RouteProfiler
{
    public function __construct(protected ProfileRepository $repository)
    {
    }

    public function heatmap(): array
    {
        $rows = $this->repository->routeAggregates();

        return array_map(function (array $row) {
            $score = $this->routeScore((float) $row['avg_query_count'], (float) $row['avg_sql_time']);

            return [
                'route' => $row['route'],
                'average_query_count' => round((float) $row['avg_query_count'], 2),
                'average_sql_time_ms' => round((float) $row['avg_sql_time'], 2),
                'average_memory_kb' => round((float) $row['avg_memory'], 2),
                'request_count' => (int) $row['request_count'],
                'severity' => $this->severityFromScore($score),
                'route_score' => $score,
            ];
        }, $rows);
    }

    protected function routeScore(float $queryCount, float $sqlTime): int
    {
        $score = 100;
        $score -= min(40, (int) floor($queryCount / 5));
        $score -= min(40, (int) floor($sqlTime / 20));

        return max(0, $score);
    }

    protected function severityFromScore(int $score): string
    {
        if ($score < 30) {
            return 'critical';
        }

        if ($score < 55) {
            return 'high';
        }

        if ($score < 75) {
            return 'medium';
        }

        return 'good';
    }
}
