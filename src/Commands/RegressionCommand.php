<?php

namespace IndexLens\IndexLens\Commands;

use Illuminate\Console\Command;
use IndexLens\IndexLens\Contracts\IndexLensContract;

class RegressionCommand extends Command
{
    protected $signature = 'indexlens:regression';

    protected $description = 'Compare baseline and current route performance to detect regressions.';

    public function handle(IndexLensContract $indexLens): int
    {
        $rows = $indexLens->regressions();

        if ($rows === []) {
            $this->components->info('No regressions detected.');

            return self::SUCCESS;
        }

        $this->components->warn('Performance regressions detected');
        $this->table(
            ['Route', 'Before (ms)', 'After (ms)', 'Before Q', 'After Q', 'Regression', 'Severity'],
            array_map(fn ($row) => [
                $row['route'],
                round((float) $row['baseline_time'], 2),
                round((float) $row['current_time'], 2),
                round((float) $row['baseline_query_count'], 2),
                round((float) $row['current_query_count'], 2),
                $row['regression'],
                strtoupper((string) $row['severity']),
            ], $rows)
        );

        return self::SUCCESS;
    }
}
