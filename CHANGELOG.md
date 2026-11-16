# Changelog

All notable changes to `t-labs-co/crash-guard` are documented here.

---

## [1.0.0] — 2026-11-16

### Added

#### Architecture

- **DDD (Domain-Driven Design) structure** — clean separation under `Core/Reporting/{Domain, Application, Infrastructure}/` following Clean Architecture principles:
  - **Domain layer** — pure domain logic with zero framework dependencies
  - **Application layer** — use cases, orchestration, and DTOs
  - **Infrastructure layer** — notification channels, queue jobs, and repositories
- `CrashFingerprint` value object — immutable wrapper around the fingerprint hash with `fromThrowable()` and `fromString()` static constructors
- `ErrorLevelResolver` domain service — determines `warning` vs `error` level based on configurable `warning_exceptions` list
- `CreateCrashReport` application action — encapsulates report creation with resolved level, title, trace, and context
- `CrashGuardContract` interface — contract for the main crash guard service, enables mocking in tests
- `CrashReportRepositoryContract` interface — abstracts data access for `CrashReport`
- `CrashReportRepository` implementation — Eloquent repository with `findReportByFingerprint()` and `getTodayFingerprints()` methods
- `ReportContextDTO` — typed DTO for structured report context with `fromArray()` and `toArray()` helpers
- `NotificationChannelContract` interface — contract all notification channels must implement
- `ChannelRegistry` — maps `ReportChannel` enum values to channel instances; supports registering custom channels at runtime

#### Core Features

- Exception capture with `md5(class + message + file + line)` fingerprint deduplication — one DB row per unique error with `occurrence_count` incrementing on repeats
- `CrashReport` Eloquent model with scopes: `pending()`, `active()`, `snoozed()`, `pruneable()`
- Report lifecycle management: `resolve()`, `read()`, `ignore()`, `pause()` (snooze until a given datetime)
- Deduplication window — suppresses duplicate notifications within `CRASH_GUARD_DEDUP_MINUTES` (default 60 minutes)
- `crash_report()` global helper function — drop-in replacement for Laravel's `report()` helper
- `CrashGuard` facade — full IDE autocompletion for all methods
- Runtime toggle — `CrashGuard::enable()` / `CrashGuard::disable()` to dynamically toggle reporting
- `reportWithContext()` — accepts a typed `ReportContextDTO` instead of a raw array
- `RequestContextResolver` — automatically captures `user_id`, `user_email`, `ip`, `user_agent`, and `route` from HTTP context
- `FingerprintGenerator` — deterministic hash generation from `Throwable` or plain string
- `ExceptionPolicy` — centralized logic for `shouldSkip`, `isSilent`, `isUrgent`, and `isDebug` checks
- `NotificationDispatcher` — decides whether to dispatch notifications based on dedup window, paused state, urgent mode, and ignored status
- Report modes: `Normal`, `Debug`, `Urgent` (`CrashReportMode` enum)
- Report statuses: `Pending`, `Resolved`, `Ignored` (`ReportStatus` enum)
- Channels enum: `Slack`, `Mail` (`ReportChannel` enum)
- Queued notification dispatch via `SendCrashReportNotificationJob` — never blocks the request cycle
- Dedicated `crash_guard` log channel (configurable via `CRASH_GUARD_LOGGING_ENABLED`)

#### Exception Classification — Attributes

- `#[UrgentCrashReport]` — bypasses deduplication and circuit breaker; always notifies immediately
- `#[SilentCrashReport]` — stores in DB but suppresses all notifications
- `#[IgnoreCrashReport]` — completely skips the exception (no DB record, no notification)
- `#[DebugCrashReport]` — stores in DB in debug mode; no notifications dispatched
- `#[ReportToCrashGuard]` — force-reports even if the exception class is in the `ignored_exceptions` config list

#### Exception Classification — Interfaces

- `ShouldUrgentCrashGuard` — marker interface counterpart to `#[UrgentCrashReport]`
- `ShouldSilentCrashGuard` — marker interface counterpart to `#[SilentCrashReport]`
- `ShouldIgnoreCrashReport` — marker interface counterpart to `#[IgnoreCrashReport]`
- `ShouldReportToCrashGuard` — marker interface counterpart to `#[ReportToCrashGuard]`

