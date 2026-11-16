<?php

namespace TLabsCo\CrashGuard\Facades;

use Carbon\Carbon;
use Illuminate\Support\Facades\Facade;
use TLabsCo\CrashGuard\Core\Reporting\Application\Contracts\CrashGuardContract;
use TLabsCo\CrashGuard\Core\Reporting\Application\DTOs\ReportContextDTO;
use TLabsCo\CrashGuard\Core\Reporting\Application\Services\CrashGuardManager;
use TLabsCo\CrashGuard\Core\Reporting\Domain\Enums\CrashReportMode;
use TLabsCo\CrashGuard\Models\CrashReport;

/**
 * @method static CrashReport|null report(\Throwable|string $error, array<string, mixed> $context = [], ?CrashReportMode $mode = null)
 * @method static CrashReport|null reportAsUrgent(\Throwable|string $error, array<string, mixed> $context = [])
 * @method static CrashReport|null reportAsDebug(\Throwable|string $error, array<string, mixed> $context = [])
 * @method static CrashReport|null reportWithContext(\Throwable|string $error, ReportContextDTO $context, ?CrashReportMode $mode = null)
 * @method static void resolve(int|CrashReport $report)
 * @method static void read(int|CrashReport $report)
 * @method static void ignore(int|CrashReport $report)
 * @method static void pause(int|CrashReport $report, Carbon $until)
 * @method static void enable()
 * @method static void disable()
 * @method static bool isEnabled()
 *
 * @see CrashGuardManager
 */
class CrashGuard extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CrashGuardContract::class;
    }
}
