# AI Assistant Guide — CrashGuard

This document provides essential context and guidelines for AI assistants working on the `t-labs-co/crash-guard` Laravel package.

## Project Overview

**CrashGuard** is a lightweight exception capture, deduplication, and notification framework for Laravel (`^12.0` | `^13.0`) and PHP `^8.4`. It captures exceptions (or plain string messages), deduplicates them by fingerprint, persists them to a database, and dispatches queued notifications via Slack and/or Mail. It ships with a circuit breaker to prevent alert floods, PHP 8 attribute-based exception classification, a `ReportContextDTO` for typed context, and a complete report lifecycle (resolve, ignore, snooze).

### Key Features
- **Fingerprint deduplication**: One DB row per unique error; `occurrence_count` increments on repeats
- **Multi-channel notifications**: Slack (Block Kit) and Mail out of the box; extensible via `ChannelRegistry`
- **Circuit breaker**: Per-fingerprint rolling window + per-day + global daily caps prevent alert floods
- **PHP 8 attribute API**: `#[UrgentCrashReport]`, `#[SilentCrashReport]`, `#[IgnoreCrashReport]`, `#[DebugCrashReport]`, `#[ReportToCrashGuard]`
- **Interface API**: `ShouldUrgentCrashGuard`, `ShouldSilentCrashGuard`, `ShouldIgnoreCrashReport`, `ShouldReportToCrashGuard`
- **Typed context**: `ReportContextDTO` + `reportWithContext()` helper
- **Runtime toggle**: `CrashGuard::enable()` / `CrashGuard::disable()`
- **`crash_report()` global helper**: Drop-in replacement for Laravel's `report()`
- **Report lifecycle**: Resolve, ignore, mark-as-read, snooze (`paused_until`)
- **DDD architecture**: Clean separation under `Core/Reporting/{Domain,Application,Infrastructure}/`

## Architecture

### Directory Structure
```
src/
├── Attributes/                          # PHP 8 exception classification attributes
│   ├── DebugCrashReport.php
│   ├── IgnoreCrashReport.php
│   ├── ReportToCrashGuard.php
│   ├── SilentCrashReport.php
│   └── UrgentCrashReport.php
├── Console/Commands/                    # Artisan CLI commands
│   ├── CrashGuardPruneCommand.php      # Delete old resolved/ignored reports
│   ├── CrashGuardResetCommand.php      # Reset circuit breaker counters
│   ├── CrashGuardStatusCommand.php     # Show current config and stats
│   └── CrashGuardTestCommand.php       # Smoke-test all report cases
├── Core/Reporting/                      # Core reporting engine
│   ├── Application/                     # Application layer services
│   │   ├── Actions/                     # Use case actions
│   │   │   └── CreateCrashReport.php
│   │   ├── Contracts/                   # Service contracts
│   │   │   ├── CrashGuardContract.php
│   │   │   └── CrashReportRepositoryContract.php
│   │   ├── DTOs/                        # Data transfer objects
│   │   │   └── ReportContextDTO.php
│   │   └── Services/                    # Business logic services
│   │       ├── CircuitBreaker.php
│   │       ├── CrashGuardManager.php
│   │       ├── NotificationDispatcher.php
│   │       ├── ReportWriter.php
│   │       └── RequestContextResolver.php
│   ├── Domain/                          # Domain layer
│   │   ├── Contracts/                   # Marker interfaces
│   │   │   ├── ShouldIgnoreCrashReport.php
│   │   │   ├── ShouldReportToCrashGuard.php
│   │   │   ├── ShouldSilentCrashGuard.php
│   │   │   └── ShouldUrgentCrashGuard.php
│   │   ├── Enums/                       # Value enumerations
│   │   │   ├── CrashReportMode.php
│   │   │   ├── ReportChannel.php
│   │   │   └── ReportStatus.php
│   │   ├── Services/                    # Domain services
│   │   │   ├── ErrorLevelResolver.php
│   │   │   ├── ExceptionPolicy.php
│   │   │   └── FingerprintGenerator.php
│   │   └── ValueObjects/                # Immutable value objects
│   │       └── CrashFingerprint.php
│   └── Infrastructure/                  # Infrastructure adapters
│       ├── Notifications/               # Notification channels
│       │   ├── Channels/
│       │   │   ├── MailNotificationChannel.php
│       │   │   ├── NotificationChannelContract.php
│       │   │   └── SlackNotificationChannel.php
│       │   └── ChannelRegistry.php
│       ├── Queue/                       # Queue jobs
│       │   └── SendCrashReportNotificationJob.php
│       └── Repositories/                # Data access
│           └── CrashReportRepository.php
├── Database/Migrations/                 # Database migrations
├── Facades/                             # Laravel facades
│   └── CrashGuard.php
├── Models/                              # Eloquent models
│   └── CrashReport.php
├── Providers/                           # Service providers
│   └── CrashGuardServiceProvider.php
└── Support/                             # Helper functions
    └── functions.php
```

### Core Concepts

1. **Reporting**: `CrashGuardManager` is the central orchestrator — it receives exceptions/messages, applies policy checks, creates or increments reports, and dispatches notifications
2. **Fingerprinting**: `FingerprintGenerator` creates a unique MD5 hash from exception class + message + file + line; `CrashFingerprint` value object wraps this
3. **Policy**: `ExceptionPolicy` determines whether to skip, silence, or report an exception based on attributes, interfaces, and config
4. **Circuit Breaker**: `CircuitBreaker` uses Cache to track per-fingerprint hit counts within a rolling cooldown window, plus daily limits
5. **Deduplication**: `NotificationDispatcher` suppresses duplicate notifications within the configured dedup window
6. **Report Lifecycle**: Reports transition through `pending` → `resolved` / `ignored`; can be snoozed via `paused_until`

