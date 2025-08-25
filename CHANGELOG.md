# Changelog

All notable changes to `laravel-oneclicklogin` will be documented in this file.

## [Unreleased]

### Added
- Initial package structure
- Basic magic link functionality
- Configuration system
- Service provider setup
- Test infrastructure

## [0.1.0] - 2025-08-25

### Added
- Initial release of Laravel OneClickLogin
- Passwordless authentication via magic links
- Security-by-default with signed, hashed tokens
- MultiPersona integration support
- ShareLink integration support
- Fluent API for magic link creation
- Artisan commands for management
- Comprehensive configuration options
- Event system for observability
- Rate limiting capabilities
- IP and device binding options
- OTP step-up authentication support

### Security
- SHA-256 token hashing
- Short default TTL (15 minutes)
- Single-use links by default
- Built-in rate limiting
- Signed URL protection
