# CrashGuard

[![Tests](https://github.com/t-labs-co/crash-guard/actions/workflows/tests.yml/badge.svg)](https://github.com/t-labs-co/crash-guard/actions/workflows/tests.yml)
[![Static Analysis](https://github.com/t-labs-co/crash-guard/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/t-labs-co/crash-guard/actions/workflows/static-analysis.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%5E8.4-8892BF.svg)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/laravel-%5E12.0%20%7C%20%5E13.0-FF2D20.svg)](https://laravel.com)

Lightweight exception capture, deduplication, and notification for Laravel — with a circuit breaker and full report lifecycle.

CrashGuard captures exceptions (or plain string messages), deduplicates them by fingerprint, persists them to the database, and dispatches queued notifications via Slack and/or Mail. It ships with a circuit breaker to prevent alert floods, PHP 8 attribute-based exception classification, a `ReportContextDTO` for typed context, and a complete report lifecycle (resolve, ignore, snooze).

---

## Features

- **Fingerprint deduplication** — one DB row per unique error; `occurrence_count` increments on repeats
- **Multi-channel notifications** — Slack (Block Kit) and Mail out of the box; extensible via `ChannelRegistry`
- **Circuit breaker** — per-fingerprint rolling window + per-day + global daily caps prevent alert floods
- **PHP 8 attribute API** — `#[UrgentCrashReport]`, `#[SilentCrashReport]`, `#[IgnoreCrashReport]`, `#[DebugCrashReport]`, `#[ReportToCrashGuard]`
- **Interface API** — `ShouldUrgentCrashGuard`, `ShouldSilentCrashGuard`, `ShouldIgnoreCrashReport`, `ShouldReportToCrashGuard`
- **Typed context** — `ReportContextDTO` + `reportWithContext()` helper
- **Runtime toggle** — `CrashGuard::enable()` / `CrashGuard::disable()`
- **`crash_report()` global helper** — drop-in replacement for Laravel's `report()`
- **Report lifecycle** — resolve, ignore, mark-as-read, snooze (`paused_until`)
- **Error level resolver** — `warning` vs `error` level per configurable exception classes
- **Queued notifications** — always dispatched via a configurable queue connection
- **Built-in scheduling** — optional auto-registration of the prune command
- **Artisan commands** — `crash-guard:help`, `crash-guard:test`, `crash-guard:prune`, `crash-guard:reset`, `crash-guard:status`
- **DDD architecture** — clean separation under `Core/Reporting/{Domain,Application,Infrastructure}/`

---

## Documentation

- **[Usage Examples](docs/usage-examples.md)** — Comprehensive, real-world examples covering all features: helper function, facade, attributes, interfaces, context DTO, report lifecycle, circuit breaker, custom channels, Artisan commands, and more.

---

## Requirements

- PHP 8.4+
- Laravel 12 or 13

---

## Installation

The package is auto-discovered via Laravel's package discovery. Add it to your `composer.json` repositories for a local path package:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "packages/t-labs-co/crash-guard"
        }
    ]
}
```

Then require it:

```bash
composer require t-labs-co/crash-guard
```

Run the migrations:

```bash
php artisan migrate
```

---

## Configuration

Publish the config file (optional):

```bash
php artisan vendor:publish --tag=crash-guard-config
```

Publish the mail view to customise the email template (optional):

```bash
php artisan vendor:publish --tag=crash-guard-views
```

Publish migrations to manage them manually (optional — auto-loaded by default):

```bash
php artisan vendor:publish --tag=crash-guard-migrations
```

### Environment Variables

| Variable | Default | Description |
|---|---|---|
| `CRASH_GUARD_ENABLED` | `true` | Master on/off switch |
| `CRASH_GUARD_DEDUP_MINUTES` | `60` | Suppress duplicate notifications within this window (minutes) |
| `CRASH_GUARD_CHANNELS` | `slack,mail` | Active notification channels (comma-separated) |
| `CRASH_GUARD_SLACK_WEBHOOK` | — | Slack incoming webhook URL |
| `CRASH_GUARD_SLACK_SKIP_ENVS` | `local` | Environments where Slack notifications are skipped (comma-separated) |
| `CRASH_GUARD_MAIL_TO` | — | Recipient email address(es), comma-separated |
| `CRASH_GUARD_MAIL_FROM` | `MAIL_FROM_ADDRESS` | Sender address for mail notifications |
| `CRASH_GUARD_PRUNE_DAYS` | `30` | Prune resolved/ignored reports older than N days |
| `CRASH_GUARD_QUEUE_CONNECTION` | app default | Queue connection for notifications |
| `CRASH_GUARD_QUEUE_NAME` | `default` | Queue name for notifications |
| `CRASH_GUARD_MODE` | `normal` | Default report mode: `normal`, `debug`, or `urgent` |
| `CRASH_GUARD_TRACE_LINES` | `10` | Stack trace lines captured per report |
| `CRASH_GUARD_CIRCUIT_BREAKER_ENABLED` | `true` | Toggle the circuit breaker on/off |
| `CRASH_GUARD_CIRCUIT_BREAKER_THRESHOLD` | `10` | Max hits within cooldown before notifications are silenced |
| `CRASH_GUARD_CIRCUIT_BREAKER_COOLDOWN` | `300` | Cooldown window in seconds |
| `CRASH_GUARD_CIRCUIT_BREAKER_DAILY_LIMIT` | `10` | Max hits per fingerprint per calendar day (`0` = disabled) |
| `CRASH_GUARD_CIRCUIT_BREAKER_GLOBAL_DAILY_LIMIT` | `50` | Max total hits across all fingerprints per day (`0` = disabled) |
| `CRASH_GUARD_SCHEDULING_ENABLED` | `false` | Auto-register the prune command with Laravel's scheduler |
| `CRASH_GUARD_SCHEDULING_EXPRESSION` | `0 0 * * *` | Cron expression for the built-in prune schedule |
| `CRASH_GUARD_LOGGING_ENABLED` | `false` | Enable the dedicated `crash_guard` log channel |

### Minimal `.env` Example

```dotenv
CRASH_GUARD_ENABLED=true
CRASH_GUARD_CHANNELS=slack,mail
CRASH_GUARD_SLACK_WEBHOOK=https://hooks.slack.com/services/xxx/yyy/zzz
CRASH_GUARD_MAIL_TO=alerts@example.com
```

---

## Laravel Integration

### Hook into the Exception Handler

Add to `bootstrap/app.php` so every unhandled exception is captured automatically:

```php
use Illuminate\Foundation\Application;
use TLabsCo\CrashGuard\Facades\CrashGuard;

return Application::configure(basePath: dirname(__DIR__))
    ->withExceptions(function (\Illuminate\Foundation\Exceptions\Handler $exceptions) {
        $exceptions->report(function (\Throwable $e) {
            CrashGuard::report($e);
        });
    })
    ->create();
```

---

## Usage

### Via the Facade

```php
use TLabsCo\CrashGuard\Facades\CrashGuard;

// Report an exception
CrashGuard::report(new \RuntimeException('Payment failed'));

// Report with extra context
CrashGuard::report($exception, [
    'order_id' => $order->id,
    'user_id' => auth()->id(),
]);

// Report a plain string message
CrashGuard::report('Unexpected state encountered in payment flow');

// Report bypassing dedup and circuit breaker — always notifies
CrashGuard::reportAsUrgent($exception);

// Report as debug — stored in DB only, no notifications
CrashGuard::reportAsDebug($exception);
```

### Via the Global Helper

```php
crash_report(new \RuntimeException('Something failed'));
crash_report('Unexpected state', ['context_key' => 'value']);
```

### Via Dependency Injection

Type-hint `CrashGuardContract` for easy mocking in tests:

```php
use TLabsCo\CrashGuard\Core\Reporting\Application\Contracts\CrashGuardContract;

class PaymentService
{
    public function __construct(private readonly CrashGuardContract $crashGuard) {}

    public function charge(Order $order): void
    {
        try {
            // ...
        } catch (\Throwable $e) {
            $this->crashGuard->report($e, ['order_id' => $order->id]);
        }
    }
}
```

### Typed Context with `ReportContextDTO`

```php
use TLabsCo\CrashGuard\Core\Reporting\Application\DTOs\ReportContextDTO;
use TLabsCo\CrashGuard\Facades\CrashGuard;

$context = new ReportContextDTO(
    userId: (string) auth()->id(),
    userEmail: auth()->user()?->email,
    ip: request()->ip(),
    route: request()->route()?->getName(),
    extra: ['order_id' => $orderId],
);

CrashGuard::reportWithContext($exception, $context);
```

### Runtime Toggle

```php
CrashGuard::disable();   // Stop capturing (e.g. during maintenance)
CrashGuard::enable();    // Re-enable
CrashGuard::isEnabled(); // bool
```

---

## Exception Classification

### PHP 8 Attributes

Apply attributes directly to exception classes to control their reporting behaviour:

```php
use TLabsCo\CrashGuard\Attributes\UrgentCrashReport;
use TLabsCo\CrashGuard\Attributes\SilentCrashReport;
use TLabsCo\CrashGuard\Attributes\IgnoreCrashReport;
use TLabsCo\CrashGuard\Attributes\DebugCrashReport;
use TLabsCo\CrashGuard\Attributes\ReportToCrashGuard;

// Bypass dedup & circuit breaker — always notify immediately
#[UrgentCrashReport]
class DatabaseConnectionLostException extends \RuntimeException {}

// Store in DB, never notify
#[SilentCrashReport]
class ExternalApiTimeoutException extends \RuntimeException {}

// Skip entirely — no DB record, no notification
#[IgnoreCrashReport]
class BotScanException extends \RuntimeException {}

// Store in DB only (debug mode)
#[DebugCrashReport]
class VerboseDiagnosticException extends \RuntimeException {}

// Force-report even if the class is listed in ignored_exceptions config
#[ReportToCrashGuard]
class CriticalValidationException extends \Illuminate\Validation\ValidationException {}
```

### Interfaces

Use marker interfaces when you cannot modify the exception class:

```php
use TLabsCo\CrashGuard\Core\Reporting\Domain\Contracts\ShouldUrgentCrashGuard;
use TLabsCo\CrashGuard\Core\Reporting\Domain\Contracts\ShouldSilentCrashGuard;
use TLabsCo\CrashGuard\Core\Reporting\Domain\Contracts\ShouldIgnoreCrashReport;
use TLabsCo\CrashGuard\Core\Reporting\Domain\Contracts\ShouldReportToCrashGuard;

class DatabaseConnectionLostException extends \RuntimeException implements ShouldUrgentCrashGuard {}
class ExternalApiTimeoutException extends \RuntimeException implements ShouldSilentCrashGuard {}
class BotScanException extends \RuntimeException implements ShouldIgnoreCrashReport {}
class CriticalValidationException extends \Illuminate\Validation\ValidationException implements ShouldReportToCrashGuard {}
```

### Priority Chain

| Priority | Rule | Effect |
|---|---|---|
| 1 (highest) | `ShouldReportToCrashGuard` / `#[ReportToCrashGuard]` | Always report — overrides ignore list |
| 2 | `ShouldIgnoreCrashReport` / `#[IgnoreCrashReport]` | Always skip |
| 3 | `ignored_exceptions` config list | Skip |
| 4 (default) | — | Report normally |

### Ignored Exceptions (default)

The following are ignored out of the box:

- `AuthenticationException`
- `ValidationException`
- `ThrottleRequestsException`
- `NotFoundHttpException`
- `MethodNotAllowedHttpException`
- `AuthorizationException`
- `TokenMismatchException`
- `ModelNotFoundException`

Add your own in `config/crash-guard.php`:

```php
'ignored_exceptions' => [
    \App\Exceptions\MyExpectedException::class,
],
```

---

## Report Mode

| Mode | Enum | Behaviour |
|---|---|---|
| `normal` | `CrashReportMode::Normal` | Standard reporting and notification |
| `debug` | `CrashReportMode::Debug` | Stored in DB only, no notifications |
| `urgent` | `CrashReportMode::Urgent` | Bypasses dedup **and** the circuit breaker — always notifies |

Mode resolution order (highest wins):

1. Explicit `$mode` argument to `report()`
2. `#[UrgentCrashReport]` / `ShouldUrgentCrashGuard` on the exception
3. `#[DebugCrashReport]` on the exception
4. `CRASH_GUARD_MODE` global default

---

## Report Lifecycle

```php
use TLabsCo\CrashGuard\Facades\CrashGuard;

// Mark as resolved (also resets the circuit breaker counter for the fingerprint)
CrashGuard::resolve($report);   // pass CrashReport model or int ID
CrashGuard::resolve(42);

// Mark as read (no status change)
CrashGuard::read($report);

// Ignore — suppresses future notifications for this fingerprint
CrashGuard::ignore($report);

// Snooze notifications until a given datetime
CrashGuard::pause($report, now()->addHours(4));
```

### Report Status Flow

```
pending  →  resolved
         →  ignored
```

A snoozed report (`paused_until`) stays in `pending` but notifications are suppressed until the datetime passes.

---

## Notifications

Notifications are dispatched as queued jobs (`SendCrashReportNotificationJob`) so they never block the request cycle.

### Slack

Uses the Slack Block Kit API. Requires `CRASH_GUARD_SLACK_WEBHOOK`.

To skip Slack in certain environments (e.g. `local`, `testing`, `staging`):

```dotenv
CRASH_GUARD_SLACK_SKIP_ENVS=local,testing
```

### Mail

Renders a Markdown Mailable. Requires `CRASH_GUARD_MAIL_TO`.

Customise the template:

```bash
php artisan vendor:publish --tag=crash-guard-views
# Edit: resources/views/vendor/crash-guard/mail/crash-report.blade.php
```

### Custom Channels

Register any driver that implements `NotificationChannelContract`:

```php
use TLabsCo\CrashGuard\Core\Reporting\Infrastructure\Notifications\ChannelRegistry;
use TLabsCo\CrashGuard\Core\Reporting\Infrastructure\Notifications\Channels\NotificationChannelContract;
use TLabsCo\CrashGuard\Models\CrashReport;

class DiscordCrashChannel implements NotificationChannelContract
{
    public function send(CrashReport $report): void
    {
        \Illuminate\Support\Facades\Http::post(config('services.discord.webhook'), [
            'content' => "🚨 [{$report->level}] {$report->title}\n{$report->message}",
        ]);
    }
}

// In your AppServiceProvider::boot():
$this->app->afterResolving(ChannelRegistry::class, function (ChannelRegistry $registry) {
    $registry->register('discord', new DiscordCrashChannel());
});

// Then add the channel key to your env:
// CRASH_GUARD_CHANNELS=slack,mail,discord
```

---

## Circuit Breaker

The circuit breaker prevents alert floods by silencing notifications when an error fires too frequently. The DB record is **always** updated regardless of circuit state.

### Cooldown window

Once a fingerprint exceeds `threshold` hits within the `cooldown_seconds` rolling window, the circuit opens. Notifications are silenced for the rest of the window.

### Daily limits

Independently of the cooldown window:
- **Per-fingerprint daily limit** — if a fingerprint exceeds `daily_limit` occurrences on a calendar day, it is silenced until midnight.
- **Global daily limit** — if the total occurrences across **all** fingerprints exceeds `global_daily_limit` on a calendar day, all non-urgent notifications are silenced until midnight.

| Condition | Effect |
|---|---|
| Hits in cooldown window > `threshold` | Silent for remainder of cooldown |
| Fingerprint hits today > `daily_limit` | Silent until midnight |
| All fingerprint hits today > `global_daily_limit` | Silent until midnight |
| Mode is `Urgent` | All circuit checks bypassed — always notifies |
| Report resolved | Both per-fingerprint counters reset immediately |

---

## Deduplication

After the first notification for a fingerprint, subsequent occurrences are suppressed for `CRASH_GUARD_DEDUP_MINUTES` minutes (default 60). The `occurrence_count` and `last_seen_at` are still updated on every occurrence.

Setting mode to `Urgent` bypasses deduplication entirely.

---

## Error Levels

CrashGuard assigns a `level` to each new report:

| Level | Condition |
|---|---|
| `warning` | Exception is an instance of a class in `warning_exceptions` config |
| `error` | Everything else |

Extend the `warning_exceptions` list in config:

```php
'warning_exceptions' => [
    \Symfony\Component\HttpKernel\Exception\HttpException::class,
    \App\Exceptions\MyWarningException::class,
],
```

---

## Artisan Commands

### `crash-guard:test`

Smoke-test all report cases to verify the integration end-to-end:

```bash
php artisan crash-guard:test
php artisan crash-guard:test --case=exception
php artisan crash-guard:test --case=urgent
```

Available cases: `exception`, `string`, `silent`, `urgent`, `debug`, `resolve`, `read`, `ignore`, `pause`, `disabled`, `all` (default).

### `crash-guard:prune`

Delete resolved and ignored reports older than the configured threshold:

```bash
php artisan crash-guard:prune
php artisan crash-guard:prune --days=14
```

### `crash-guard:reset`

Reset circuit breaker counters:

```bash
# Reset all of today's fingerprint counters + global daily limit
php artisan crash-guard:reset

# Reset a specific fingerprint
php artisan crash-guard:reset {fingerprint}
```

### `crash-guard:status`

Show the current configuration and report statistics:

```bash
php artisan crash-guard:status
```

Example output:

```
CrashGuard Status

  Enabled:          YES
  Mode:             normal
  Dedup window:     60 minutes
  Channels:         slack, mail
  Circuit breaker:  enabled (threshold: 10, cooldown: 300s)
  Pending reports:  4
  Total reports:    127
  Prune after:      30 days
  Scheduling:       disabled
```

### `crash-guard:help`

Display help and usage information for all CrashGuard commands:

```bash
php artisan crash-guard:help
```

Lists all available commands with descriptions, the global `crash_report()` helper, the `CrashGuard` facade, and PHP 8 attributes.

---

## Built-in Scheduling

Enable the service provider to automatically register the prune command with Laravel's scheduler:

```dotenv
CRASH_GUARD_SCHEDULING_ENABLED=true
CRASH_GUARD_SCHEDULING_EXPRESSION=0 0 * * *
```

Alternatively, schedule it manually in `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('crash-guard:prune')->dailyAt('00:15');
```

---

## Testing

Mock `CrashGuardContract` in feature tests:

```php
use TLabsCo\CrashGuard\Core\Reporting\Application\Contracts\CrashGuardContract;

it('reports the exception to crash guard', function () {
    $mock = $this->mock(CrashGuardContract::class);
    $mock->shouldReceive('report')->once();

    $this->post('/api/checkout', [])->assertServerError();
});
```

---

## Report Model

The `CrashReport` Eloquent model has the following fields:

| Field | Type | Description |
|---|---|---|
| `fingerprint` | `string` | Unique hash (class + message + file + line) |
| `level` | `string` | `error` or `warning` |
| `title` | `string` | Short title (class basename or first 100 chars of message) |
| `message` | `text` | Full exception message |
| `trace` | `text?` | First N lines of the stack trace |
| `context` | `json?` | Extra context array supplied at report time |
| `status` | `ReportStatus` | `pending`, `resolved`, or `ignored` |
| `mode` | `CrashReportMode` | `normal`, `debug`, or `urgent` |
| `occurrence_count` | `int` | Total occurrences of this fingerprint |
| `first_seen_at` | `datetime` | Timestamp of the first occurrence |
| `last_seen_at` | `datetime` | Timestamp of the most recent occurrence |
| `notified_at` | `datetime?` | When the last notification was dispatched |
| `read_at` | `datetime?` | When the report was marked read |
| `resolved_at` | `datetime?` | When the report was resolved |
| `ignored_at` | `datetime?` | When the report was ignored |
| `paused_until` | `datetime?` | Notifications suppressed until this datetime |
| `user_id` | `string?` | Authenticated user ID at report time |
| `user_email` | `string?` | Authenticated user email at report time |
| `ip` | `string?` | Request IP address |
| `user_agent` | `string?` | Request User-Agent header |
| `route` | `string?` | Route name or path |

### Scopes

```php
CrashReport::query()->pending();    // status = pending
CrashReport::query()->active();     // pending and not snoozed
CrashReport::query()->snoozed();    // paused_until in the future
CrashReport::query()->pruneable(30); // resolved/ignored older than 30 days
```

---

## Package Structure

```
src/
├── Attributes/                          PHP 8 exception classification attributes
├── Console/Commands/                    Artisan commands
├── Core/
│   └── Reporting/
│       ├── Domain/                      Pure domain logic — no framework dependencies
│       │   ├── Contracts/               Marker interfaces (ShouldUrgentCrashGuard, etc.)
│       │   ├── Enums/                   CrashReportMode, ReportChannel, ReportStatus
│       │   ├── Services/                ExceptionPolicy, FingerprintGenerator, ErrorLevelResolver
│       │   └── ValueObjects/            CrashFingerprint
│       ├── Application/                 Use cases and orchestration
│       │   ├── Actions/                 CreateCrashReport
│       │   ├── Contracts/               CrashGuardContract, CrashReportRepositoryContract
│       │   ├── DTOs/                    ReportContextDTO
│       │   └── Services/                CrashGuardManager, CircuitBreaker, NotificationDispatcher,
│       │                                ReportWriter, RequestContextResolver
│       └── Infrastructure/              External integrations
│           ├── Notifications/
│           │   ├── Channels/            NotificationChannelContract, SlackNotificationChannel,
│           │   │                        MailNotificationChannel
│           │   └── ChannelRegistry.php
│           ├── Queue/                   SendCrashReportNotificationJob
│           └── Repositories/            CrashReportRepository
├── Database/Migrations/
├── Facades/                             CrashGuard facade
├── Models/                              CrashReport
├── Providers/                           CrashGuardServiceProvider
└── Support/
    └── functions.php                    crash_report() global helper
```

---

## License

GPL-3.0-only — © DBCSoft Inc
