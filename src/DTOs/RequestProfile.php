<?php

namespace IndexLens\IndexLens\DTOs;

class RequestProfile
{
    public function __construct(
        public string $route,
        public string $url,
        public ?string $action,
        public ?int $userId,
        public int $queryCount,
        public float $totalSqlTimeMs,
        public float $requestDurationMs,
        public int $memoryStart,
        public int $memoryEnd,
        public int $memoryPeak,
        public int $duplicateCount
    ) {
    }

    public function avgMemoryKb(): float
    {
        return (($this->memoryStart + $this->memoryEnd) / 2) / 1024;
    }
}
