# IndexLens

Intelligent query observability and optimization engine for Laravel.

IndexLens is built for teams that want to move beyond basic query logs. It captures runtime SQL behavior, explains performance bottlenecks, recommends practical fixes, and guards performance budgets in CI.

## Why IndexLens

Most tooling can tell you what query ran.
IndexLens tells you why it is expensive, where it hurts in your routes, and what to do next.

It is designed to detect:
- N+1 query patterns and duplicate query storms
- missing indexes across WHERE, JOIN, ORDER BY, GROUP BY
- full table scans and filesort risks from EXPLAIN plans
- route-level bottlenecks and memory-heavy hydration patterns
- performance regressions across deployments

## Core Intelligence

### Query Interceptor Engine
- Captures SQL, bindings, timing, connection, route name, URL, action, user ID
- Tracks memory before and after each query
- Tracks request duration and query fingerprint per request

### N+1 + Duplicate Detection
- Groups normalized SQL and flags repeated patterns
- Returns severity, explanation, and eager loading recommendation
- Generates suggested code snippets when relation/model candidates are detected

### Missing Index Recommendation
- Extracts candidate columns from WHERE/JOIN/ORDER BY/GROUP BY usage
- Checks existing index metadata (MySQL and PostgreSQL paths)
- Outputs actionable SQL suggestions with reason and severity

### SQL EXPLAIN Intelligence
- Runs EXPLAIN for slow queries
- Interprets scan type, filesort, temporary table, missing keys, row estimates
- Produces optimization score, severity, and human-readable strategy

### Route Performance Heatmap
- Aggregates average query count, SQL time, memory, request count per route
- Assigns route score and severity ranking
- Makes worst endpoints visible immediately

### Regression Detection
- Compares latest route baseline against previous baseline
- Flags significant growth in query count and route SQL latency
- Stores regression snapshots for team visibility

### CI Performance Budget
- Fails pipeline when route performance exceeds configured thresholds
- Intended for pull request enforcement and deployment safety

## Installation

```bash
composer require indexlens/indexlens
php artisan vendor:publish --tag=indexlens-config
php artisan vendor:publish --tag=indexlens-migrations
php artisan migrate
```

Optional debug view publish:

```bash
php artisan vendor:publish --tag=indexlens-views
```

## Configuration

```php
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
```

## Facade API

```php
use IndexLens\IndexLens\Facades\IndexLens;

IndexLens::enable();
IndexLens::scan();
IndexLens::routes();
IndexLens::recommendIndexes();
IndexLens::explainSlowQueries();
IndexLens::regressions();
IndexLens::report('json');
IndexLens::report('markdown');
IndexLens::report('html');
```

## Artisan Commands

```bash
php artisan indexlens:scan
php artisan indexlens:routes
php artisan indexlens:regression
php artisan indexlens:ci --max-query=50 --max-time=200
php artisan indexlens:report --format=markdown --output=storage/app/indexlens-report.md
php artisan indexlens:report --format=html --output=storage/app/indexlens-report.html
```

## Example Findings

N+1 finding:

```json
{
  "type": "n_plus_one",
  "severity": "high",
  "message": "Repeated query detected 50 times",
  "suggestion": "Use Post::with('user') before iterating"
}
```

Index recommendation:

```json
{
  "table": "orders",
  "column": "user_id",
  "reason": "frequent WHERE usage",
  "suggestion": "CREATE INDEX idx_orders_user_id ON orders(user_id)",
  "severity": "high"
}
```

Regression output:

```json
{
  "route": "/dashboard",
  "before_ms": 120,
  "after_ms": 520,
  "regression": "4.3x slower"
}
```

Route heatmap snapshot:

```text
/dashboard -> 142 queries -> HIGH
/reports/export -> 600 queries -> CRITICAL
/users -> 12 queries -> GOOD
```

## CI Usage (GitHub Actions)

```yaml
name: indexlens-performance

on:
  pull_request:
  push:
    branches: [main]

jobs:
  performance:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          tools: composer

      - run: composer install --no-interaction --prefer-dist
      - run: php artisan test
      - run: php artisan indexlens:ci --max-query=50 --max-time=200
```

## Debug View

```php
return view('indexlens::debug', [
    'routes' => app('indexlens')->routes(),
]);
```

## Architecture

```text
src/
 ├── Console/
 ├── Facades/
 ├── Services/
 ├── Analyzers/
 ├── DTOs/
 ├── Models/
 ├── Repositories/
 ├── Commands/
 ├── Contracts/
 └── IndexLensServiceProvider.php
```

## License

MIT
