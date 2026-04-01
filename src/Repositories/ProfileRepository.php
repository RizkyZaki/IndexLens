<?php

namespace IndexLens\IndexLens\Repositories;

use Illuminate\Support\Facades\DB;
use IndexLens\IndexLens\DTOs\QuerySample;
use IndexLens\IndexLens\DTOs\RequestProfile;
use IndexLens\IndexLens\Models\QueryProfile;
use IndexLens\IndexLens\Models\RegressionSnapshot;
use IndexLens\IndexLens\Models\RouteProfile;

class ProfileRepository
{
    public function persistQueryProfiles(array $queries): void
    {
        foreach ($queries as $query) {
            if (! $query instanceof QuerySample) {
                continue;
            }

            QueryProfile::create([
                'route' => $query->route,
                'sql' => $query->sql,
                'normalized_sql' => $query->normalizedSql,
                'execution_time' => $query->timeMs,
                'memory_usage' => $query->memoryDeltaKb(),
                'duplicate_count' => 1,
                'explain_plan' => $query->explainPlan,
                'severity' => $query->severity,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function persistRouteProfile(RequestProfile $profile): void
    {
        RouteProfile::create([
            'route' => $profile->route,
            'avg_query_count' => $profile->queryCount,
            'avg_sql_time' => $profile->totalSqlTimeMs,
            'avg_memory' => $profile->avgMemoryKb(),
            'request_count' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function routeAggregates(): array
    {
        return DB::table('route_profiles')
            ->selectRaw('route, AVG(avg_query_count) as avg_query_count, AVG(avg_sql_time) as avg_sql_time, AVG(avg_memory) as avg_memory, SUM(request_count) as request_count')
            ->groupBy('route')
            ->orderByDesc('avg_query_count')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    public function latestRouteBaselines(): array
    {
        return DB::table('route_profiles as rp')
            ->join(DB::raw('(SELECT route, MAX(id) as max_id FROM route_profiles GROUP BY route) latest'), function ($join) {
                $join->on('rp.route', '=', 'latest.route')->on('rp.id', '=', 'latest.max_id');
            })
            ->select('rp.route', 'rp.avg_query_count', 'rp.avg_sql_time')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->keyBy('route')
            ->all();
    }

    public function previousRouteBaselines(): array
    {
        $rows = DB::table('route_profiles')
            ->select('id', 'route', 'avg_query_count', 'avg_sql_time')
            ->orderByDesc('id')
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row->route] ??= [];
            $grouped[$row->route][] = (array) $row;
        }

        $previous = [];
        foreach ($grouped as $route => $items) {
            if (isset($items[1])) {
                $previous[$route] = $items[1];
            }
        }

        return $previous;
    }

    public function persistRegression(array $payload): void
    {
        RegressionSnapshot::create($payload + [
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
