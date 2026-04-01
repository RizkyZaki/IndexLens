<?php

return [
    'enabled' => true,
    'slow_query_ms' => 100,
    'detect_n_plus_one' => true,
    'detect_missing_indexes' => true,
    'run_explain' => true,
    'store_profiles' => true,
    'memory_spike_kb' => 1024,
    'n_plus_one_repeat_threshold' => 5,
    'duplicate_query_threshold' => 3,
    'ci_budget' => [
        'max_queries' => 50,
        'max_sql_time' => 200,
    ],
];
