<?php

namespace IndexLens\IndexLens\Services;

class ReportBuilder
{
    public function build(array $payload, string $format = 'array'): array|string
    {
        return match ($format) {
            'json' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}',
            'markdown' => $this->toMarkdown($payload),
            'html' => $this->toHtml($payload),
            default => $payload,
        };
    }

    protected function toMarkdown(array $payload): string
    {
        $lines = [
            '# IndexLens Performance Report',
            '',
            '## Summary',
            '- Query count: ' . ($payload['scan']['summary']['query_count'] ?? 0),
            '- Total SQL time (ms): ' . round((float) ($payload['scan']['summary']['total_sql_time_ms'] ?? 0), 2),
            '- Route profiles: ' . count($payload['routes'] ?? []),
            '- Regressions: ' . count($payload['regressions'] ?? []),
            '',
            '## Top Route Heatmap',
        ];

        foreach (array_slice($payload['routes'] ?? [], 0, 5) as $route) {
            $lines[] = sprintf(
                '- %s -> %s queries -> %s',
                $route['route'],
                $route['average_query_count'],
                strtoupper((string) $route['severity'])
            );
        }

        return implode(PHP_EOL, $lines);
    }

    protected function toHtml(array $payload): string
    {
        $summary = $payload['scan']['summary'] ?? [];
        $routes = $payload['routes'] ?? [];
        $nPlusOne = $payload['scan']['n_plus_one'] ?? [];
        $duplicates = $payload['scan']['duplicates'] ?? [];
        $memory = $payload['scan']['memory_correlation'] ?? [];
        $indexes = $payload['indexes'] ?? [];
        $explain = $payload['explain'] ?? [];
        $regressions = $payload['regressions'] ?? [];

        usort($explain, fn (array $a, array $b) => (float) ($b['time_ms'] ?? 0) <=> (float) ($a['time_ms'] ?? 0));
        usort($routes, fn (array $a, array $b) => (float) ($b['average_sql_time_ms'] ?? 0) <=> (float) ($a['average_sql_time_ms'] ?? 0));

        $summaryCards = [
            ['label' => 'Captured Queries', 'value' => (string) ((int) ($summary['query_count'] ?? 0))],
            ['label' => 'Total SQL Time', 'value' => round((float) ($summary['total_sql_time_ms'] ?? 0), 2) . ' ms'],
            ['label' => 'Route Profiles', 'value' => (string) count($routes)],
            ['label' => 'Slow Query Findings', 'value' => (string) count($explain)],
            ['label' => 'N+1 Findings', 'value' => (string) count($nPlusOne)],
            ['label' => 'Regressions', 'value' => (string) count($regressions)],
        ];

        $cardsHtml = '';
        foreach ($summaryCards as $card) {
            $cardsHtml .= sprintf(
                '<div class="card"><p class="label">%s</p><p class="value">%s</p></div>',
                $this->esc($card['label']),
                $this->esc($card['value'])
            );
        }

        $routeRows = '';
        foreach (array_slice($routes, 0, 15) as $route) {
            $routeRows .= sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s ms</td><td>%s KB</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                $this->esc((string) ($route['route'] ?? '-')),
                $this->esc((string) ($route['average_query_count'] ?? 0)),
                $this->esc((string) ($route['average_sql_time_ms'] ?? 0)),
                $this->esc((string) ($route['average_memory_kb'] ?? 0)),
                $this->esc((string) ($route['request_count'] ?? 0)),
                $this->severityBadge((string) ($route['severity'] ?? 'good')),
                $this->esc((string) ($route['route_score'] ?? 0))
            );
        }

        $slowQueryRows = '';
        foreach (array_slice($explain, 0, 10) as $item) {
            $issues = implode(', ', $item['issues'] ?? []);
            $slowQueryRows .= sprintf(
                '<tr><td><code>%s</code></td><td>%s ms</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                $this->esc((string) ($item['sql'] ?? '-')),
                $this->esc((string) round((float) ($item['time_ms'] ?? 0), 2)),
                $this->severityBadge((string) ($item['severity'] ?? 'low')),
                $this->esc((string) ($item['score'] ?? 0)),
                $this->esc($issues !== '' ? $issues : '-')
            );
        }

        $nPlusOneRows = '';
        foreach (array_slice($nPlusOne, 0, 10) as $item) {
            $nPlusOneRows .= sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td><code>%s</code></td></tr>',
                $this->severityBadge((string) ($item['severity'] ?? 'medium')),
                $this->esc((string) ($item['message'] ?? '-')),
                $this->esc((string) ($item['suggestion'] ?? '-')),
                $this->esc((string) ($item['relation_candidate'] ?? '-'))
            );
        }

        $indexRows = '';
        foreach (array_slice($indexes, 0, 10) as $item) {
            $indexRows .= sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td><code>%s</code></td></tr>',
                $this->esc((string) ($item['table'] ?? '-')),
                $this->esc((string) ($item['column'] ?? '-')),
                $this->severityBadge((string) ($item['severity'] ?? 'medium')),
                $this->esc((string) ($item['suggestion'] ?? '-'))
            );
        }

        $regressionRows = '';
        foreach (array_slice($regressions, 0, 10) as $item) {
            $regressionRows .= sprintf(
                '<tr><td>%s</td><td>%s ms</td><td>%s ms</td><td>%s</td><td>%s</td></tr>',
                $this->esc((string) ($item['route'] ?? '-')),
                $this->esc((string) round((float) ($item['baseline_time'] ?? 0), 2)),
                $this->esc((string) round((float) ($item['current_time'] ?? 0), 2)),
                $this->esc((string) ($item['regression'] ?? '-')),
                $this->severityBadge((string) ($item['severity'] ?? 'high'))
            );
        }

        $duplicateRows = '';
        foreach (array_slice($duplicates, 0, 10) as $item) {
            $duplicateRows .= sprintf(
                '<tr><td><code>%s</code></td><td>%s</td></tr>',
                $this->esc((string) ($item['normalized_sql'] ?? '-')),
                $this->esc((string) ($item['count'] ?? 0))
            );
        }

        $memoryRows = '';
        foreach (array_slice($memory, 0, 10) as $item) {
            $memoryRows .= sprintf(
                '<tr><td>%s</td><td><code>%s</code></td><td>%s KB</td><td>%s ms</td><td>%s</td></tr>',
                $this->esc((string) ($item['route'] ?? '-')),
                $this->esc((string) ($item['sql'] ?? '-')),
                $this->esc((string) ($item['memory_spike_kb'] ?? 0)),
                $this->esc((string) ($item['execution_time'] ?? 0)),
                $this->severityBadge((string) ($item['severity'] ?? 'medium'))
            );
        }

        return sprintf(
            '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>IndexLens Performance Report</title><style>
                :root{--bg:#f2f4f8;--card:#ffffff;--text:#111827;--muted:#6b7280;--border:#e5e7eb;--accent:#0f766e;--high:#b45309;--critical:#b91c1c;--good:#166534;}
                *{box-sizing:border-box} body{margin:0;background:linear-gradient(180deg,#f8fafc 0%%,#eef2ff 100%%);font-family:Segoe UI,Helvetica,Arial,sans-serif;color:var(--text)}
                .wrap{max-width:1200px;margin:24px auto;padding:0 16px 40px}
                .header{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:20px 24px;box-shadow:0 10px 20px rgba(15,23,42,.04)}
                h1{margin:0;font-size:28px} .subtitle{margin-top:6px;color:var(--muted)}
                .cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-top:16px}
                .card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:14px}
                .label{margin:0;color:var(--muted);font-size:12px;text-transform:uppercase;letter-spacing:.08em}
                .value{margin:8px 0 0;font-size:22px;font-weight:700}
                .section{margin-top:16px;background:var(--card);border:1px solid var(--border);border-radius:14px;padding:16px 18px}
                .section h2{margin:0 0 12px;font-size:20px}
                table{width:100%%;border-collapse:collapse;font-size:13px}
                th,td{padding:10px;border-bottom:1px solid var(--border);vertical-align:top;text-align:left}
                th{font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:var(--muted)}
                code{font-family:Consolas,Menlo,monospace;background:#f8fafc;border:1px solid var(--border);padding:2px 6px;border-radius:6px;word-break:break-word}
                .badge{display:inline-flex;padding:3px 8px;border-radius:999px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em}
                .badge.good{background:#dcfce7;color:var(--good)}
                .badge.medium{background:#dbeafe;color:#1d4ed8}
                .badge.high{background:#fef3c7;color:var(--high)}
                .badge.critical{background:#fee2e2;color:var(--critical)}
                .empty{padding:10px 0;color:var(--muted)}
            </style></head><body>
            <div class="wrap"><div class="header"><h1>IndexLens Performance Report</h1><div class="subtitle">Actionable SQL and route performance intelligence</div><div class="cards">%s</div></div>
            <section class="section"><h2>Route Heatmap</h2>%s</section>
            <section class="section"><h2>Slow Query Intelligence</h2>%s</section>
            <section class="section"><h2>N+1 Findings</h2>%s</section>
            <section class="section"><h2>Index Recommendations</h2>%s</section>
            <section class="section"><h2>Regressions</h2>%s</section>
            <section class="section"><h2>Duplicate Query Patterns</h2>%s</section>
            <section class="section"><h2>Memory Correlation</h2>%s</section>
            </div></body></html>',
            $cardsHtml,
            $routeRows !== '' ? '<table><thead><tr><th>Route</th><th>Avg Queries</th><th>Avg SQL Time</th><th>Avg Memory</th><th>Requests</th><th>Severity</th><th>Score</th></tr></thead><tbody>' . $routeRows . '</tbody></table>' : '<div class="empty">No route profile data available.</div>',
            $slowQueryRows !== '' ? '<table><thead><tr><th>Normalized SQL</th><th>Time</th><th>Severity</th><th>Score</th><th>Detected Issues</th></tr></thead><tbody>' . $slowQueryRows . '</tbody></table>' : '<div class="empty">No slow query explain findings.</div>',
            $nPlusOneRows !== '' ? '<table><thead><tr><th>Severity</th><th>Message</th><th>Suggestion</th><th>Relation Candidate</th></tr></thead><tbody>' . $nPlusOneRows . '</tbody></table>' : '<div class="empty">No N+1 findings.</div>',
            $indexRows !== '' ? '<table><thead><tr><th>Table</th><th>Column</th><th>Severity</th><th>Suggested Index</th></tr></thead><tbody>' . $indexRows . '</tbody></table>' : '<div class="empty">No index recommendations.</div>',
            $regressionRows !== '' ? '<table><thead><tr><th>Route</th><th>Before</th><th>After</th><th>Delta</th><th>Severity</th></tr></thead><tbody>' . $regressionRows . '</tbody></table>' : '<div class="empty">No regressions detected.</div>',
            $duplicateRows !== '' ? '<table><thead><tr><th>Normalized SQL</th><th>Occurrences</th></tr></thead><tbody>' . $duplicateRows . '</tbody></table>' : '<div class="empty">No duplicate query pattern above threshold.</div>',
            $memoryRows !== '' ? '<table><thead><tr><th>Route</th><th>SQL</th><th>Memory Spike</th><th>Execution Time</th><th>Severity</th></tr></thead><tbody>' . $memoryRows . '</tbody></table>' : '<div class="empty">No memory spikes above threshold.</div>'
        );
    }

    protected function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES);
    }

    protected function severityBadge(string $severity): string
    {
        $normalized = strtolower(trim($severity));
        $allowed = ['good', 'medium', 'high', 'critical'];
        if (! in_array($normalized, $allowed, true)) {
            $normalized = 'medium';
        }

        return sprintf('<span class="badge %s">%s</span>', $this->esc($normalized), $this->esc(strtoupper($normalized)));
    }
}
