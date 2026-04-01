<?php

namespace IndexLens\IndexLens\Services;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use IndexLens\IndexLens\Analyzers\QueryFingerprint;
use IndexLens\IndexLens\DTOs\QuerySample;
use IndexLens\IndexLens\DTOs\RequestProfile;
use IndexLens\IndexLens\Repositories\ProfileRepository;

class QueryCaptureService
{
    protected bool $enabled;

    protected int $requestStartedAt;

    protected int $memoryStart;

    protected int $memoryPeak;

    /** @var QuerySample[] */
    protected array $queries = [];

    public function __construct(
        protected ProfileRepository $repository,
        protected QueryFingerprint $fingerprint
    ) {
        $this->enabled = (bool) config('indexlens.enabled', true);
        $this->requestStartedAt = hrtime(true);
        $this->memoryStart = memory_get_usage(true);
        $this->memoryPeak = memory_get_peak_usage(true);
    }

    public function boot(): void
    {
        DB::listen(function (QueryExecuted $event): void {
            if (! $this->enabled) {
                return;
            }

            $route = request()?->route();
            $memoryBefore = memory_get_usage(true);
            $durationMs = (int) ((hrtime(true) - $this->requestStartedAt) / 1_000_000);

            $sample = new QuerySample(
                sql: $event->sql,
                normalizedSql: $this->fingerprint->normalize($event->sql),
                bindings: $event->bindings,
                timeMs: (float) $event->time,
                connection: $event->connectionName,
                route: $route?->getName() ?? $route?->uri() ?? 'cli',
                url: request()?->fullUrl() ?? 'cli://local',
                action: $route?->getActionName(),
                userId: Auth::id(),
                memoryBefore: $memoryBefore,
                memoryAfter: memory_get_usage(true),
                requestDurationMs: $durationMs,
                fingerprint: $this->fingerprint->fingerprint($event->sql)
            );

            $this->queries[] = $sample;
            $this->memoryPeak = max($this->memoryPeak, memory_get_peak_usage(true));
        });

        app()->terminating(function (): void {
            if (! $this->enabled) {
                return;
            }

            $this->finalizeRequest();
        });
    }

    public function setEnabled(bool $state): void
    {
        $this->enabled = $state;
    }

    /** @return QuerySample[] */
    public function queries(): array
    {
        return $this->queries;
    }

    public function memoryCorrelations(): array
    {
        $thresholdKb = (int) config('indexlens.memory_spike_kb', 1024);

        return array_values(array_filter(array_map(function (QuerySample $query) use ($thresholdKb) {
            $delta = $query->memoryDeltaKb();
            if ($delta < $thresholdKb) {
                return null;
            }

            return [
                'route' => $query->route,
                'sql' => $query->normalizedSql,
                'memory_spike_kb' => round($delta, 2),
                'execution_time' => $query->timeMs,
                'severity' => $delta > ($thresholdKb * 3) ? 'high' : 'medium',
            ];
        }, $this->queries)));
    }

    public function finalizeRequest(): ?RequestProfile
    {
        if (count($this->queries) === 0) {
            return null;
        }

        $route = $this->queries[0]->route;
        $url = $this->queries[0]->url;
        $action = $this->queries[0]->action;
        $userId = $this->queries[0]->userId;
        $duplicateCount = $this->calculateDuplicateCount();
        $profile = new RequestProfile(
            route: $route,
            url: $url,
            action: $action,
            userId: $userId,
            queryCount: count($this->queries),
            totalSqlTimeMs: array_sum(array_map(fn (QuerySample $q) => $q->timeMs, $this->queries)),
            requestDurationMs: (hrtime(true) - $this->requestStartedAt) / 1_000_000,
            memoryStart: $this->memoryStart,
            memoryEnd: memory_get_usage(true),
            memoryPeak: $this->memoryPeak,
            duplicateCount: $duplicateCount
        );

        if ((bool) config('indexlens.store_profiles', true)) {
            try {
                $this->repository->persistQueryProfiles($this->queries);
                $this->repository->persistRouteProfile($profile);
            } catch (\Throwable $e) {
                Log::debug('IndexLens persistence skipped: ' . $e->getMessage());
            }
        }

        return $profile;
    }

    protected function calculateDuplicateCount(): int
    {
        $map = [];

        foreach ($this->queries as $query) {
            $map[$query->fingerprint] ??= 0;
            $map[$query->fingerprint]++;
        }

        $duplicates = array_filter($map, fn ($count) => $count > 1);

        return (int) array_sum(array_map(fn ($count) => $count - 1, $duplicates));
    }
}
