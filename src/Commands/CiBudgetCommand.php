<?php

namespace IndexLens\IndexLens\Commands;

use Illuminate\Console\Command;
use IndexLens\IndexLens\Contracts\IndexLensContract;

class CiBudgetCommand extends Command
{
    protected $signature = 'indexlens:ci {--max-query=} {--max-time=}';

    protected $description = 'Fail CI when query and SQL time budgets are exceeded.';

    public function handle(IndexLensContract $indexLens): int
    {
        $maxQuery = (int) ($this->option('max-query') ?: config('indexlens.ci_budget.max_queries', 50));
        $maxTime = (float) ($this->option('max-time') ?: config('indexlens.ci_budget.max_sql_time', 200));

        $routes = $indexLens->routes();

        if ($routes === []) {
            $this->components->warn('No route profile data available. CI budget check skipped with failure to prevent false green.');

            return self::FAILURE;
        }

        $violations = array_values(array_filter($routes, fn ($row) =>
            (float) $row['average_query_count'] > $maxQuery || (float) $row['average_sql_time_ms'] > $maxTime
        ));

        $this->table(['Route', 'Avg Query Count', 'Avg SQL Time (ms)', 'Severity'], array_map(
            fn ($row) => [$row['route'], $row['average_query_count'], $row['average_sql_time_ms'], strtoupper((string) $row['severity'])],
            $routes
        ));

        if ($violations !== []) {
            $this->components->error('CI performance budget exceeded.');
            $this->table(
                ['Route', 'Avg Query Count', 'Avg SQL Time (ms)'],
                array_map(fn ($row) => [$row['route'], $row['average_query_count'], $row['average_sql_time_ms']], $violations)
            );

            return self::FAILURE;
        }

        $this->components->info('CI performance budget passed.');

        return self::SUCCESS;
    }
}
