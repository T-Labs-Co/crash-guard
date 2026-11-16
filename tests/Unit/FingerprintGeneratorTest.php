<?php

declare(strict_types=1);

use TLabsCo\CrashGuard\Core\Reporting\Domain\Services\FingerprintGenerator;

it('generates fingerprint from Throwable', function () {
    $generator = new FingerprintGenerator;
    $error = new RuntimeException('test message');

    $fingerprint = $generator->generate($error);

    expect($fingerprint)->toBeString()->toHaveLength(32);
});

it('generates fingerprint from string', function () {
    $generator = new FingerprintGenerator;

    $fingerprint = $generator->generate('test error message');

    expect($fingerprint)->toBeString()->toHaveLength(32);
});

it('generates deterministic fingerprint for same throwable', function () {
    $generator = new FingerprintGenerator;

    $error1 = new RuntimeException('test message');
    $error2 = new RuntimeException('test message');

    // Same class, message, file, line should produce same fingerprint
    $fp1 = $generator->generate($error1);
    $fp2 = $generator->generate($error2);

    expect($fp1)->toBeString()->toHaveLength(32);
    expect($fp2)->toBeString()->toHaveLength(32);
});

it('generates deterministic fingerprint for same string', function () {
    $generator = new FingerprintGenerator;

    $fp1 = $generator->generate('exact same string');
    $fp2 = $generator->generate('exact same string');

    expect($fp1)->toBe($fp2);
});

it('generates different fingerprint for different messages', function () {
    $generator = new FingerprintGenerator;

    $fp1 = $generator->generate('message one');
    $fp2 = $generator->generate('message two');

    expect($fp1)->not->toBe($fp2);
});
