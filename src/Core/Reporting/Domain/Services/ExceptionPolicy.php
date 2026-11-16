<?php

namespace TLabsCo\CrashGuard\Core\Reporting\Domain\Services;

use ReflectionClass;
use Throwable;
use TLabsCo\CrashGuard\Attributes\DebugCrashReport;
use TLabsCo\CrashGuard\Attributes\IgnoreCrashReport;
use TLabsCo\CrashGuard\Attributes\ReportToCrashGuard;
use TLabsCo\CrashGuard\Attributes\SilentCrashReport;
use TLabsCo\CrashGuard\Attributes\UrgentCrashReport;
use TLabsCo\CrashGuard\Core\Reporting\Domain\Contracts\ShouldIgnoreCrashReport;
use TLabsCo\CrashGuard\Core\Reporting\Domain\Contracts\ShouldReportToCrashGuard;
use TLabsCo\CrashGuard\Core\Reporting\Domain\Contracts\ShouldSilentCrashGuard;
use TLabsCo\CrashGuard\Core\Reporting\Domain\Contracts\ShouldUrgentCrashGuard;

class ExceptionPolicy
{
    /**
     * Determine whether to skip capturing this exception entirely.
     *
     * Priority (highest → lowest):
     *  1. ShouldReportToCrashGuard interface / #[ReportToCrashGuard] → never skip
     *  2. ShouldIgnoreCrashReport interface / #[IgnoreCrashReport]   → always skip
     *  3. ignored_exceptions[] config list                            → skip
     *  4. Default                                                     → do not skip
     */
    public function shouldSkip(Throwable $error): bool
    {
        $reflection = new ReflectionClass($error);

        if ($error instanceof ShouldReportToCrashGuard || $reflection->getAttributes(ReportToCrashGuard::class) !== []) {
            return false;
        }

        if ($error instanceof ShouldIgnoreCrashReport || $reflection->getAttributes(IgnoreCrashReport::class) !== []) {
            return true;
        }

        return in_array($error::class, config('crash-guard.ignored_exceptions', []), strict: true);
    }

    /**
     * Determine whether the exception should be stored silently (DB only, no notification).
     */
    public function isSilent(Throwable $error): bool
    {
        $reflection = new ReflectionClass($error);

        return $error instanceof ShouldSilentCrashGuard
            || $reflection->getAttributes(SilentCrashReport::class) !== [];
    }

    /**
     * Determine whether the exception is marked as urgent (bypasses dedup, always notifies).
     */
    public function isUrgent(Throwable $error): bool
    {
        $reflection = new ReflectionClass($error);

        return $error instanceof ShouldUrgentCrashGuard
            || $reflection->getAttributes(UrgentCrashReport::class) !== [];
    }

    /**
     * Determine whether the exception is marked as debug (DB only, no notifications).
     */
    public function isDebug(Throwable $error): bool
    {
        $reflection = new ReflectionClass($error);

        return $reflection->getAttributes(DebugCrashReport::class) !== [];
    }
}
