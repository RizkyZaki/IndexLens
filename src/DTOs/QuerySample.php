<?php

namespace IndexLens\IndexLens\DTOs;

class QuerySample
{
    public function __construct(
        public string $sql,
        public string $normalizedSql,
        public array $bindings,
        public float $timeMs,
        public string $connection,
        public string $route,
        public string $url,
        public ?string $action,
        public ?int $userId,
        public int $memoryBefore,
        public int $memoryAfter,
        public int $requestDurationMs,
        public string $fingerprint,
        public ?array $explainPlan = null,
        public ?string $severity = null
    ) {
    }

    public function memoryDeltaKb(): float
    {
        return max(0, ($this->memoryAfter - $this->memoryBefore) / 1024);
    }

    public function toArray(): array
    {
        return [
            'sql' => $this->sql,
            'normalized_sql' => $this->normalizedSql,
            'bindings' => $this->bindings,
            'execution_time' => $this->timeMs,
            'connection' => $this->connection,
            'route' => $this->route,
            'url' => $this->url,
            'action' => $this->action,
            'user_id' => $this->userId,
            'memory_before' => $this->memoryBefore,
            'memory_after' => $this->memoryAfter,
            'request_duration_ms' => $this->requestDurationMs,
            'fingerprint' => $this->fingerprint,
            'explain_plan' => $this->explainPlan,
            'severity' => $this->severity,
        ];
    }
}
