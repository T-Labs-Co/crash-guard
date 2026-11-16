<?php

namespace TLabsCo\CrashGuard\Core\Reporting\Application\Actions;

use Throwable;
use TLabsCo\CrashGuard\Core\Reporting\Domain\Enums\CrashReportMode;
use TLabsCo\CrashGuard\Core\Reporting\Domain\Enums\ReportStatus;
use TLabsCo\CrashGuard\Core\Reporting\Domain\Services\ErrorLevelResolver;
use TLabsCo\CrashGuard\Models\CrashReport;

class CreateCrashReport
{
    public function __construct(
        private readonly ErrorLevelResolver $errorLevelResolver,
    ) {}

    public static function make(): self
    {
        return app()->make(self::class);
    }

    /**
     * Create a new crash report with all resolved attributes.
     *
     * @param  array<string, mixed>|null  $context
     */
    public function execute(
        string $fingerprint,
        Throwable|string $error,
        ?array $context = null,
        CrashReportMode $mode = CrashReportMode::Normal,
        bool $silent = false,
    ): CrashReport {
        return CrashReport::query()->create([
            'fingerprint' => $fingerprint,
            'level' => $this->resolveLevel($error),
            'title' => $this->resolveTitle($error),
            'message' => $error instanceof Throwable ? $error->getMessage() : $error,
            'trace' => $error instanceof Throwable ? $this->resolveTrace($error) : null,
            'context' => $context ?: null,
            'status' => ReportStatus::Pending,
            'mode' => $mode,
            'occurrence_count' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'notified_at' => $silent ? null : now(),
            'user_id' => $context['user_id'] ?? null,
            'user_email' => $context['user_email'] ?? null,
            'ip' => $context['ip'] ?? null,
            'user_agent' => $context['user_agent'] ?? null,
            'route' => $context['route'] ?? null,
        ]);
    }

    private function resolveLevel(Throwable|string $error): string
    {
        return $this->errorLevelResolver->resolve($error);
    }

    private function resolveTitle(Throwable|string $error): string
    {
        if ($error instanceof Throwable) {
            return class_basename($error::class);
        }

        return mb_substr($error, 0, 100);
    }

    private function resolveTrace(Throwable $error): string
    {
        $lines = (int) config('crash-guard.trace_lines', 10);
        $trace = $error->getTraceAsString();

        return implode("\n", array_slice(explode("\n", $trace), 0, $lines));
    }
}
