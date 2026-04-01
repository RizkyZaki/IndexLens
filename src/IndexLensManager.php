<?php

namespace IndexLens\IndexLens;

use IndexLens\IndexLens\Contracts\IndexLensContract;
use IndexLens\IndexLens\Services\ExplainAnalyzer;
use IndexLens\IndexLens\Services\IndexRecommendationService;
use IndexLens\IndexLens\Services\QueryCaptureService;
use IndexLens\IndexLens\Services\RegressionService;
use IndexLens\IndexLens\Services\ReportBuilder;
use IndexLens\IndexLens\Services\RouteProfiler;
use IndexLens\IndexLens\Analyzers\NPlusOneAnalyzer;

class IndexLensManager implements IndexLensContract
{
    public function __construct(
        protected QueryCaptureService $captureService,
        protected NPlusOneAnalyzer $nPlusOneAnalyzer,
        protected IndexRecommendationService $indexRecommendationService,
        protected ExplainAnalyzer $explainAnalyzer,
        protected RouteProfiler $routeProfiler,
        protected RegressionService $regressionService,
        protected ReportBuilder $reportBuilder
    ) {
    }

    public function enable(bool $state = true): void
    {
        $this->captureService->setEnabled($state);
    }

    public function scan(): array
    {
        $queries = $this->captureService->queries();
        $nPlusOne = (bool) config('indexlens.detect_n_plus_one', true)
            ? $this->nPlusOneAnalyzer->analyze($queries)
            : [];
        $duplicates = $this->nPlusOneAnalyzer->duplicateSummary($queries);
        $memory = $this->captureService->memoryCorrelations();

        return [
            'summary' => [
                'query_count' => count($queries),
                'total_sql_time_ms' => array_sum(array_map(fn ($q) => $q->timeMs, $queries)),
            ],
            'n_plus_one' => $nPlusOne,
            'duplicates' => $duplicates,
            'memory_correlation' => $memory,
        ];
    }

    public function routes(): array
    {
        return $this->routeProfiler->heatmap();
    }

    public function recommendIndexes(): array
    {
        if (! (bool) config('indexlens.detect_missing_indexes', true)) {
            return [];
        }

        return $this->indexRecommendationService->recommend($this->captureService->queries());
    }

    public function explainSlowQueries(): array
    {
        return $this->explainAnalyzer->analyze($this->captureService->queries());
    }

    public function regressions(): array
    {
        return $this->regressionService->detectRegressions();
    }

    public function report(string $format = 'array'): array|string
    {
        $payload = [
            'scan' => $this->scan(),
            'routes' => $this->routes(),
            'indexes' => $this->recommendIndexes(),
            'explain' => $this->explainSlowQueries(),
            'regressions' => $this->regressions(),
        ];

        return $this->reportBuilder->build($payload, $format);
    }
}
