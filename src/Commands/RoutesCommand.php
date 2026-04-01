<?php

namespace IndexLens\IndexLens\Commands;

use Illuminate\Console\Command;
use IndexLens\IndexLens\Contracts\IndexLensContract;

class RoutesCommand extends Command
{
    protected $signature = 'indexlens:routes';

    protected $description = 'Show route performance heatmap and ranking.';

    public function handle(IndexLensContract $indexLens): int
    {
        $rows = $indexLens->routes();

        if ($rows === []) {
            $this->components->warn('No route profile data available yet.');

            return self::SUCCESS;
        }

        $this->components->info('IndexLens Route Performance Heatmap');

        $this->table(
            ['Route', 'Avg Query Count', 'Avg SQL Time (ms)', 'Avg Memory (KB)', 'Requests', 'Severity', 'Score'],
            array_map(fn ($row) => [
                $row['route'],
                $row['average_query_count'],
                $row['average_sql_time_ms'],
                $row['average_memory_kb'],
                $row['request_count'],
                strtoupper((string) $row['severity']),
                $row['route_score'],
            ], $rows)
        );

        return self::SUCCESS;
    }
}
