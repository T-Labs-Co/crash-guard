<?php

namespace TLabsCo\CrashGuard\Core\Reporting\Application\Contracts;

use Carbon\Carbon;
use Throwable;
use TLabsCo\CrashGuard\Core\Reporting\Domain\Enums\CrashReportMode;
use TLabsCo\CrashGuard\Models\CrashReport;

/**
 * Contract for the main CrashGuard service.
 */
interface CrashGuardContract
{
    /** @param array<string, mixed> $context */
    public function report(Throwable|string $error, ?array $context = [], ?CrashReportMode $mode = null): ?CrashReport;

    /** @param array<string, mixed> $context */
    public function reportAsUrgent(Throwable|string $error, array $context = []): ?CrashReport;

    /** @param array<string, mixed> $context */
    public function reportAsDebug(Throwable|string $error, array $context = []): ?CrashReport;

    public function resolve(int|CrashReport $report): void;

    public function read(int|CrashReport $report): void;

    public function ignore(int|CrashReport $report): void;

    public function pause(int|CrashReport $report, Carbon $until): void;

    public function enable(): void;

    public function disable(): void;

    public function isEnabled(): bool;
}
