<?php

return [
    'mode' => env('INDEXLENS_MODE', 'balanced'),
    'enabled' => true,
    'slow_query_ms' => 100,
    'detect_n_plus_one' => true,
    'detect_missing_indexes' => true,
    'run_explain' => true,
    'store_profiles' => true,
    'capture_cli' => env('INDEXLENS_CAPTURE_CLI', false),
    'sample_rate' => (float) env('INDEXLENS_SAMPLE_RATE', 1.0),
    'max_queries_per_request' => (int) env('INDEXLENS_MAX_QUERIES_PER_REQUEST', 250),
    'persist_only_slow_requests' => env('INDEXLENS_PERSIST_ONLY_SLOW', false),
    'ignore_routes' => [
        'horizon.*',
        'telescope*',
    ],
    'memory_spike_kb' => 1024,
    'n_plus_one_repeat_threshold' => 5,
    'duplicate_query_threshold' => 3,
    'regression_ignore_cli' => env('INDEXLENS_REGRESSION_IGNORE_CLI', true),
    'regression_min_delta_ms' => (float) env('INDEXLENS_REGRESSION_MIN_DELTA_MS', 50),
    'ci_budget' => [
        'max_queries' => 50,
        'max_sql_time' => 200,
    ],
];
