<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use TLabsCo\CrashGuard\Core\Reporting\Domain\Enums\CrashReportMode;
use TLabsCo\CrashGuard\Core\Reporting\Domain\Enums\ReportStatus;
use TLabsCo\CrashGuard\Models\CrashReport;

it('deletes resolved reports older than specified days', function () {
    // Old resolved report (40 days old)
    $old = now()->subDays(40);
    $recent = now()->subDays(5);

    DB::table('crash_guard_reports')->insert([
        'fingerprint' => 'fp-old',
        'level' => 'error',
        'title' => 'Old',
        'message' => 'Old error',
        'status' => ReportStatus::Resolved->value,
        'mode' => CrashReportMode::Normal->value,
        'occurrence_count' => 1,
        'first_seen_at' => $old,
        'last_seen_at' => $old,
        'created_at' => $old,
        'updated_at' => $old,
    ]);

    // Recent resolved report (5 days old)
    DB::table('crash_guard_reports')->insert([
        'fingerprint' => 'fp-recent',
        'level' => 'error',
        'title' => 'Recent',
        'message' => 'Recent error',
        'status' => ReportStatus::Resolved->value,
        'mode' => CrashReportMode::Normal->value,
        'occurrence_count' => 1,
        'first_seen_at' => $recent,
        'last_seen_at' => $recent,
        'created_at' => $recent,
        'updated_at' => $recent,
    ]);

    $this->artisan('crash-guard:prune --days=30')
        ->assertSuccessful()
        ->expectsOutput('Pruned 1 crash report(s) older than 30 day(s).');

    expect(CrashReport::query()->count())->toBe(1);
});

it('deletes ignored reports older than specified days', function () {
    $old = now()->subDays(50);
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

    $this->artisan('crash-guard:prune --days=30')
        ->assertSuccessful();

    expect(CrashReport::query()->where('fingerprint', 'fp-old-ignored')->count())->toBe(0);
});

it('does not delete pending reports', function () {
    CrashReport::query()->create([
        'fingerprint' => 'fp-pending',
        'level' => 'error',
        'title' => 'Pending',
        'message' => 'Pending error',
        'status' => ReportStatus::Pending,
        'mode' => CrashReportMode::Normal,
        'occurrence_count' => 1,
        'first_seen_at' => now()->subDays(60),
        'last_seen_at' => now()->subDays(60),
    ]);

    $this->artisan('crash-guard:prune --days=30')
        ->assertSuccessful();

    expect(CrashReport::query()->count())->toBe(1);
});

it('fails with negative days value', function () {
    $this->artisan('crash-guard:prune --days=-1')
        ->assertFailed()
        ->expectsOutput('The --days value must be a positive integer.');
});

it('uses config default days when --days not provided', function () {
    config(['crash-guard.prune_after_days' => 30]);

    $old = now()->subDays(40);
    DB::table('crash_guard_reports')->insert([
        'fingerprint' => 'fp-config-days',
        'level' => 'error',
        'title' => 'Config days',
        'message' => 'Config days error',
        'status' => ReportStatus::Resolved->value,
        'mode' => CrashReportMode::Normal->value,
        'occurrence_count' => 1,
        'first_seen_at' => $old,
        'last_seen_at' => $old,
        'created_at' => $old,
        'updated_at' => $old,
    ]);

    $this->artisan('crash-guard:prune')
        ->assertSuccessful();

    expect(CrashReport::query()->where('fingerprint', 'fp-config-days')->count())->toBe(0);
});

it('prunes zero reports when none are eligible', function () {
    CrashReport::query()->create([
        'fingerprint' => 'fp-recent',
        'level' => 'error',
        'title' => 'Recent',
        'message' => 'Recent error',
        'status' => ReportStatus::Resolved,
        'mode' => CrashReportMode::Normal,
        'occurrence_count' => 1,
        'first_seen_at' => now()->subDays(5),
        'last_seen_at' => now()->subDays(5),
    ]);

    $this->artisan('crash-guard:prune --days=30')
        ->assertSuccessful()
        ->expectsOutput('Pruned 0 crash report(s) older than 30 day(s).');
});
