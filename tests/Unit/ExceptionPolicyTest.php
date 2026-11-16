<?php

declare(strict_types=1);

use TLabsCo\CrashGuard\Attributes\DebugCrashReport;
use TLabsCo\CrashGuard\Attributes\IgnoreCrashReport;
use TLabsCo\CrashGuard\Attributes\ReportToCrashGuard;
use TLabsCo\CrashGuard\Attributes\SilentCrashReport;
use TLabsCo\CrashGuard\Attributes\UrgentCrashReport;
use TLabsCo\CrashGuard\Core\Reporting\Domain\Contracts\ShouldIgnoreCrashReport;
use TLabsCo\CrashGuard\Core\Reporting\Domain\Contracts\ShouldReportToCrashGuard;
use TLabsCo\CrashGuard\Core\Reporting\Domain\Contracts\ShouldSilentCrashGuard;
use TLabsCo\CrashGuard\Core\Reporting\Domain\Contracts\ShouldUrgentCrashGuard;
use TLabsCo\CrashGuard\Core\Reporting\Domain\Services\ExceptionPolicy;

// Anonymous classes for testing attributes
#[IgnoreCrashReport]
class IgnoredAttributeException extends RuntimeException {}

#[ReportToCrashGuard]
class ReportedAttributeException extends RuntimeException {}

#[SilentCrashReport]
class SilentAttributeException extends RuntimeException {}

#[UrgentCrashReport]
class UrgentAttributeException extends RuntimeException {}

#[DebugCrashReport]
class DebugAttributeException extends RuntimeException {}

class SilentInterfaceException extends RuntimeException implements ShouldSilentCrashGuard {}
class UrgentInterfaceException extends RuntimeException implements ShouldUrgentCrashGuard {}
class IgnoredInterfaceException extends RuntimeException implements ShouldIgnoreCrashReport {}
class ReportedInterfaceException extends RuntimeException implements ShouldReportToCrashGuard {}

it('skips exception with IgnoreCrashReport attribute', function () {
    $policy = app(ExceptionPolicy::class);
    $error = new IgnoredAttributeException('test');

    expect($policy->shouldSkip($error))->toBeTrue();
});

it('skips exception implementing ShouldIgnoreCrashReport interface', function () {
    $policy = app(ExceptionPolicy::class);
    $error = new IgnoredInterfaceException('test');

    expect($policy->shouldSkip($error))->toBeTrue();
});

it('does not skip exception with ReportToCrashGuard attribute even if ignored', function () {
    $policy = app(ExceptionPolicy::class);
    $error = new ReportedInterfaceException('test');

    expect($policy->shouldSkip($error))->toBeFalse();
});

it('does not skip exception implementing ShouldReportToCrashGuard', function () {
    $policy = app(ExceptionPolicy::class);
    $error = new ReportedInterfaceException('test');

    expect($policy->shouldSkip($error))->toBeFalse();
});

it('skips exception listed in ignored_exceptions config', function () {
    config(['crash-guard.ignored_exceptions' => [RuntimeException::class]]);

    $policy = app(ExceptionPolicy::class);
    $error = new RuntimeException('test');

    expect($policy->shouldSkip($error))->toBeTrue();
});

it('does not skip regular exception by default', function () {
    config(['crash-guard.ignored_exceptions' => []]);

    $policy = app(ExceptionPolicy::class);
    $error = new RuntimeException('test');

    expect($policy->shouldSkip($error))->toBeFalse();
});

it('detects silent exception with SilentCrashReport attribute', function () {
    $policy = app(ExceptionPolicy::class);
    $error = new SilentAttributeException('test');

    expect($policy->isSilent($error))->toBeTrue();
});

it('detects silent exception implementing ShouldSilentCrashGuard', function () {
    $policy = app(ExceptionPolicy::class);
    $error = new SilentInterfaceException('test');

    expect($policy->isSilent($error))->toBeTrue();
});

it('returns false for non-silent exception', function () {
    $policy = app(ExceptionPolicy::class);
    $error = new RuntimeException('test');

    expect($policy->isSilent($error))->toBeFalse();
});

it('detects urgent exception with UrgentCrashReport attribute', function () {
    $policy = app(ExceptionPolicy::class);
    $error = new UrgentAttributeException('test');

    expect($policy->isUrgent($error))->toBeTrue();
});

it('detects urgent exception implementing ShouldUrgentCrashGuard', function () {
    $policy = app(ExceptionPolicy::class);
    $error = new UrgentInterfaceException('test');

    expect($policy->isUrgent($error))->toBeTrue();
});

it('returns false for non-urgent exception', function () {
    $policy = app(ExceptionPolicy::class);
    $error = new RuntimeException('test');

    expect($policy->isUrgent($error))->toBeFalse();
});

it('detects debug exception with DebugCrashReport attribute', function () {
    $policy = app(ExceptionPolicy::class);
    $error = new DebugAttributeException('test');

    expect($policy->isDebug($error))->toBeTrue();
});

it('returns false for non-debug exception', function () {
    $policy = app(ExceptionPolicy::class);
    $error = new RuntimeException('test');

    expect($policy->isDebug($error))->toBeFalse();
});
