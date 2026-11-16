<?php

declare(strict_types=1);

use Symfony\Component\HttpKernel\Exception\HttpException;
use TLabsCo\CrashGuard\Core\Reporting\Domain\Services\ErrorLevelResolver;

it('returns warning for HttpException by default', function () {
    config(['crash-guard.warning_exceptions' => [HttpException::class]]);

    $resolver = new ErrorLevelResolver;
    $error = new HttpException(404, 'Not found');

    expect($resolver->resolve($error))->toBe('warning');
});

it('returns error for regular exceptions', function () {
    config(['crash-guard.warning_exceptions' => [HttpException::class]]);

    $resolver = new ErrorLevelResolver;
    $error = new RuntimeException('Something went wrong');

    expect($resolver->resolve($error))->toBe('error');
});

it('returns error for string input', function () {
    $resolver = new ErrorLevelResolver;

    expect($resolver->resolve('plain string error'))->toBe('error');
});

it('respects custom warning_exceptions config', function () {
    config(['crash-guard.warning_exceptions' => [InvalidArgumentException::class]]);

    $resolver = new ErrorLevelResolver;

    expect($resolver->resolve(new InvalidArgumentException('bad arg')))->toBe('warning');
    expect($resolver->resolve(new RuntimeException('other')))->toBe('error');
});

it('returns error when warning_exceptions is empty', function () {
    config(['crash-guard.warning_exceptions' => []]);

    $resolver = new ErrorLevelResolver;
    $error = new HttpException(500, 'Server error');

    expect($resolver->resolve($error))->toBe('error');
});
