# Changelog

All notable changes to this project will be documented in this file.

The format is based on Keep a Changelog,
and this project follows Semantic Versioning.

## [Unreleased]

### Added
- Planned: deeper database-driver explain adapters and extended analyzer test coverage.

## [1.0.3] - 2026-04-07

### Fixed
- Fixed MySQL migration failure by removing index creation on LONGTEXT normalized_sql in query_profiles.

## [1.0.1] - 2026-04-01

### Added
- Added MIT LICENSE file for open-source distribution clarity.
- Added CHANGELOG.md to track release history in a maintainable format.

### Changed
- Rewrote README with premium, product-focused narrative and developer value framing.
- Improved documentation structure for enterprise team onboarding.

## [1.0.0] - 2026-04-01

### Added
- Initial production-grade release of IndexLens Laravel package.
- Query interceptor engine with per-request SQL, route, memory, and context capture.
- N+1 and duplicate query detection with normalized SQL fingerprinting.
- Missing index recommendation engine for WHERE, JOIN, ORDER BY, and GROUP BY patterns.
- SQL EXPLAIN analyzer with severity scoring and optimization strategy output.
- Route performance heatmap with ranking score and severity mapping.
- Regression detection and snapshot persistence across route baselines.
- CI performance budget command with failure exit codes for pipeline enforcement.
- Multi-format report generation (array, JSON, Markdown, HTML).
- Debug Blade view plus artisan command suite for scan, routes, regression, CI, and reports.