#### Notifications

- **Slack** — Block Kit formatted messages via incoming webhook (`CRASH_GUARD_SLACK_WEBHOOK`)
- **Mail** — Markdown Mailable with customizable Blade template (publishable via `crash-guard-views`)
- **Custom channels** — extend `NotificationChannelContract` and register via `ChannelRegistry`
- Configurable Slack environment skip — `CRASH_GUARD_SLACK_SKIP_ENVS` (comma-separated, default `local`)

#### Circuit Breaker

- Per-fingerprint rolling window — opens circuit after `threshold` hits within `cooldown_seconds`
- Per-fingerprint daily limit — silences notifications after `daily_limit` occurrences per calendar day
- Global daily limit — silences all non-urgent notifications after `global_daily_limit` total occurrences per calendar day
- Urgent mode bypasses all circuit breaker checks
- Resolving a report resets its circuit breaker counter

#### Artisan Commands

- `crash-guard:help` — displays help and usage information for all CrashGuard commands
- `crash-guard:test [--case=]` — smoke-test all or individual report cases end-to-end
- `crash-guard:prune [--days=]` — delete resolved and ignored reports older than N days
- `crash-guard:reset [fingerprint]` — reset circuit breaker counters (all or specific fingerprint)
- `crash-guard:status` — display current configuration and report statistics

#### Configuration

| Key | Default | Description |
|-----|---------|-------------|
| `enabled` | `true` | Master on/off switch |
| `dedup_minutes` | `60` | Suppress duplicate notifications within this window |
| `channels` | `slack,mail` | Active notification channels (comma-separated) |
| `slack.webhook` | — | Slack incoming webhook URL |
| `slack.skip_environments` | `local` | Environments where Slack is skipped |
| `mail.to` | — | Recipient email address(es) |
| `mail.from` | `MAIL_FROM_ADDRESS` | Sender address for mail notifications |
| `prune_days` | `30` | Prune resolved/ignored reports older than N days |
| `queue.connection` | app default | Queue connection for notifications |
| `queue.name` | `default` | Queue name for notifications |
| `mode` | `normal` | Default report mode: `normal`, `debug`, or `urgent` |
| `trace_lines` | `10` | Stack trace lines captured per report |
| `circuit_breaker.enabled` | `true` | Toggle circuit breaker on/off |
| `circuit_breaker.threshold` | `10` | Max hits within cooldown before silencing |
| `circuit_breaker.cooldown_seconds` | `300` | Cooldown window in seconds |
| `circuit_breaker.daily_limit` | `10` | Per-fingerprint daily cap (`0` = disabled) |
| `circuit_breaker.global_daily_limit` | `50` | Global daily cap (`0` = disabled) |
| `warning_exceptions` | `HttpException` | Exceptions logged at `warning` level |
| `ignored_exceptions` | *(8 Laravel exceptions)* | Exceptions skipped entirely |
| `scheduling.enabled` | `false` | Auto-register prune command with scheduler |
| `scheduling.expression` | `0 0 * * *` | Cron expression for built-in prune schedule |
| `scheduling.prune_days` | `30` | Days value passed to prune when auto-scheduled |
| `logging.enabled` | `false` | Enable dedicated `crash_guard` log channel |

#### Migrations

- `create_crash_guard_reports_table` — creates the `crash_guard_reports` table with all fields
- `add_trace_to_crash_guard_reports_table` — adds the `trace` column for stack trace storage

#### Publishable Assets

- `crash-guard-config` — publish `config/crash-guard.php`
- `crash-guard-views` — publish mail Blade template for customization
- `crash-guard-migrations` — publish migrations for manual management

#### Service Provider

- Auto-discovered via Laravel's package discovery
- Binds `CrashGuardContract` → `CrashGuardManager` and `CrashReportRepositoryContract` → `CrashReportRepository`
- Registers publishable assets (config, views, migrations)
- Optional built-in scheduling of `crash-guard:prune` via `CRASH_GUARD_SCHEDULING_ENABLED`
