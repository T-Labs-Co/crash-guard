# Usage Examples — `t-labs-co/crash-guard`

**Package:** `t-labs-co/crash-guard`  
**Applies to:** v1.0.0+

This guide provides detailed, real-world examples for every CrashGuard feature — from basic exception reporting to advanced circuit breaker tuning and custom notification channels.

---

## Table of Contents

1. [Basic Reporting](#1-basic-reporting)
2. [The `crash_report()` Global Helper](#2-the-crash_report-global-helper)
3. [The `CrashGuard` Facade](#3-the-crashguard-facade)
4. [Exception Classification — Attributes](#4-exception-classification--attributes)
5. [Exception Classification — Interfaces](#5-exception-classification--interfaces)
6. [Typed Context with `ReportContextDTO`](#6-typed-context-with-reportcontextdto)
7. [Report Lifecycle Management](#7-report-lifecycle-management)
8. [Runtime Enable/Disable](#8-runtime-enabledisable)
9. [Querying Crash Reports](#9-querying-crash-reports)
10. [Circuit Breaker Behavior](#10-circuit-breaker-behavior)
11. [Notification Channels](#11-notification-channels)
12. [Custom Notification Channel](#12-custom-notification-channel)
13. [Artisan Commands](#13-artisan-commands)
14. [Replacing Laravel's `report()` Helper](#14-replacing-laravels-report-helper)
15. [Configuration Tuning](#15-configuration-tuning)

---

## 1. Basic Reporting

The simplest way to report an exception is to catch it and pass it to CrashGuard:

```php
use TLabsCo\CrashGuard\Facades\CrashGuard;

try {
    // Some operation that might fail
    $result = $this->externalApi->fetchData();
} catch (\Throwable $e) {
    CrashGuard::report($e);
}
```

You can also report plain string messages without an exception:

```php
CrashGuard::report('Payment gateway returned an unexpected response');
```

---

## 2. The `crash_report()` Global Helper

CrashGuard ships with a global helper function that works anywhere in your application — controllers, middleware, jobs, listeners, etc.:

```php
// In a controller
public function store(Request $request)
{
    try {
        Order::create($request->validated());
    } catch (\Throwable $e) {
        crash_report($e, ['order_data' => $request->validated()]);
    }

    return response()->json(['message' => 'Order created']);
}
```

```php
// In a middleware
public function handle(Request $request, Closure $next)
{
    try {
        return $next($request);
    } catch (\Throwable $e) {
        crash_report($e, ['path' => $request->path(), 'method' => $request->method()]);
        throw $e;
    }
}
```

```php
// In a queued job
public function handle()
{
    try {
        $this->processData();
    } catch (\Throwable $e) {
        crash_report($e, ['job_id' => $this->id]);
    }
}
```

The helper accepts an optional context array that will be stored with the report:

```php
crash_report($e, [
    'user_id' => auth()->id(),
    'action' => 'checkout',
    'cart_total' => $cart->total(),
]);
```

---

## 3. The `CrashGuard` Facade

The facade provides access to all CrashGuard methods with full IDE autocompletion:

```php
use TLabsCo\CrashGuard\Facades\CrashGuard;

// Standard report
$report = CrashGuard::report($exception);

// Urgent report — bypasses deduplication, always notifies
$report = CrashGuard::reportAsUrgent($exception, ['reason' => 'payment failure']);

// Debug report — stored in DB only, no notifications
$report = CrashGuard::reportAsDebug($exception);

// Check if reporting is enabled
if (CrashGuard::isEnabled()) {
    // ...
}
```

---

## 4. Exception Classification — Attributes

PHP 8 attributes let you classify exception classes without modifying their code (useful for third-party exceptions via wrapper classes):

### `#[UrgentCrashReport]` — Always Notify

Bypasses the deduplication window and circuit breaker; always triggers notifications immediately:

```php
<?php

namespace App\Exceptions;

use TLabsCo\CrashGuard\Attributes\UrgentCrashReport;
use RuntimeException;

#[UrgentCrashReport]
class PaymentGatewayTimeout extends RuntimeException
{
    // This exception will always trigger immediate notifications
}
```

### `#[SilentCrashReport]` — Store Only, No Notification

Persists the exception to the database but suppresses all channel notifications:

```php
<?php

namespace App\Exceptions;

use TLabsCo\CrashGuard\Attributes\SilentCrashReport;
use RuntimeException;

#[SilentCrashReport]
class RateLimitExceededException extends RuntimeException
{
    // Logged for tracking but won't send Slack/email alerts
}
```

### `#[IgnoreCrashReport]` — Skip Entirely

Completely skips the exception — no DB record, no notification:

```php
<?php

namespace App\Exceptions;

use TLabsCo\CrashGuard\Attributes\IgnoreCrashReport;
use RuntimeException;

#[IgnoreCrashReport]
class ExpectedRetryException extends RuntimeException
{
    // Intentionally retried operation — no need to report
}
```

### `#[DebugCrashReport]` — Debug Mode

Stored in the database with debug mode — no notifications dispatched:

```php
<?php

namespace App\Exceptions;

use TLabsCo\CrashGuard\Attributes\DebugCrashReport;
use RuntimeException;

#[DebugCrashReport]
class FeatureFlagDisabledException extends RuntimeException
{
    // Only recorded during development/debugging
}
```

### `#[ReportToCrashGuard]` — Force Report

Force-reports an exception even if it's listed in `ignored_exceptions[]` config:

```php
<?php

namespace App\Exceptions;

use TLabsCo\CrashGuard\Attributes\ReportToCrashGuard;
use Illuminate\Database\Eloquent\ModelNotFoundException;

#[ReportToCrashGuard]
class CriticalResourceNotFound extends ModelNotFoundException
{
    // Even though ModelNotFoundException is ignored by default,
    // this subclass will still be reported
}
```

---

## 5. Exception Classification — Interfaces

For exceptions you own, implementing interfaces is the cleanest approach:

### `ShouldUrgentCrashGuard`

```php
<?php

namespace App\Exceptions;

use TLabsCo\CrashGuard\Core\Reporting\Domain\Contracts\ShouldUrgentCrashGuard;
use RuntimeException;

class DatabaseConnectionLost extends RuntimeException implements ShouldUrgentCrashGuard
{
    // Always notifies — database connectivity is critical
}
```

### `ShouldSilentCrashGuard`

```php
<?php

namespace App\Exceptions;

use TLabsCo\CrashGuard\Core\Reporting\Domain\Contracts\ShouldSilentCrashGuard;
use RuntimeException;

class CacheMissException extends RuntimeException implements ShouldSilentCrashGuard
{
    // Expected behavior — track it but don't alert
}
```

### `ShouldIgnoreCrashReport`

```php
<?php

namespace App\Exceptions;

use TLabsCo\CrashGuard\Core\Reporting\Domain\Contracts\ShouldIgnoreCrashReport;
use RuntimeException;

class UserCancelledException extends RuntimeException implements ShouldIgnoreCrashReport
{
    // User intentionally cancelled — not an error
}
```

### `ShouldReportToCrashGuard`

```php
<?php

namespace App\Exceptions;

use Illuminate\Validation\ValidationException;
use TLabsCo\CrashGuard\Core\Reporting\Domain\Contracts\ShouldReportToCrashGuard;

class CriticalValidationFailed extends ValidationException implements ShouldReportToCrashGuard
{
    // ValidationException is ignored by default, but this one should be reported
}
```

---

## 6. Typed Context with `ReportContextDTO`

For structured context data, use `ReportContextDTO`:

```php
use TLabsCo\CrashGuard\Core\Reporting\Application\DTOs\ReportContextDTO;
use TLabsCo\CrashGuard\Facades\CrashGuard;

$context = new ReportContextDTO(
    userId: auth()->id(),
    userEmail: auth()->user()->email,
    ip: request()->ip(),
    userAgent: request()->userAgent(),
    route: request()->route()->getName(),
    extra: [
        'order_id' => $order->id,
        'payment_method' => $order->payment_method,
    ],
);

CrashGuard::reportWithContext($exception, $context);
```

Building from an array:

```php
$context = ReportContextDTO::fromArray([
    'user_id' => 42,
    'user_email' => 'user@example.com',
    'order_id' => 'ORD-12345',
]);

CrashGuard::reportWithContext($exception, $context);
```

---

## 7. Report Lifecycle Management

Every report goes through a lifecycle. Manage it via the facade:

```php
use TLabsCo\CrashGuard\Facades\CrashGuard;
use TLabsCo\CrashGuard\Models\CrashReport;

// --- Resolve a report (issue is fixed) ---
$report = CrashReport::where('fingerprint', 'abc123')->first();
CrashGuard::resolve($report);
// Also accepts an integer ID:
CrashGuard::resolve($report->id);

// --- Mark as read (acknowledged, not yet fixed) ---
CrashGuard::read($report);

// --- Ignore a report (false positive, won't fix) ---
CrashGuard::ignore($report);

// --- Snooze notifications until a specific time ---
use Carbon\Carbon;

CrashGuard::pause($report, Carbon::now()->addHours(4));
// Notifications for this fingerprint are suppressed for 4 hours
```

### Lifecycle Flow

```
New Exception → Pending (active)
                    │
                    ├─→ read() → Pending (acknowledged)
                    │
                    ├─→ pause() → Pending (snoozed until X)
                    │
                    ├─→ resolve() → Resolved
                    │
                    └─→ ignore() → Ignored
```

Resolved and ignored reports are eligible for pruning via `crash-guard:prune`.

---

## 8. Runtime Enable/Disable

Dynamically toggle crash reporting at runtime:

```php
use TLabsCo\CrashGuard\Facades\CrashGuard;

// Disable during a maintenance window
CrashGuard::disable();

// Re-enable after maintenance
CrashGuard::enable();

// Check current state
if (CrashGuard::isEnabled()) {
    // Reporting is active
}
```

**Use case — disable during seed/migrate:**

```php
// In a custom Artisan command
public function handle()
{
    \TLabsCo\CrashGuard\Facades\CrashGuard::disable();

    try {
        $this->call('db:seed');
    } finally {
        \TLabsCo\CrashGuard\Facades\CrashGuard::enable();
    }
}
```

---

## 9. Querying Crash Reports

The `CrashReport` model provides convenient scopes:

```php
use TLabsCo\CrashGuard\Models\CrashReport;

// All pending (unresolved) reports
$pending = CrashReport::pending()->get();

// Active reports (pending and not currently snoozed)
$active = CrashReport::active()->get();

// Currently snoozed reports (paused_until is in the future)
$snoozed = CrashReport::snoozed()->get();

// Reports eligible for pruning (resolved/ignored older than N days)
$pruneable = CrashReport::pruneable(30)->get();

// Custom queries
$recentErrors = CrashReport::pending()
    ->where('level', 'error')
    ->orderByDesc('last_seen_at')
    ->take(10)
    ->get();

// Find by fingerprint
$report = CrashReport::where('fingerprint', $fingerprint)->first();
```

---

## 10. Circuit Breaker Behavior

The circuit breaker prevents alert floods by silencing notifications when a fingerprint fires too frequently.

### How It Works

```
Exception occurs → fingerprint generated → circuit breaker records hit
                                              │
                                              ├─ Hits < threshold → notification sent
                                              │
                                              ├─ Hits >= threshold → circuit OPENS
                                              │   (notifications silenced for cooldown period)
                                              │
                                              ├─ Daily limit exceeded → silenced until midnight
                                              │
                                              └─ Global daily limit exceeded → ALL non-urgent silenced until midnight
```

### Configuration

```php
// config/crash-guard.php

'circuit_breaker' => [
    'enabled' => true,                    // Toggle on/off
    'threshold' => 10,                    // Max hits within cooldown before silencing
    'cooldown_seconds' => 300,            // How long the circuit stays open (5 min)
    'daily_limit' => 10,                  // Per-fingerprint daily cap (0 = disabled)
    'global_daily_limit' => 50,           // All-fingerprints daily cap (0 = disabled)
],
```

### Resetting the Circuit Breaker

```bash
# Reset all circuit breaker counters via Artisan
php artisan crash-guard:reset
```

Programmatically, resolving a report resets its circuit counter:

```php
CrashGuard::resolve($report);  // Resets circuit breaker for this fingerprint
```

### Urgent Reports Bypass

Reports marked as `urgent` (via `#[UrgentCrashReport]`, `ShouldUrgentCrashGuard`, or `reportAsUrgent()`) **always bypass** the circuit breaker and are never silenced.

---

## 11. Notification Channels

CrashGuard ships with two built-in notification channels:

### Slack

Sends formatted messages using Slack's Block Kit. Configure via environment:

```env
CRASH_GUARD_SLACK_WEBHOOK=https://hooks.slack.com/services/T00000/B00000/XXXXXXXX
CRASH_GUARD_SLACK_SKIP_ENVS=local
```

The Slack notification includes:
- Exception class and message
- Fingerprint and occurrence count
- Stack trace (configurable lines)
- Request context (IP, user, route)
- Error level (warning/error)

### Mail

Sends email notifications with a Blade template:

```env
CRASH_GUARD_MAIL_TO=admin@example.com,devops@example.com
CRASH_GUARD_MAIL_FROM=noreply@example.com
```

Customize the email template by publishing the views:

```bash
php artisan vendor:publish --tag=crash-guard-views
```

Then edit `resources/views/vendor/crash-guard/mail/crash-report.blade.php`.

### Selecting Channels

```env
# Both channels
CRASH_GUARD_CHANNELS=slack,mail

# Slack only
CRASH_GUARD_CHANNELS=slack

# Mail only
CRASH_GUARD_CHANNELS=mail
```

---

## 12. Custom Notification Channel

Implement `NotificationChannelContract` and register it with `ChannelRegistry`:

```php
<?php

namespace App\CrashGuard\Channels;

use TLabsCo\CrashGuard\Core\Reporting\Infrastructure\Notifications\Channels\NotificationChannelContract;
use TLabsCo\CrashGuard\Models\CrashReport;

class DiscordNotificationChannel implements NotificationChannelContract
{
    public function __construct(
        private string $webhookUrl,
    ) {}

    public function send(CrashReport $report): void
    {
        // Send to Discord webhook
        http_post($this->webhookUrl, [
            'content' => sprintf(
                '🚨 **%s** — %s (occurrence #%d)',
                $report->title,
                $report->message,
                $report->occurrence_count,
            ),
        ]);
    }
}
```

Register it in your `AppServiceProvider`:

```php
use TLabsCo\CrashGuard\Core\Reporting\Infrastructure\Notifications\ChannelRegistry;
use App\CrashGuard\Channels\DiscordNotificationChannel;

public function boot(): void
{
    ChannelRegistry::register('discord', function ($app) {
        return new DiscordNotificationChannel(
            config('crash-guard.discord.webhook_url'),
        );
    });
}
```

Then enable it:

```env
CRASH_GUARD_CHANNELS=slack,mail,discord
```

---

## 13. Artisan Commands

### `crash-guard:help`

Display all available commands and usage information:

```bash
php artisan crash-guard:help
```

### `crash-guard:status`

Show the current system status and configuration summary:

```bash
php artisan crash-guard:status
```

Output example:

```
CrashGuard Status
─────────────────
Enabled:         true
Mode:            normal
Channels:        slack, mail
Circuit Breaker: enabled (threshold: 10, cooldown: 300s)
Dedup Window:    60 minutes
Queue:           default
Scheduling:      disabled
```

### `crash-guard:test`

Smoke-test all report cases to verify the pipeline is working:

```bash
# Run all test cases
php artisan crash-guard:test

# Run a specific case
php artisan crash-guard:test --case=urgent
php artisan crash-guard:test --case=silent
php artisan crash-guard:test --case=normal
```

### `crash-guard:prune`

Delete old resolved/ignored crash reports:

```bash
# Use configured prune days (default: 30)
php artisan crash-guard:prune

# Custom number of days
php artisan crash-guard:prune --days=7
```

### `crash-guard:reset`

Reset all circuit breaker counters:

```bash
php artisan crash-guard:reset
```

---

## 14. Replacing Laravel's `report()` Helper

To make CrashGuard the default exception reporter, override Laravel's `report()` helper in your `AppServiceProvider`:

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use TLabsCo\CrashGuard\Facades\CrashGuard;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Override Laravel's global report() helper
        if (function_exists('report')) {
            // Keep Laravel's helper for non-Throwable arguments
        }

        // In your ExceptionHandler, delegate to CrashGuard:
        // $this->crashGuard->report($e);
    }
}
```

Or, in your `app/Exceptions/Handler.php`:

```php
<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use TLabsCo\CrashGuard\Facades\CrashGuard;
use Throwable;

class Handler extends ExceptionHandler
{
    public function report(Throwable $exception): void
    {
        // Let CrashGuard handle all exceptions
        CrashGuard::report($exception);

        // Still call parent for Laravel's default handling
        parent::report($exception);
    }
}
```

---

## 15. Configuration Tuning

### Ignoring Exceptions

By default, CrashGuard ignores common Laravel exceptions. Customize the list:

```php
// config/crash-guard.php

'ignored_exceptions' => [
    \Illuminate\Auth\AuthenticationException::class,
    \Illuminate\Validation\ValidationException::class,
    \Illuminate\Http\Exceptions\ThrottleRequestsException::class,
    \Illuminate\Auth\Access\AuthorizationException::class,
    \Illuminate\Session\TokenMismatchException::class,
    \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
    \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException::class,
    \Illuminate\Database\Eloquent\ModelNotFoundException::class,
    // Add your own:
    \App\Exceptions\ExpectedRetryException::class,
],
```

### Warning vs Error Level

Classify certain exceptions as `warning` level instead of `error`:

```php
'warning_exceptions' => [
    \Symfony\Component\HttpKernel\Exception\HttpException::class,
    \App\Exceptions\RateLimitExceededException::class,
],
```

### Queue Configuration

Notifications are dispatched via queue. Configure the connection and queue name:

```env
CRASH_GUARD_QUEUE_CONNECTION=redis
CRASH_GUARD_QUEUE_NAME=crash-notifications
```

### Built-in Scheduling

Automatically prune old reports via Laravel's scheduler:

```env
CRASH_GUARD_SCHEDULING_ENABLED=true
CRASH_GUARD_SCHEDULING_EXPRESSION=0 0 * * *
CRASH_GUARD_PRUNE_DAYS=30
```

When enabled, the service provider registers `crash-guard:prune` to run daily at midnight.

### Logging

Enable a dedicated log channel for debugging:

```env
CRASH_GUARD_LOGGING_ENABLED=true
```

Logs are written to `storage/logs/crash-guard.log` with daily rotation (7 days retention).

### Stack Trace Depth

Control how many stack trace lines are captured:

```env
CRASH_GUARD_TRACE_LINES=10
```

---

## Quick Reference

| Action | Code |
|--------|------|
| Report an exception | `crash_report($e)` |
| Report a message | `crash_report('Something went wrong')` |
| Urgent report | `CrashGuard::reportAsUrgent($e)` |
| Debug report | `CrashGuard::reportAsDebug($e)` |
| Report with context | `crash_report($e, ['key' => 'value'])` |
| Resolve a report | `CrashGuard::resolve($report)` |
| Mark as read | `CrashGuard::read($report)` |
| Ignore a report | `CrashGuard::ignore($report)` |
| Snooze notifications | `CrashGuard::pause($report, $until)` |
| Disable reporting | `CrashGuard::disable()` |
| Enable reporting | `CrashGuard::enable()` |
| Check status | `CrashGuard::isEnabled()` |
| Mark exception urgent | `#[UrgentCrashReport]` |
| Mark exception silent | `#[SilentCrashReport]` |
| Ignore exception class | `#[IgnoreCrashReport]` |
| Force report ignored | `#[ReportToCrashGuard]` |
| Debug mode exception | `#[DebugCrashReport]` |
