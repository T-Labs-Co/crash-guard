<?php

namespace TLabsCo\CrashGuard\Core\Reporting\Application\Services;

use Throwable;
use TLabsCo\CrashGuard\Core\Reporting\Application\Actions\CreateCrashReport;
use TLabsCo\CrashGuard\Core\Reporting\Application\Contracts\CrashReportRepositoryContract;
use TLabsCo\CrashGuard\Core\Reporting\Domain\Enums\CrashReportMode;
use TLabsCo\CrashGuard\Models\CrashReport;

class ReportWriter
{
    public function __construct(
        private readonly CrashReportRepositoryContract $crashReportRepository,
    ) {}

    /**
     * Find an existing report by fingerprint and increment its occurrence count,
     * or create a new one with all resolved attributes.
     *
     * Use {@see CrashReport::$wasRecentlyCreated} on the returned model to
     * distinguish a new record from an existing one.
     *
     * @param  array<string, mixed>|null  $context
     */
    public function record(
        string $fingerprint,
        Throwable|string $error,
        ?array $context = null,
        bool $silent = false,
        CrashReportMode $mode = CrashReportMode::Normal,
    ): CrashReport {
        $existing = $this->crashReportRepository->findReportByFingerprint($fingerprint);

        if ($existing !== null) {
            $existing->increment('occurrence_count');
            $existing->update(['last_seen_at' => now()]);

            return $existing;
        }

        return CreateCrashReport::make()->execute($fingerprint, $error, $context, $mode, $silent);
    }
}
