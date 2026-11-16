<?php

namespace TLabsCo\CrashGuard\Core\Reporting\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;
use TLabsCo\CrashGuard\Core\Reporting\Application\Contracts\CrashGuardContract;
use TLabsCo\CrashGuard\Core\Reporting\Application\Contracts\CrashReportRepositoryContract;
use TLabsCo\CrashGuard\Core\Reporting\Application\DTOs\ReportContextDTO;
use TLabsCo\CrashGuard\Core\Reporting\Domain\Enums\CrashReportMode;
use TLabsCo\CrashGuard\Core\Reporting\Domain\Enums\ReportStatus;
use TLabsCo\CrashGuard\Core\Reporting\Domain\Services\ExceptionPolicy;
use TLabsCo\CrashGuard\Core\Reporting\Domain\Services\FingerprintGenerator;
use TLabsCo\CrashGuard\Models\CrashReport;

final readonly class CrashGuardManager implements CrashGuardContract
{
    public function __construct(
        private FingerprintGenerator $fingerprintGenerator,
        private ExceptionPolicy $exceptionPolicy,
        private NotificationDispatcher $notificationDispatcher,
        private RequestContextResolver $requestContextResolver,
        private ReportWriter $reportWriter,
        private CrashReportRepositoryContract $crashReportRepository,
        private CircuitBreaker $circuitBreaker,
    ) {}

    /**
     * Capture and report an error.
     * Returns the persisted CrashReport, or null when reporting is disabled or the
     * exception is configured to be skipped entirely.
     *
     * @param  array<string, mixed>|null  $context
     */
    public function report(Throwable|string $error, ?array $context = [], ?CrashReportMode $mode = null): ?CrashReport
    {
        if (! $this->isEnabled()) {
            return null;
        }

        if ($error instanceof Throwable && $this->exceptionPolicy->shouldSkip($error)) {
            return null;
        }

        $resolvedMode = $this->resolveMode($error, $mode);

        $fingerprint = $this->fingerprintGenerator->generate($error);

        $this->circuitBreaker->record($fingerprint);

        $isCircuitBreakerOpen = $this->isCircuitBreakerOpen($fingerprint, $resolvedMode);

        // Log the circuit breaker state for debugging purposes, but only if it's open to avoid log noise
        if ($isCircuitBreakerOpen) {
            Log::channel('crash_guard')->warning('CrashGuard: Circuit breaker tripped for fingerprint '.$fingerprint);
        }

        $silent = $isCircuitBreakerOpen || $this->isSilent($error, $resolvedMode);

        $mergedContext = array_merge($this->requestContextResolver->resolve(), $context ?? []);

        $report = $this->reportWriter->record($fingerprint, $error, $mergedContext, $silent, $resolvedMode);

        $shouldDispatch = $report->wasRecentlyCreated || $this->notificationDispatcher->shouldDispatch($report, $resolvedMode);

        if (! $silent && $shouldDispatch) {
            $this->notificationDispatcher->dispatch($report);

            if (! $report->wasRecentlyCreated) {
                $report->update(['notified_at' => now()]);
            }
        }

        return $report;
    }

    private function resolveMode(Throwable|string $error, ?CrashReportMode $mode = null): CrashReportMode
    {
        if ($mode !== null) {
            return $mode;
        }

        if ($error instanceof Throwable && $this->exceptionPolicy->isUrgent($error)) {
            return CrashReportMode::Urgent;
        }

        if ($error instanceof Throwable && $this->exceptionPolicy->isDebug($error)) {
            return CrashReportMode::Debug;
        }

        if ($mode = CrashReportMode::tryFrom((string) config('crash-guard.mode', 'normal'))) {
            return $mode;
        }

        return CrashReportMode::Normal;
    }

    private function isSilent(Throwable|string $error, CrashReportMode $mode): bool
    {
        $isDebugMode = $mode === CrashReportMode::Debug;
        $isSilentPolicy = $error instanceof Throwable && $this->exceptionPolicy->isSilent($error);

        return $isDebugMode || $isSilentPolicy;
    }

    private function isCircuitBreakerOpen(string $fingerprint, CrashReportMode $mode): bool
    {
        return $mode !== CrashReportMode::Urgent
            && ($this->circuitBreaker->isOpen($fingerprint)
                || $this->circuitBreaker->isDailyLimitExceeded($fingerprint)
                || $this->circuitBreaker->isGlobalDailyLimitExceeded());
    }

    /**
     * Report an error with Urgent mode — bypasses deduplication and always notifies.
     *
     * @param  array<string, mixed>  $context
     */
    public function reportAsUrgent(Throwable|string $error, array $context = []): ?CrashReport
    {
        return $this->report($error, $context, CrashReportMode::Urgent);
    }

    /**
     * Report an error with Debug mode — stored in DB only, no notifications dispatched.
     *
     * @param  array<string, mixed>  $context
     */
    public function reportAsDebug(Throwable|string $error, array $context = []): ?CrashReport
    {
        return $this->report($error, $context, CrashReportMode::Debug);
    }

    /** Mark a report as resolved. */
    public function resolve(int|CrashReport $report): void
    {
        $found = $this->crashReportRepository->findReport($report);
        $found->update([
            'status' => ReportStatus::Resolved,
            'resolved_at' => now(),
        ]);
        $this->circuitBreaker->reset($found->fingerprint);
    }

    /** Mark a report as read (does not change status). */
    public function read(int|CrashReport $report): void
    {
        $this->crashReportRepository->findReport($report)->update(['read_at' => now()]);
    }

    /** Mark a report as ignored — suppresses future notifications. */
    public function ignore(int|CrashReport $report): void
    {
        $this->crashReportRepository->findReport($report)->update([
            'status' => ReportStatus::Ignored,
            'ignored_at' => now(),
        ]);
    }

    /** Snooze notifications for a report until the given datetime. */
    public function pause(int|CrashReport $report, Carbon $until): void
    {
        $this->crashReportRepository->findReport($report)->update(['paused_until' => $until]);
    }

    /** Whether reporting is currently enabled. */
    public function isEnabled(): bool
    {
        return (bool) config('crash-guard.enabled', true);
    }

    /** Enable crash reporting at runtime. */
    public function enable(): void
    {
        config(['crash-guard.enabled' => true]);
    }

    /** Disable crash reporting at runtime. */
    public function disable(): void
    {
        config(['crash-guard.enabled' => false]);
    }

    /**
     * Report an error with a typed context DTO.
     */
    public function reportWithContext(Throwable|string $error, ReportContextDTO $context, ?CrashReportMode $mode = null): ?CrashReport
    {
        return $this->report($error, $context->toArray(), $mode);
    }
}
