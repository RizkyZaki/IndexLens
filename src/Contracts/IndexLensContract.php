<?php

namespace IndexLens\IndexLens\Contracts;

interface IndexLensContract
{
    public function enable(bool $state = true): void;

    public function scan(): array;

    public function routes(): array;

    public function recommendIndexes(): array;

    public function explainSlowQueries(): array;

    public function regressions(): array;

    public function report(string $format = 'array'): array|string;
}
