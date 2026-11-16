<?php

declare(strict_types=1);

use TLabsCo\CrashGuard\Core\Reporting\Application\Services\NotificationDispatcher;
use TLabsCo\CrashGuard\Core\Reporting\Domain\Enums\CrashReportMode;
use TLabsCo\CrashGuard\Core\Reporting\Domain\Enums\ReportStatus;
use TLabsCo\CrashGuard\Models\CrashReport;

it('does not dispatch for ignored reports', function () {
    $dispatcher = app(NotificationDispatcher::class);

    $report = CrashReport::query()->create([
        'fingerprint' => 'fp-ignored',
        'level' => 'error',
        'title' => 'Ignored',
        'message' => 'Ignored error',
        'status' => ReportStatus::Ignored,
        'mode' => CrashReportMode::Normal,
        'occurrence_count' => 1,
        'first_seen_at' => now(),
        'last_seen_at' => now(),
        'notified_at' => null,
    ]);

    expect($dispatcher->shouldDispatch($report))->toBeFalse();
});

it('does not dispatch when paused_until is in the future', function () {
    $dispatcher = app(NotificationDispatcher::class);

    $report = CrashReport::query()->create([
        'fingerprint' => 'fp-paused',
        'level' => 'error',
        'title' => 'Paused',
        'message' => 'Paused error',
        'status' => ReportStatus::Pending,
        'mode' => CrashReportMode::Normal,
        'occurrence_count' => 1,
        'first_seen_at' => now(),
        'last_seen_at' => now(),
        'notified_at' => now(),
        'paused_until' => now()->addHours(1),
    ]);

    expect($dispatcher->shouldDispatch($report))->toBeFalse();
});

it('dispatches when paused_until has expired', function () {
    $dispatcher = app(NotificationDispatcher::class);

    $report = CrashReport::query()->create([
        'fingerprint' => 'fp-expired-pause',
        'level' => 'error',
        'title' => 'Expired pause',
        'message' => 'Expired pause error',
        'status' => ReportStatus::Pending,
        'mode' => CrashReportMode::Normal,
        'occurrence_count' => 1,
        'first_seen_at' => now(),
        'last_seen_at' => now(),
        'notified_at' => now()->subHours(2),
        'paused_until' => now()->subHours(1),
    ]);

    expect($dispatcher->shouldDispatch($report))->toBeTrue();
});

it('always dispatches for urgent mode', function () {
    $dispatcher = app(NotificationDispatcher::class);

    $report = CrashReport::query()->create([
        'fingerprint' => 'fp-urgent',
        'level' => 'error',
        'title' => 'Urgent',
        'message' => 'Urgent error',
        'status' => ReportStatus::Pending,
        'mode' => CrashReportMode::Urgent,
        'occurrence_count' => 1,
        'first_seen_at' => now(),
        'last_seen_at' => now(),
        'notified_at' => now(), // Within dedup window
    ]);

    expect($dispatcher->shouldDispatch($report, CrashReportMode::Urgent))->toBeTrue();
});

it('does not dispatch within dedup window', function () {
    config(['crash-guard.dedup_minutes' => 60]);

    $dispatcher = app(NotificationDispatcher::class);

    $report = CrashReport::query()->create([
        'fingerprint' => 'fp-dedup',
        'level' => 'error',
        'title' => 'Dedup',
        'message' => 'Dedup error',
        'status' => ReportStatus::Pending,
        'mode' => CrashReportMode::Normal,
        'occurrence_count' => 2,
        'first_seen_at' => now()->subHours(1),
        'last_seen_at' => now(),
        'notified_at' => now()->subMinutes(10), // 10 minutes ago, within 60-min window
    ]);

    expect($dispatcher->shouldDispatch($report))->toBeFalse();
});

it('dispatches when notified_at is null', function () {
    $dispatcher = app(NotificationDispatcher::class);

    $report = CrashReport::query()->create([
        'fingerprint' => 'fp-not-notified',
        'level' => 'error',
        'title' => 'Not notified',
        'message' => 'Not notified error',
        'status' => ReportStatus::Pending,
        'mode' => CrashReportMode::Normal,
        'occurrence_count' => 1,
        'first_seen_at' => now(),
        'last_seen_at' => now(),
        'notified_at' => null,
    ]);

    expect($dispatcher->shouldDispatch($report))->toBeTrue();
});

it('dispatches after dedup window expires', function () {
    config(['crash-guard.dedup_minutes' => 30]);

    $dispatcher = app(NotificationDispatcher::class);

    $report = CrashReport::query()->create([
        'fingerprint' => 'fp-dedup-expired',
        'level' => 'error',
        'title' => 'Dedup expired',
        'message' => 'Dedup expired error',
        'status' => ReportStatus::Pending,
        'mode' => CrashReportMode::Normal,
        'occurrence_count' => 2,
        'first_seen_at' => now()->subHours(2),
        'last_seen_at' => now(),
        'notified_at' => now()->subHours(1), // 60 minutes ago, outside 30-min window
    ]);

    expect($dispatcher->shouldDispatch($report))->toBeTrue();
});
