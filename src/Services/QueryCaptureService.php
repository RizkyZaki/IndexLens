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

    protected bool $requestSampled = true;

    protected int $requestStartedAt;

    protected int $memoryStart;

    protected int $memoryPeak;

    /** @var QuerySample[] */
    protected array $queries = [];

    public function __construct(
        protected ProfileRepository $repository,
        protected QueryFingerprint $fingerprint
    ) {
        $this->applyModeDefaults();
        $this->enabled = (bool) config('indexlens.enabled', true);
        $this->requestSampled = $this->shouldSampleRequest();
        $this->requestStartedAt = hrtime(true);
        $this->memoryStart = memory_get_usage(true);
        $this->memoryPeak = memory_get_peak_usage(true);
    }

    public function boot(): void
    {
        DB::listen(function (QueryExecuted $event): void {
            if (! $this->enabled || ! $this->requestSampled) {
                return;
            }

            $route = request()?->route();
            $routeName = $route?->getName() ?? $route?->uri() ?? 'cli';

            if ($this->shouldSkipRoute($routeName)) {
                return;
            }

            if (count($this->queries) >= (int) config('indexlens.max_queries_per_request', 250)) {
                return;
            }

            $memoryBefore = memory_get_usage(true);
            $durationMs = (int) ((hrtime(true) - $this->requestStartedAt) / 1_000_000);

            $sample = new QuerySample(
                sql: $event->sql,
                normalizedSql: $this->fingerprint->normalize($event->sql),
                bindings: $event->bindings,
                timeMs: (float) $event->time,
                connection: $event->connectionName,
                route: $routeName,
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
            if (! $this->enabled || ! $this->requestSampled) {
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
                if (! (bool) config('indexlens.persist_only_slow_requests', false)
                    || $profile->totalSqlTimeMs >= (float) config('indexlens.slow_query_ms', 100)) {
                    $this->repository->persistQueryProfiles($this->queries);
                    $this->repository->persistRouteProfile($profile);
                }
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

    protected function shouldSampleRequest(): bool
    {
        $rate = (float) config('indexlens.sample_rate', 1.0);
        if ($rate >= 1.0) {
            return true;
        }

        if ($rate <= 0.0) {
            return false;
        }

        return mt_rand(1, 10000) <= (int) round($rate * 10000);
    }

    protected function shouldSkipRoute(string $routeName): bool
    {
        if ($routeName === 'cli' && ! (bool) config('indexlens.capture_cli', false)) {
            return true;
        }

        foreach ((array) config('indexlens.ignore_routes', []) as $pattern) {
            if (fnmatch((string) $pattern, $routeName)) {
                return true;
            }
        }

        return false;
    }

    protected function applyModeDefaults(): void
    {
        $mode = strtolower((string) config('indexlens.mode', 'balanced'));

        if ($mode === 'off') {
            config(['indexlens.enabled' => false]);

            return;
        }

        if ($mode === 'safe') {
            config([
                'indexlens.run_explain' => false,
                'indexlens.store_profiles' => false,
                'indexlens.capture_cli' => false,
                'indexlens.sample_rate' => 0.25,
                'indexlens.slow_query_ms' => max(300, (int) config('indexlens.slow_query_ms', 100)),
            ]);

            return;
        }

        if ($mode === 'investigate') {
            config([
                'indexlens.run_explain' => false,
                'indexlens.store_profiles' => true,
                'indexlens.capture_cli' => false,
                'indexlens.sample_rate' => 1.0,
            ]);
        }
    }
}
