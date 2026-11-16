<?php

namespace TLabsCo\CrashGuard\Core\Reporting\Application\Contracts;

use Illuminate\Support\Collection;
use TLabsCo\CrashGuard\Models\CrashReport;

/**
 * Contract for the crash report repository.
 */
interface CrashReportRepositoryContract
{
    /**
     * Find a crash report by ID or return the existing instance.
     */
    public function findReport(int|CrashReport $report): CrashReport;

    /**
     * Find a crash report by fingerprint.
     */
    public function findReportByFingerprint(string $fingerprint): ?CrashReport;

    /**
     * Get all distinct fingerprints created today.
     *
     * @return Collection<int, string>
     */
    public function getTodayFingerprints(): Collection;
}
