<?php

namespace IndexLens\IndexLens\Services;

use IndexLens\IndexLens\Repositories\ProfileRepository;

class RegressionService
{
    public function __construct(protected ProfileRepository $repository)
    {
    }

    public function detectRegressions(): array
    {
        $current = $this->repository->latestRouteBaselines();
        $previous = $this->repository->previousRouteBaselines();

        $regressions = [];
        foreach ($current as $route => $now) {
            if (! isset($previous[$route])) {
                continue;
            }

            $before = (float) ($previous[$route]['avg_sql_time'] ?? 0);
            $after = (float) ($now['avg_sql_time'] ?? 0);
            $queryBefore = (float) ($previous[$route]['avg_query_count'] ?? 0);
            $queryAfter = (float) ($now['avg_query_count'] ?? 0);

            if ($before <= 0) {
                continue;
            }

            $ratio = $after / $before;
            if ($ratio < 1.25 && $queryAfter <= ($queryBefore * 1.25)) {
                continue;
            }

            $delta = round($ratio, 2);
            $payload = [
                'route' => $route,
                'baseline_query_count' => $queryBefore,
                'current_query_count' => $queryAfter,
                'baseline_time' => $before,
                'current_time' => $after,
                'delta' => $delta,
            ];

            $this->repository->persistRegression($payload);

            $regressions[] = $payload + [
                'regression' => sprintf('%.1fx slower', $ratio),
                'severity' => $ratio >= 2.5 ? 'critical' : 'high',
            ];
        }

        return $regressions;
    }
}
