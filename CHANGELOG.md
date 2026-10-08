# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [v1.2.0] - 2026-10-08

### Changed

- **Minimum PHP version is now 8.4**: PHP 8.3 is no longer supported (#6)
- CI test matrix now runs PHP 8.4 and 8.5 (#6)
- Rector: simplified repeated strict comparisons into `in_array(..., true)` in `MagicLink::markAsUsed()` and `LogMagicLinkAttempts`.
- CI workflows: `actions/checkout` bumped to v5, `softprops/action-gh-release` bumped to v2.

### Removed

- `symfony/yaml` dependency, which was not used anywhere in the package.

## [v1.1.0] - 2026-09-17

### Added

- Laravel 13 support (`illuminate/support` `^12.0|^13.0`).
- Pest 4 support (`pestphp/pest` `^3.8|^4.0`, `pestphp/pest-plugin-laravel` `^3.2|^4.0`).
- Orchestra Testbench 11 support (`orchestra/testbench` `^10.0|^11.0`).

### Changed

- CI test matrix now covers PHP 8.3 / 8.4 with Laravel 12 and 13 (Testbench 10 / 11), `prefer-lowest` and `prefer-stable`.
- Release workflow now runs against Laravel 13.
- `symfony/yaml` constraint widened to `^7.3|^8.0`.
- `laravel/pint` constraint relaxed from `1.24.0` to `^1.24`.

### Removed

- Laravel 11 support (end of life). PHP 8.3 remains the minimum version.

### Fixed

- `rector.php`: removed the `strictBooleans` option, which no longer exists in Rector 2.

## [v1.0.0] - 2025-08-25

### Added

- Initial release.

[Unreleased]: https://github.com/Grazulex/laravel-oneclicklogin/compare/v1.2.0...HEAD
[v1.2.0]: https://github.com/Grazulex/laravel-oneclicklogin/compare/v1.1.0...v1.2.0
[v1.1.0]: https://github.com/Grazulex/laravel-oneclicklogin/compare/v1.0.0...v1.1.0
[v1.0.0]: https://github.com/Grazulex/laravel-oneclicklogin/releases/tag/v1.0.0
