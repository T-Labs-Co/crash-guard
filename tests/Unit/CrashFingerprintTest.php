<?php

declare(strict_types=1);

use TLabsCo\CrashGuard\Core\Reporting\Domain\ValueObjects\CrashFingerprint;

it('creates fingerprint from Throwable', function () {
    $error = new RuntimeException('test error');

    $fp = CrashFingerprint::fromThrowable($error);

    expect((string) $fp)->toBeString()->toHaveLength(32);
});

it('creates fingerprint from string', function () {
    $fp = CrashFingerprint::fromString('error message');

    expect((string) $fp)->toBeString()->toHaveLength(32);
});

it('returns correct value via __toString', function () {
    $fp = CrashFingerprint::fromString('test');

    expect((string) $fp)->toBe(md5('test'));
});

it('produces deterministic fingerprint from same string', function () {
    $fp1 = CrashFingerprint::fromString('same message');
    $fp2 = CrashFingerprint::fromString('same message');

    expect((string) $fp1)->toBe((string) $fp2);
});

it('produces different fingerprint from different strings', function () {
    $fp1 = CrashFingerprint::fromString('message one');
    $fp2 = CrashFingerprint::fromString('message two');

    expect((string) $fp1)->not->toBe((string) $fp2);
});
