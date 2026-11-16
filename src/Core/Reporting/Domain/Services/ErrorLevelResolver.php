<?php

namespace TLabsCo\CrashGuard\Core\Reporting\Domain\Services;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Resolves the log level for a given error.
 * Returns 'warning' for configured warning exceptions, 'error' for everything else.
 */
class ErrorLevelResolver
{
    public function resolve(Throwable|string $error): string
    {
        if (! $error instanceof Throwable) {
            return 'error';
        }

        $class = $error::class;

        $warnings = config('crash-guard.warning_exceptions', [
            HttpException::class,
        ]);

        foreach ($warnings as $warningClass) {
            if ($error instanceof $warningClass) {
                return 'warning';
            }
        }

        return 'error';
    }
}
