<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use TLabsCo\CrashGuard\Core\Reporting\Application\DTOs\ReportContextDTO;
use TLabsCo\CrashGuard\Facades\CrashGuard;
use TLabsCo\CrashGuard\Models\CrashReport;

beforeEach(function () {
    Queue::fake();
});

it('reports a runtime exception and creates a crash report', function () {
    $report = CrashGuard::report(new RuntimeException('Test error'));

    expect($report)->toBeInstanceOf(CrashReport::class)
        ->and($report->message)->toBe('Test error')
        ->and($report->occurrence_count)->toBe(1);
});

it('reports a string message', function () {
    $report = CrashGuard::report('String error message');

    expect($report)->toBeInstanceOf(CrashReport::class)
        ->and($report->message)->toBe('String error message');
});

it('returns null for skipped exceptions', function () {
    config(['crash-guard.ignored_exceptions' => [InvalidArgumentException::class]]);

    $report = CrashGuard::report(new InvalidArgumentException('Should be skipped'));

    expect($report)->toBeNull();
});

it('returns null when crash guard is disabled', function () {
    CrashGuard::disable();

    $report = CrashGuard::report(new RuntimeException('Disabled'));

    expect($report)->toBeNull();
});

it('enables and disables crash guard at runtime', function () {
    CrashGuard::disable();
    expect(CrashGuard::isEnabled())->toBeFalse();

    CrashGuard::enable();
    expect(CrashGuard::isEnabled())->toBeTrue();
});

it('reports as urgent', function () {
    $report = CrashGuard::reportAsUrgent(new RuntimeException('Urgent error'));

    expect($report)->toBeInstanceOf(CrashReport::class);
});

it('reports as debug', function () {
    $report = CrashGuard::reportAsDebug(new RuntimeException('Debug error'));

    expect($report)->toBeInstanceOf(CrashReport::class);
});

it('reports with context DTO', function () {
    $context = new ReportContextDTO(
        userId: 'user-1',
        userEmail: 'user@test.com',
        ip: '127.0.0.1',
    );

    $report = CrashGuard::reportWithContext(new RuntimeException('Context error'), $context);

    expect($report)->toBeInstanceOf(CrashReport::class)
        ->and($report->user_id)->toBe('user-1')
        ->and($report->user_email)->toBe('user@test.com')
        ->and($report->ip)->toBe('127.0.0.1');
});

it('resolves a report', function () {
    $report = CrashGuard::report(new RuntimeException('To resolve'));

    CrashGuard::resolve($report->id);

    $refreshed = CrashReport::find($report->id);
    expect($refreshed->status->value)->toBe('resolved')
        ->and($refreshed->resolved_at)->not->toBeNull();
});

it('marks a report as read', function () {
    $report = CrashGuard::report(new RuntimeException('To read'));
    expect($report->read_at)->toBeNull();

    CrashGuard::read($report->id);

    $refreshed = CrashReport::find($report->id);
    expect($refreshed->read_at)->not->toBeNull();
});

it('ignores a report', function () {
    $report = CrashGuard::report(new RuntimeException('To ignore'));

    CrashGuard::ignore($report->id);

    $refreshed = CrashReport::find($report->id);
    expect($refreshed->status->value)->toBe('ignored')
        ->and($refreshed->ignored_at)->not->toBeNull();
});

it('pauses a report', function () {
    $report = CrashGuard::report(new RuntimeException('To pause'));

    $until = now()->addHours(2);
    CrashGuard::pause($report->id, $until);

    $refreshed = CrashReport::find($report->id);
    expect($refreshed->paused_until)->not->toBeNull();
});

it('increments occurrence count on repeated reports', function () {
    // Use string messages so the fingerprint is deterministic
    $first = CrashGuard::report('Repeated string error');
    $second = CrashGuard::report('Repeated string error');

    expect($second->occurrence_count)->toBeGreaterThan(1)
        ->and($second->id)->toBe($first->id);
});
