<?php

namespace IndexLens\IndexLens\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use IndexLens\IndexLens\Contracts\IndexLensContract;

class ReportCommand extends Command
{
    protected $signature = 'indexlens:report {--format=markdown : array|json|markdown|html} {--output= : Path to output file}';

    protected $description = 'Generate a performance report in JSON, Markdown, or HTML.';

    public function handle(IndexLensContract $indexLens): int
    {
        $format = (string) $this->option('format');
        $output = $this->option('output');

        $report = $indexLens->report($format);

        if (is_array($report)) {
            $rendered = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
        } else {
            $rendered = $report;
        }

        if (is_string($output) && $output !== '') {
            File::put($output, $rendered);
            $this->components->info('IndexLens report written to: ' . $output);

            return self::SUCCESS;
        }

        $this->line($rendered);

        return self::SUCCESS;
    }
}
