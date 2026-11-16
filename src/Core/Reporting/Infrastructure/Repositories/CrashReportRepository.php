<?php

namespace TLabsCo\CrashGuard\Core\Reporting\Infrastructure\Repositories;

use Illuminate\Support\Collection;
use TLabsCo\CrashGuard\Core\Reporting\Application\Contracts\CrashReportRepositoryContract;
use TLabsCo\CrashGuard\Models\CrashReport;

class CrashReportRepository implements CrashReportRepositoryContract
{
    public function findReport(int|CrashReport $report): CrashReport
    {
        return $report instanceof CrashReport
            ? $report
            : CrashReport::query()->findOrFail($report);
    }

    public function findReportByFingerprint(string $fingerprint): ?CrashReport
    {
        return CrashReport::query()->where('fingerprint', $fingerprint)->first();
    }

    public function getTodayFingerprints(): Collection
    {
        return CrashReport::query()
            ->where('created_at', '>=', now()->startOfDay())
            ->select('fingerprint')
            ->distinct()
            ->pluck('fingerprint')
            ->filter()
            ->values();
    }
}
