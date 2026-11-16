# Changelog

All notable changes to `dbcsoft-labs/crash-guard` are documented here.

---

## [Unreleased]

---

## [1.1.0] — 2026-10-29

### Added

#### Architecture — DDD Restructure

- Reorganised all domain logic into a clean `src/Core/Reporting/` bounded context following Clean Architecture:
  - **Domain layer** (`Core/Reporting/Domain/`) — zero framework dependencies
  - **Application layer** (`Core/Reporting/Application/`) — use cases and orchestration
  - **Infrastructure layer** (`Core/Reporting/Infrastructure/`) — notifications, queue, repositories
- Added `CrashFingerprint` value object (`Core/Reporting/Domain/ValueObjects/`) — immutable wrapper around the fingerprint hash with static constructors `fromThrowable()` and `fromString()`
- Added `ErrorLevelResolver` domain service — determines `warning` vs `error` level based on configurable `warning_exceptions` list
- Added `CreateCrashReport` application action (`Core/Reporting/Application/Actions/`) — encapsulates report creation with resolved level, title, trace, and context
- Added `CrashGuardContract` interface (`Core/Reporting/Application/Contracts/`) — allows mocking the main service in tests
- Added `CrashReportRepositoryContract` interface (`Core/Reporting/Application/Contracts/`) — abstracts data access for `CrashReport`
- Added `CrashReportRepository` implementation (`Core/Reporting/Infrastructure/Repositories/`) with new methods:
  - `findReportByFingerprint(string $fingerprint): ?CrashReport`
  - `getTodayFingerprints(): Collection` — returns all distinct fingerprints created today, used by `crash-guard:reset`
- Added `ReportContextDTO` (`Core/Reporting/Application/DTOs/`) — typed DTO for report context with `fromArray()` and `toArray()` helpers
- Added `NotificationChannelContract` interface (`Core/Reporting/Infrastructure/Notifications/Channels/`) — contract all notification channels must implement
- Added `ChannelRegistry` (`Core/Reporting/Infrastructure/Notifications/`) — maps `ReportChannel` enum values to channel instances; allows registering custom channels at runtime
- Commands moved from `src/Commands/` to `src/Console/Commands/`
- Renamed `Services/CrashGuard` → `CrashGuardManager` (implements `CrashGuardContract`)
- `CrashGuardServiceProvider` now binds `CrashGuardContract` → `CrashGuardManager` and `CrashReportRepositoryContract` → `CrashReportRepository`

#### New Features

- **`ShouldUrgentCrashGuard` interface** — marker interface counterpart to `#[UrgentCrashReport]`; implementing this on an exception bypasses deduplication and the circuit breaker
- **`CrashGuardManager::enable()` / `disable()`** — runtime toggle; updates `config('crash-guard.enabled')` in-process
- **`CrashGuardManager::reportWithContext()`** — accepts a typed `ReportContextDTO` instead of a raw array
- **`crash_report()` global helper** (`src/Support/functions.php`) — shorthand for `CrashGuard::report()`
- **`crash-guard:status` command** — displays current configuration (enabled state, mode, dedup window, channels, circuit breaker settings) and DB statistics (pending and total report counts)
- **`crash-guard:reset` improvements** — now accepts an optional `fingerprint` argument; without it, resets all circuit breaker counters for today's distinct fingerprints plus the global daily counter (replaces the previous no-op behaviour)
- **Configurable Slack environment skip** — `CRASH_GUARD_SLACK_SKIP_ENVS` (comma-separated, default `local`) replaces the hard-coded `local`-only check
- **Configurable trace depth** — `CRASH_GUARD_TRACE_LINES` (default `10`) controls how many stack trace lines are captured per report
- **`warning_exceptions` config** — exception classes that should be stored at `warning` level instead of `error`; defaults to `HttpException::class`
- **`scheduling` config block** — optional built-in registration of the prune command with Laravel's scheduler; controlled via `CRASH_GUARD_SCHEDULING_ENABLED` and `CRASH_GUARD_SCHEDULING_EXPRESSION`
- **Publishable assets** — `CrashGuardServiceProvider` now registers `publishes()` for config (`crash-guard-config`), views (`crash-guard-views`), and migrations (`crash-guard-migrations`)
- **`CrashGuard` facade** updated to resolve `CrashGuardContract` and expose full docblock for IDE autocompletion including `enable()`, `disable()`, and `reportWithContext()`
- **Removed** `_packages.crash-guard` double config registration — service provider now merges only `crash-guard` key

#### Config Changes

| Key | New/Changed | Description |
|-----|-------------|-------------|
| `slack.skip_environments` | **New** | Environments where Slack notifications are skipped |
| `warning_exceptions` | **New** | Exceptions to log at `warning` level |
| `trace_lines` | **New** | Stack trace lines to capture (default `10`) |
| `scheduling.enabled` | **New** | Auto-register prune command with scheduler |
| `scheduling.expression` | **New** | Cron expression for built-in prune schedule |
| `scheduling.prune_days` | **New** | Days value passed to prune when auto-scheduled |
| `circuit_breaker.global_daily_limit` | **New** | Global cross-fingerprint daily notification cap |

---

## [1.0.0] — 2026-05-29

### Added

- Initial implementation
- Exception capture with `md5(class + message + file + line)` fingerprinting
- `CrashReport` Eloquent model with scopes: `pending()`, `active()`, `snoozed()`, `pruneable()`
- Report lifecycle management: `resolve()`, `read()`, `ignore()`, `pause()`
- Deduplication window: `CRASH_GUARD_DEDUP_MINUTES`
- Slack notification via Block Kit webhook (`SlackCrashReportNotification`)
- Mail notification via Laravel `Mailable` with Markdown template (`MailCrashReportNotification`)
- Queued notification dispatch via `SendCrashReportNotificationJob`
- Circuit breaker: per-fingerprint rolling window + per-fingerprint daily limit
- PHP 8 attribute API: `#[UrgentCrashReport]`, `#[SilentCrashReport]`, `#[IgnoreCrashReport]`, `#[DebugCrashReport]`, `#[ReportToCrashGuard]`
- Interface API: `ShouldIgnoreCrashReport`, `ShouldSilentCrashGuard`, `ShouldReportToCrashGuard`
- Report modes: `Normal`, `Debug`, `Urgent` (`CrashReportMode` enum)
- Report statuses: `Pending`, `Resolved`, `Ignored` (`ReportStatus` enum)
- Channels enum: `Slack`, `Mail` (`ReportChannel`)
- `ExceptionPolicy` — shouldSkip, isSilent, isUrgent, isDebug logic
- `FingerprintGenerator` — deterministic hash from Throwable or string
- `RequestContextResolver` — captures `user_id`, `user_email`, `ip`, `user_agent`, `route` from HTTP context
- `NotificationDispatcher` — shouldDispatch logic (dedup, paused, urgent, ignored)
- `CrashGuardPruneCommand` — `crash-guard:prune [--days=]`
- `CrashGuardTestCommand` — `crash-guard:test [--case=]`
- Dedicated `crash_guard` log channel
- `CRASH_GUARD_CIRCUIT_BREAKER_DAILY_LIMIT` — per-fingerprint calendar-day notification cap
- Migration: `crash_guard_reports` table
- Migration: add `trace` column to `crash_guard_reports`
