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

        $rows = '';
        foreach ($routes as $route) {
            $rows .= sprintf(
                '<tr><td>%s</td><td>%s</td><td>%sms</td><td>%s</td></tr>',
                htmlspecialchars((string) $route['route'], ENT_QUOTES),
                htmlspecialchars((string) $route['average_query_count'], ENT_QUOTES),
                htmlspecialchars((string) $route['average_sql_time_ms'], ENT_QUOTES),
                htmlspecialchars((string) strtoupper((string) $route['severity']), ENT_QUOTES)
            );
        }

        return sprintf(
            '<html><head><title>IndexLens Report</title></head><body><h1>IndexLens Report</h1><p>Total queries: %d</p><p>Total SQL time: %sms</p><table border="1" cellpadding="8"><thead><tr><th>Route</th><th>Avg Queries</th><th>Avg SQL Time</th><th>Severity</th></tr></thead><tbody>%s</tbody></table></body></html>',
            (int) ($summary['query_count'] ?? 0),
            htmlspecialchars((string) round((float) ($summary['total_sql_time_ms'] ?? 0), 2), ENT_QUOTES),
            $rows
        );
    }
}
