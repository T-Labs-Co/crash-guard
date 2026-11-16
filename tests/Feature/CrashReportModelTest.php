<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use TLabsCo\CrashGuard\Core\Reporting\Domain\Enums\CrashReportMode;
use TLabsCo\CrashGuard\Core\Reporting\Domain\Enums\ReportStatus;
use TLabsCo\CrashGuard\Models\CrashReport;

it('finds pending reports', function () {
    CrashReport::query()->create([
        'fingerprint' => 'fp-pending',
        'level' => 'error',
        'title' => 'Pending',
        'message' => 'Pending error',
        'status' => ReportStatus::Pending,
        'mode' => CrashReportMode::Normal,
        'occurrence_count' => 1,
        'first_seen_at' => now(),
        'last_seen_at' => now(),
    ]);

    CrashReport::query()->create([
        'fingerprint' => 'fp-resolved',
        'level' => 'error',
        'title' => 'Resolved',
        'message' => 'Resolved error',
        'status' => ReportStatus::Resolved,
        'mode' => CrashReportMode::Normal,
        'occurrence_count' => 1,
        'first_seen_at' => now(),
        'last_seen_at' => now(),
    ]);

    expect(CrashReport::query()->pending()->count())->toBe(1);
});

it('finds active reports (pending and not snoozed)', function () {
    // Active: pending, no pause
    CrashReport::query()->create([
        'fingerprint' => 'fp-active',
        'level' => 'error',
        'title' => 'Active',
        'message' => 'Active error',
        'status' => ReportStatus::Pending,
        'mode' => CrashReportMode::Normal,
        'occurrence_count' => 1,
        'first_seen_at' => now(),
        'last_seen_at' => now(),
    ]);

    // Snoozed: pending, paused until future
    CrashReport::query()->create([
        'fingerprint' => 'fp-snoozed',
        'level' => 'error',
        'title' => 'Snoozed',
        'message' => 'Snoozed error',
        'status' => ReportStatus::Pending,
        'mode' => CrashReportMode::Normal,
        'occurrence_count' => 1,
        'first_seen_at' => now(),
        'last_seen_at' => now(),
        'paused_until' => now()->addHours(1),
    ]);

    // Expired pause: pending, paused until past
    CrashReport::query()->create([
        'fingerprint' => 'fp-expired-pause',
        'level' => 'error',
        'title' => 'Expired pause',
        'message' => 'Expired pause error',
        'status' => ReportStatus::Pending,
        'mode' => CrashReportMode::Normal,
        'occurrence_count' => 1,
        'first_seen_at' => now(),
        'last_seen_at' => now(),
        'paused_until' => now()->subHours(1),
    ]);

    expect(CrashReport::query()->active()->count())->toBe(2);
});

it('finds snoozed reports', function () {
    CrashReport::query()->create([
        'fingerprint' => 'fp-snoozed-1',
        'level' => 'error',
        'title' => 'Snoozed 1',
        'message' => 'Snoozed error 1',
        'status' => ReportStatus::Pending,
        'mode' => CrashReportMode::Normal,
        'occurrence_count' => 1,
        'first_seen_at' => now(),
        'last_seen_at' => now(),
        'paused_until' => now()->addHours(2),
    ]);

    CrashReport::query()->create([
        'fingerprint' => 'fp-snoozed-2',
        'level' => 'error',
        'title' => 'Snoozed 2',
        'message' => 'Snoozed error 2',
        'status' => ReportStatus::Pending,
        'mode' => CrashReportMode::Normal,
        'occurrence_count' => 1,
        'first_seen_at' => now(),
        'last_seen_at' => now(),
        'paused_until' => now()->addMinutes(30),
    ]);

    // Not snoozed
    CrashReport::query()->create([
        'fingerprint' => 'fp-not-snoozed',
        'level' => 'error',
        'title' => 'Not snoozed',
        'message' => 'Not snoozed error',
        'status' => ReportStatus::Pending,
        'mode' => CrashReportMode::Normal,
        'occurrence_count' => 1,
        'first_seen_at' => now(),
        'last_seen_at' => now(),
    ]);

    expect(CrashReport::query()->snoozed()->count())->toBe(2);
});

it('finds pruneable reports (resolved/ignored older than days)', function () {
    $old = now()->subDays(40);
    $recent = now()->subDays(5);

    // Pruneable: resolved, 40 days old
    DB::table('crash_guard_reports')->insert([
        'fingerprint' => 'fp-old-resolved',
        'level' => 'error',
        'title' => 'Old resolved',
        'message' => 'Old resolved error',
        'status' => ReportStatus::Resolved->value,
        'mode' => CrashReportMode::Normal->value,
        'occurrence_count' => 1,
        'first_seen_at' => $old,
        'last_seen_at' => $old,
        'created_at' => $old,
        'updated_at' => $old,
    ]);

    // Pruneable: ignored, 40 days old
    DB::table('crash_guard_reports')->insert([
        'fingerprint' => 'fp-old-ignored',
        'level' => 'error',
        'title' => 'Old ignored',
        'message' => 'Old ignored error',
        'status' => ReportStatus::Ignored->value,
        'mode' => CrashReportMode::Normal->value,
        'occurrence_count' => 1,
        'first_seen_at' => $old,
        'last_seen_at' => $old,
        'created_at' => $old,
        'updated_at' => $old,
    ]);

    // Not pruneable: pending
    CrashReport::query()->create([
        'fingerprint' => 'fp-pending',
        'level' => 'error',
        'title' => 'Pending',
        'message' => 'Pending error',
        'status' => ReportStatus::Pending,
        'mode' => CrashReportMode::Normal,
        'occurrence_count' => 1,
        'first_seen_at' => now(),
        'last_seen_at' => now(),
    ]);

    // Not pruneable: resolved but recent
    DB::table('crash_guard_reports')->insert([
        'fingerprint' => 'fp-recent-resolved',
        'level' => 'error',
        'title' => 'Recent resolved',
        'message' => 'Recent resolved error',
        'status' => ReportStatus::Resolved->value,
        'mode' => CrashReportMode::Normal->value,
        'occurrence_count' => 1,
        'first_seen_at' => $recent,
        'last_seen_at' => $recent,
        'created_at' => $recent,
        'updated_at' => $recent,
    ]);

    expect(CrashReport::query()->pruneable(30)->count())->toBe(2);
});
