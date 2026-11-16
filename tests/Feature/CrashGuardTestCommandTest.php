<?php

declare(strict_types=1);

use TLabsCo\CrashGuard\Core\Reporting\Domain\Enums\CrashReportMode;
use TLabsCo\CrashGuard\Core\Reporting\Domain\Enums\ReportStatus;
use TLabsCo\CrashGuard\Models\CrashReport;

it('runs --case=all successfully', function () {
    $this->artisan('crash-guard:test --case=all')
        ->assertSuccessful();
});

it('runs individual exception case', function () {
    $this->artisan('crash-guard:test --case=exception')
        ->assertSuccessful();

    expect(CrashReport::query()->where('context->case', 'exception')->count())->toBe(1);
});

it('runs individual string case', function () {
    $this->artisan('crash-guard:test --case=string')
        ->assertSuccessful();

    expect(CrashReport::query()->where('context->case', 'string')->count())->toBe(1);
});

it('runs individual silent case', function () {
    $this->artisan('crash-guard:test --case=silent')
        ->assertSuccessful();

    expect(CrashReport::query()->where('context->case', 'silent')->count())->toBe(1);
});

it('runs individual urgent case', function () {
    $this->artisan('crash-guard:test --case=urgent')
        ->assertSuccessful();

    expect(CrashReport::query()->where('context->case', 'urgent')->count())->toBe(1);
});

it('runs individual debug case', function () {
    $this->artisan('crash-guard:test --case=debug')
        ->assertSuccessful();

    expect(CrashReport::query()->where('context->case', 'debug')->count())->toBe(1);
});

it('runs all cases and creates reports', function () {
    $this->artisan('crash-guard:test --case=all')
        ->assertSuccessful();

    // exception, string, silent, urgent, debug, resolve, read, ignore, pause = 9 reports (disabled does not create)
    expect(CrashReport::query()->count())->toBe(9);
});

it('runs resolve, read, ignore, pause cases without errors', function () {
    // First create a report to operate on
    $report = CrashReport::query()->create([
        'fingerprint' => 'fp-lifecycle',
        'level' => 'error',
        'title' => 'Lifecycle',
        'message' => 'Lifecycle test',
        'status' => ReportStatus::Pending,
        'mode' => CrashReportMode::Normal,
        'occurrence_count' => 1,
        'first_seen_at' => now(),
        'last_seen_at' => now(),
    ]);

    // These cases should succeed
    $this->artisan('crash-guard:test --case=resolve')->assertSuccessful();
    $this->artisan('crash-guard:test --case=read')->assertSuccessful();
    $this->artisan('crash-guard:test --case=ignore')->assertSuccessful();
    $this->artisan('crash-guard:test --case=pause')->assertSuccessful();
});
