<?php

namespace IndexLens\IndexLens\Console;

use Illuminate\Console\Command;

class ReportPrinter
{
    public function printSectionTitle(Command $command, string $title): void
    {
        $command->line('');
        $command->line('<options=bold;fg=cyan>=== ' . $title . ' ===</>');
    }
}
