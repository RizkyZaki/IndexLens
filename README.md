# IndexLens

IndexLens is an intelligent database query observability and optimization package for Laravel.

It provides:
- query interception and profiling
- N+1 and duplicate query detection
- missing index recommendations
- SQL EXPLAIN intelligence for slow queries
- route performance heatmap
- regression detection between baselines
- CI performance budget enforcement
- markdown and HTML report export

## Requirements

- PHP 8.2+
- Laravel 10+

## Install

```bash
composer require indexlens/indexlens
```

Publish config and migrations:

```bash
php artisan vendor:publish --tag=indexlens-config
php artisan vendor:publish --tag=indexlens-migrations
php artisan migrate
```

Optional debug view publish:

```bash
php artisan vendor:publish --tag=indexlens-views
```

## Config

Configuration file: config/indexlens.php

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

## Route Heatmap Output Example

```text
/dashboard -> 142 queries -> HIGH
/reports/export -> 600 queries -> CRITICAL
/users -> 12 queries -> GOOD
```

## N+1 Example Finding

```json
{
  "type": "n_plus_one",
  "severity": "high",
  "message": "Repeated query detected 50 times",
  "suggestion": "Use Post::with('user') before iterating"
}
```

## Index Recommendation Example

```json
{
  "table": "orders",
  "column": "user_id",
  "reason": "frequent WHERE usage",
  "suggestion": "CREATE INDEX idx_orders_user_id ON orders(user_id)"
}
```

## Regression Example

```json
{
  "route": "/dashboard",
  "before_ms": 120,
  "after_ms": 520,
  "regression": "4.3x slower"
}
```

## CI Budget in GitHub Actions

```yaml
name: indexlens-performance

on:
  push:
    branches: [ main ]
  pull_request:

jobs:
  performance:
    runs-on: ubuntu-latest

    services:
      mysql:
        image: mysql:8
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: testing
        ports:
          - 3306:3306
        options: >-
          --health-cmd="mysqladmin ping --silent"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=5

    steps:
      - uses: actions/checkout@v4

      - uses: shivammathur/setup-php@v2
        with:
          php-version: 8.3
          tools: composer

      - run: composer install --no-interaction --prefer-dist
      - run: cp .env.example .env
      - run: php artisan key:generate
      - run: php artisan migrate --force
      - run: php artisan test
      - run: php artisan indexlens:ci --max-query=50 --max-time=200
```

## Debug Blade View

You can render the published debug view with your own controller:

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

## Publishing to Packagist

1. Push this repository to GitHub.
2. Ensure composer.json name is indexlens/indexlens.
3. Create a public release tag (example: v1.0.0).
4. Submit repository URL at https://packagist.org/packages/submit.
5. Enable GitHub Service Hook or auto-update in Packagist.

## License

MIT
