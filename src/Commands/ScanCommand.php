<?php

namespace IndexLens\IndexLens\Commands;

use Illuminate\Console\Command;
use IndexLens\IndexLens\Contracts\IndexLensContract;

class ScanCommand extends Command
{
    protected $signature = 'indexlens:scan';

    protected $description = 'Analyze captured queries for N+1, duplicates, and memory spikes.';

    public function handle(IndexLensContract $indexLens): int
    {
        $report = $indexLens->scan();

        $this->components->info('IndexLens Scan Summary');
        $this->table(['Metric', 'Value'], [
            ['Query count', (string) ($report['summary']['query_count'] ?? 0)],
            ['Total SQL time (ms)', (string) round((float) ($report['summary']['total_sql_time_ms'] ?? 0), 2)],
            ['N+1 findings', (string) count($report['n_plus_one'] ?? [])],
            ['Duplicate fingerprints', (string) count($report['duplicates'] ?? [])],
            ['Memory spikes', (string) count($report['memory_correlation'] ?? [])],
        ]);

        if (! empty($report['n_plus_one'])) {
            $this->components->warn('N+1 Findings');
            $this->table(
                ['Severity', 'Message', 'Suggestion'],
                array_map(fn ($row) => [$row['severity'], $row['message'], $row['suggestion']], $report['n_plus_one'])
            );
        }

        return self::SUCCESS;
    }
}
