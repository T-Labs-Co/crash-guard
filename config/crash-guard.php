<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return [

    /*
    |--------------------------------------------------------------------------
    | Master Enable Switch
    |--------------------------------------------------------------------------
    | Can also be toggled at runtime via CrashGuard::enable() / disable().
    | The runtime cache flag takes precedence over this config value.
    */
    'enabled' => env('CRASH_GUARD_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Deduplication Window (minutes)
    |--------------------------------------------------------------------------
    | Suppress duplicate channel notifications for the same error fingerprint
    | within this many minutes. The DB record is still updated (occurrence_count
    | incremented, last_seen_at refreshed) even when notification is suppressed.
    */
    'dedup_minutes' => env('CRASH_GUARD_DEDUP_MINUTES', 60),

    /*
    |--------------------------------------------------------------------------
    | Active Notification Channels
    |--------------------------------------------------------------------------
    | Comma-separated list of channels to dispatch notifications to.
    | Supported: "slack", "mail"
    */
    'channels' => explode(',', (string) env('CRASH_GUARD_CHANNELS', 'slack,mail')),

    /*
    |--------------------------------------------------------------------------
    | Slack
    |--------------------------------------------------------------------------
    */
    'slack' => [
        'webhook_url' => env('CRASH_GUARD_SLACK_WEBHOOK'),
        'skip_environments' => explode(',', (string) env('CRASH_GUARD_SLACK_SKIP_ENVS', 'local')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mail
    |--------------------------------------------------------------------------
    | 'to' accepts multiple recipients — separate addresses with a comma in
    | the CRASH_GUARD_MAIL_TO env variable.
    */
    'mail' => [
        'to' => array_filter(explode(',', (string) env('CRASH_GUARD_MAIL_TO', ''))),
        'from' => (string) env('CRASH_GUARD_MAIL_FROM', env('MAIL_FROM_ADDRESS')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Prune After Days
    |--------------------------------------------------------------------------
    | Resolved and ignored reports older than this many days will be deleted
    | when running: php artisan crash-guard:prune
    */
    'prune_after_days' => env('CRASH_GUARD_PRUNE_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    | The queue connection and queue name used to dispatch notifications.
    | Set to null to use the application default.
    */
    'queue' => [
        'connection' => env('CRASH_GUARD_QUEUE_CONNECTION', config('queue.default')),
        'name' => env('CRASH_GUARD_QUEUE_NAME', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Report Mode
    |--------------------------------------------------------------------------
    | Controls how reports are treated by default.
    | Supported: "normal", "debug", "urgent"
    |
    |   normal — standard reporting and notification behaviour
    |   debug  — stored in DB only; notifications are suppressed (like silent)
    |   urgent — bypasses the deduplication window and always notifies
    */
    'mode' => env('CRASH_GUARD_MODE', 'normal'),

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker
    |--------------------------------------------------------------------------
    | Automatically silences notifications for a fingerprint that fires too
    | frequently within the cooldown window, preventing alert floods.
    |
    |   enabled          — toggle the circuit breaker on/off
    |   threshold        — max hits before the circuit opens (notifications stop)
    |   cooldown_seconds — how long (in seconds) the circuit stays open before
    |                      resetting. The counter restarts after this window.
    |
    | Urgent reports bypass the circuit breaker entirely.
    | Resolving a report resets its circuit counter immediately.
    */
    'circuit_breaker' => [
        'enabled' => env('CRASH_GUARD_CIRCUIT_BREAKER_ENABLED', true),
        'threshold' => env('CRASH_GUARD_CIRCUIT_BREAKER_THRESHOLD', 10),
        'cooldown_seconds' => env('CRASH_GUARD_CIRCUIT_BREAKER_COOLDOWN', 300),
        // Max occurrences per calendar day before notifications are silenced for that
        // fingerprint until midnight. Set to 0 to disable the per-fingerprint daily limit.
        'daily_limit' => env('CRASH_GUARD_CIRCUIT_BREAKER_DAILY_LIMIT', 10),
        // Max total occurrences across ALL fingerprints per calendar day. Once reached,
        // all non-urgent notifications are suppressed until midnight.
        // Set to 0 to disable the global daily limit.
        'global_daily_limit' => env('CRASH_GUARD_CIRCUIT_BREAKER_GLOBAL_DAILY_LIMIT', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Warning Exceptions
    |--------------------------------------------------------------------------
    | Exception classes listed here will be logged at 'warning' level instead
    | of 'error'. This improves alert quality by differentiating between
    | critical errors and expected warning-level exceptions.
    */
    'warning_exceptions' => [
        HttpException::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Trace Lines
    |--------------------------------------------------------------------------
    | Number of stack trace lines to capture when creating a new report.
    */
    'trace_lines' => (int) env('CRASH_GUARD_TRACE_LINES', 10),

    /*
    |--------------------------------------------------------------------------
    | Scheduling
    |--------------------------------------------------------------------------
    | Built-in scheduler configuration for the prune command.
    | When enabled, the service provider will register the prune command
    | with Laravel's scheduler automatically.
    */
    'scheduling' => [
        'enabled' => env('CRASH_GUARD_SCHEDULING_ENABLED', false),
        'expression' => env('CRASH_GUARD_SCHEDULING_EXPRESSION', '0 0 * * *'),
        'prune_days' => env('CRASH_GUARD_PRUNE_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ignored Exceptions
    |--------------------------------------------------------------------------
    | Exception classes listed here are silently skipped — no DB record, no
    | notification. Use #[IgnoreCrashReport] attribute or ShouldIgnoreCrashReport
    | interface for class-level control without modifying this config.
    */
    'ignored_exceptions' => [
        AuthenticationException::class,
        ValidationException::class,
        ThrottleRequestsException::class,
        AuthorizationException::class,
        TokenMismatchException::class,
        NotFoundHttpException::class,
        MethodNotAllowedHttpException::class,
        ModelNotFoundException::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channel Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the dedicated 'crash_guard' log channel.
    |
    */
    'logging' => [
        'enabled' => env('CRASH_GUARD_LOGGING_ENABLED', false),
        'driver' => env('CRASH_GUARD_LOGGING_ENABLED', false) ? 'daily' : 'null',
        'path' => storage_path('logs/crash-guard.log'),
        'level' => 'debug',
        'days' => 7,
    ],
];
