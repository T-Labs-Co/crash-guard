<?php

use TLabsCo\CrashGuard\Facades\CrashGuard;
use TLabsCo\CrashGuard\Models\CrashReport;

if (! function_exists('crash_report')) {
    /**
     * Quickly report an error or exception to CrashGuard.
     *
     * @param  Throwable|string  $error  The exception or error message to report.
     * @param  array<string, mixed>  $context  Additional context data.
     * @return CrashReport|null The created report or null if suppressed.
     */
    function crash_report(Throwable|string $error, array $context = []): ?CrashReport
    {
        return CrashGuard::report($error, $context);
    }
}
