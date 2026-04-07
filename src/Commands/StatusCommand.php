<?php

namespace IndexLens\IndexLens\Commands;

use Illuminate\Console\Command;

class StatusCommand extends Command
{
    protected $signature = 'indexlens:status';

    protected $description = 'Show active IndexLens runtime mode and production-safety settings.';

    public function handle(): int
    {
        $this->components->info('IndexLens Runtime Status');

        $this->table(['Setting', 'Value'], [
            ['mode', (string) config('indexlens.mode', 'balanced')],
            ['enabled', config('indexlens.enabled', true) ? 'true' : 'false'],
            ['store_profiles', config('indexlens.store_profiles', true) ? 'true' : 'false'],
            ['run_explain', config('indexlens.run_explain', true) ? 'true' : 'false'],
            ['capture_cli', config('indexlens.capture_cli', false) ? 'true' : 'false'],
            ['sample_rate', (string) config('indexlens.sample_rate', 1.0)],
            ['slow_query_ms', (string) config('indexlens.slow_query_ms', 100)],
            ['persist_only_slow_requests', config('indexlens.persist_only_slow_requests', false) ? 'true' : 'false'],
        ]);

        return self::SUCCESS;
    }
}
