<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use TLabsCo\CrashGuard\Core\Reporting\Application\Services\CircuitBreaker;

beforeEach(function () {
    Cache::flush();
});

it('returns false when circuit breaker is disabled', function () {
    config(['crash-guard.circuit_breaker.enabled' => false]);

    $breaker = new CircuitBreaker;
    $breaker->record('fp-123');

    expect($breaker->isOpen('fp-123'))->toBeFalse();
});

it('returns false when hits are below threshold', function () {
    config(['crash-guard.circuit_breaker.enabled' => true]);
    config(['crash-guard.circuit_breaker.threshold' => 10]);
    config(['crash-guard.circuit_breaker.cooldown_seconds' => 300]);

    $breaker = new CircuitBreaker;

    // Record 5 hits (below threshold of 10)
    for ($i = 0; $i < 5; $i++) {
        $breaker->record('fp-123');
    }

    expect($breaker->isOpen('fp-123'))->toBeFalse();
});

it('returns true when hits reach threshold', function () {
    config(['crash-guard.circuit_breaker.enabled' => true]);
    config(['crash-guard.circuit_breaker.threshold' => 5]);
    config(['crash-guard.circuit_breaker.cooldown_seconds' => 300]);

    $breaker = new CircuitBreaker;

    for ($i = 0; $i < 5; $i++) {
        $breaker->record('fp-123');
    }

    expect($breaker->isOpen('fp-123'))->toBeTrue();
});

it('resets circuit breaker for a fingerprint', function () {
    config(['crash-guard.circuit_breaker.enabled' => true]);
    config(['crash-guard.circuit_breaker.threshold' => 3]);
    config(['crash-guard.circuit_breaker.cooldown_seconds' => 300]);

    $breaker = new CircuitBreaker;

    for ($i = 0; $i < 3; $i++) {
        $breaker->record('fp-123');
    }

    expect($breaker->isOpen('fp-123'))->toBeTrue();

    $breaker->reset('fp-123');

    expect($breaker->isOpen('fp-123'))->toBeFalse();
});

it('does not record when disabled', function () {
    config(['crash-guard.circuit_breaker.enabled' => false]);
    config(['crash-guard.circuit_breaker.threshold' => 1]);

    $breaker = new CircuitBreaker;
    $breaker->record('fp-123');
    $breaker->record('fp-123');

    expect($breaker->isOpen('fp-123'))->toBeFalse();
});

it('checks daily limit when configured', function () {
    config(['crash-guard.circuit_breaker.enabled' => true]);
    config(['crash-guard.circuit_breaker.threshold' => 100]);
    config(['crash-guard.circuit_breaker.daily_limit' => 3]);

    $breaker = new CircuitBreaker;

    for ($i = 0; $i < 3; $i++) {
        $breaker->record('fp-daily');
    }

    expect($breaker->isDailyLimitExceeded('fp-daily'))->toBeTrue();
});

it('returns false for daily limit when not configured', function () {
    config(['crash-guard.circuit_breaker.enabled' => true]);
    config(['crash-guard.circuit_breaker.daily_limit' => 0]);

    $breaker = new CircuitBreaker;

    for ($i = 0; $i < 100; $i++) {
        $breaker->record('fp-daily');
    }

    expect($breaker->isDailyLimitExceeded('fp-daily'))->toBeFalse();
});

it('checks global daily limit when configured', function () {
    config(['crash-guard.circuit_breaker.enabled' => true]);
    config(['crash-guard.circuit_breaker.threshold' => 100]);
    config(['crash-guard.circuit_breaker.global_daily_limit' => 5]);

    $breaker = new CircuitBreaker;

    for ($i = 0; $i < 5; $i++) {
        $breaker->record('fp-global-'.$i);
    }

    expect($breaker->isGlobalDailyLimitExceeded())->toBeTrue();
});

it('returns false for global daily limit when not configured', function () {
    config(['crash-guard.circuit_breaker.enabled' => true]);
    config(['crash-guard.circuit_breaker.global_daily_limit' => 0]);

    $breaker = new CircuitBreaker;

    for ($i = 0; $i < 100; $i++) {
        $breaker->record('fp-global-'.$i);
    }

    expect($breaker->isGlobalDailyLimitExceeded())->toBeFalse();
});
