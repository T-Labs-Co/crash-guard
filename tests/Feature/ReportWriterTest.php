<?php

declare(strict_types=1);

use TLabsCo\CrashGuard\Core\Reporting\Application\Services\ReportWriter;
use TLabsCo\CrashGuard\Models\CrashReport;

it('creates a new report when fingerprint does not exist', function () {
    $writer = app(ReportWriter::class);

    $report = $writer->record('fp-new-1', new RuntimeException('New error'));

    expect($report)->toBeInstanceOf(CrashReport::class)
        ->and($report->fingerprint)->toBe('fp-new-1')
        ->and($report->occurrence_count)->toBe(1)
        ->and($report->wasRecentlyCreated)->toBeTrue();
});

it('increments existing report instead of creating duplicate', function () {
    $writer = app(ReportWriter::class);

    $first = $writer->record('fp-existing', new RuntimeException('First occurrence'));
    expect($first->occurrence_count)->toBe(1);

    $second = $writer->record('fp-existing', new RuntimeException('Second occurrence'));
    expect($second->occurrence_count)->toBe(2)
        ->and($second->id)->toBe($first->id)
        ->and($second->wasRecentlyCreated)->toBeFalse();
});

it('updates last_seen_at on increment', function () {
    $writer = app(ReportWriter::class);

    $first = $writer->record('fp-time', new RuntimeException('First'));
    $firstLastSeen = $first->last_seen_at;

    // Simulate time passing
    usleep(100000);

    $second = $writer->record('fp-time', new RuntimeException('Second'));
    expect($second->last_seen_at->gte($firstLastSeen))->toBeTrue();
});

it('sets notified_at when not silent', function () {
    $writer = app(ReportWriter::class);

    $report = $writer->record('fp-notified', new RuntimeException('Error'), null, silent: false);

    expect($report->notified_at)->not->toBeNull();
});

it('leaves notified_at null when silent', function () {
    $writer = app(ReportWriter::class);

    $report = $writer->record('fp-silent', new RuntimeException('Error'), null, silent: true);

    expect($report->notified_at)->toBeNull();
});

it('stores context data on new report', function () {
    $writer = app(ReportWriter::class);

    $context = ['user_id' => 'user-1', 'ip' => '127.0.0.1', 'custom' => 'data'];
    $report = $writer->record('fp-context', new RuntimeException('Error'), $context);

    expect($report->context)->toBe($context)
        ->and($report->user_id)->toBe('user-1')
        ->and($report->ip)->toBe('127.0.0.1');
});

it('creates report from string message', function () {
    $writer = app(ReportWriter::class);

    $report = $writer->record('fp-string', 'Plain string error');

    expect($report)->toBeInstanceOf(CrashReport::class)
        ->and($report->message)->toBe('Plain string error');
});