## Coding Standards

### PHP Standards
- **PHP Version**: `^8.4` with strict types (`declare(strict_types=1)`)
- **Code Style**: Laravel Pint configuration (see `pint.json`)
- **Static Analysis**: PHPStan Level 8 via Larastan (`composer analyse`)
- **Type Safety**: Full type coverage required — all parameters, properties, and return types must be explicitly typed

### Naming Conventions
- **Classes**: PascalCase (e.g., `CrashGuardManager`, `CrashReport`)
- **Methods/Functions**: camelCase (e.g., `reportException`, `shouldDispatch`)
- **Properties**: camelCase with visibility modifiers
- **Constants**: UPPER_SNAKE_CASE
- **Files**: PascalCase.php matching class name

### Type Annotations
Use PHPDoc `@param`, `@return`, and `@var` annotations for:
- Complex array types: `@param array<string, mixed> $data`
- Collection generics: `@return Collection<int, CrashReport>`
- Nullable types: `@param array<string>|null $environments`

### Code Organization
- Follow Laravel's service provider pattern for package registration
- Use dependency injection via constructor
- Keep business logic in `Services/`, data access in `Repositories/`, and domain logic in `Domain/`
- Domain contracts in `Domain/Contracts/`, implementations in `Application/` or `Infrastructure/`

## Testing Guidelines

### Test Framework
- **Pest PHP** with Laravel plugin for feature/unit tests
- Run tests with: `./vendor/bin/pest`

### Test Coverage Requirements
- **Unit Tests**: Value objects, enums, DTOs, and pure services (no DB)
- **Feature Tests**: Report writing, notification dispatch, full manager flow, model scopes, CLI commands

### Test Organization
```
tests/
├── Unit/                          # Unit tests for value objects, DTOs, services
├── Feature/                       # Feature tests for DB operations, commands, integration
├── TestCase.php                   # Base test case with Orchestra Testbench
└── Pest.php                       # Pest configuration
```

### Test Writing Guidelines
- Use descriptive test names: `it_skips_ignored_exceptions()`
- Test both success and failure paths
- Mock external dependencies where appropriate
- Use Orchestra Testbench for Laravel package testing
- Cover edge cases: null values, empty arrays, boundary conditions
- Use `DB::table()->insert()` for records requiring historical timestamps

## Development Workflow

### Quality Gates
Before submitting changes, ensure:
1. **Static Analysis Passes**: `composer analyse` (PHPStan Level 8, 0 errors)
2. **Tests Pass**: `./vendor/bin/pest` (all tests passing)
3. **Code Style**: `composer format` (Laravel Pint auto-fixes applied)

### Common Commands
```bash
# Run tests
./vendor/bin/pest

# Run static analysis
composer analyse

# Format code
composer format

# Full quality check
composer analyse && ./vendor/bin/pest
```

### Package Configuration
Key configuration in `config/crash-guard.php`:
- `enabled`: Master toggle for crash reporting
- `dedup_minutes`: Duplicate notification suppression window
- `channels`: Active notification channels (slack, mail)
- `circuit_breaker.*`: Circuit breaker thresholds and limits
- `mode`: Default report mode (normal, debug, urgent)
- `ignored_exceptions`: Exception classes to silently skip
- `warning_exceptions`: Exception classes logged at warning level
- `prune_after_days`: Days before resolved/ignored reports are pruned
- `scheduling.*`: Built-in scheduler for prune command
- `logging.*`: Dedicated log channel configuration

## Change Guidelines

### Adding New Features
1. Follow existing architecture patterns (Attributes, Services, Models)
2. Add corresponding tests for new functionality
3. Update documentation if user-facing
4. Ensure PHPStan Level 8 passes with full type coverage

### Modifying Existing Code
1. Preserve backward compatibility where possible
2. Update tests to reflect new behavior
3. Maintain type safety — no untyped parameters or returns
4. Follow existing naming and organization patterns

### Database Changes
1. Add migrations in `src/Database/Migrations/`
2. Update models with new relationships/accessors
3. Consider migration rollback scenarios

### Notification Channels
1. Implement `NotificationChannelContract` interface
2. Register via `ChannelRegistry` in a service provider
3. Add channel key to supported channels list

## Important Notes

- **Package Namespace**: `TLabsCo\CrashGuard\`
- **Table Name**: `crash_guard_reports`
- **Event System**: Uses Laravel queue for notification dispatch
- **Cache Strategy**: Cache store for circuit breaker counters
- **Global Helper**: `crash_report()` function as drop-in for Laravel's `report()`
- **Logging**: Optional dedicated log channel configurable via `logging.*`

## Documentation

For detailed user-facing guides, refer to:

- **[README.md](README.md)** — User-facing documentation, installation, configuration, and usage examples

## References

- **README.md**: User-facing documentation and quick-start examples
- **config/crash-guard.php**: All configuration options with defaults
- **phpstan.neon**: Static analysis configuration
- **pint.json**: Code style configuration
- **composer.json**: Dependencies and scripts
