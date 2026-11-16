<?php

namespace TLabsCo\CrashGuard\Core\Reporting\Application\Services;

use Illuminate\Support\Facades\Log;
use Throwable;
use TLabsCo\CrashGuard\Core\Reporting\Domain\Enums\CrashReportMode;
use TLabsCo\CrashGuard\Core\Reporting\Domain\Enums\ReportChannel;
use TLabsCo\CrashGuard\Core\Reporting\Domain\Enums\ReportStatus;
use TLabsCo\CrashGuard\Core\Reporting\Infrastructure\Queue\SendCrashReportNotificationJob;
use TLabsCo\CrashGuard\Models\CrashReport;

class NotificationDispatcher
{
    public function shouldDispatch(CrashReport $report, CrashReportMode $mode = CrashReportMode::Normal): bool
    {
        if ($report->status === ReportStatus::Ignored) {
            return false;
        }

        if ($report->paused_until !== null && $report->paused_until->isFuture()) {
            return false;
        }

        if ($mode === CrashReportMode::Urgent) {
            return true;
        }

        return ! $this->isDuplicate($report);
    }

    public function dispatch(CrashReport $report): void
    {
        $channels = config('crash-guard.channels', []);

        foreach ($channels as $channel) {
            $channelEnum = ReportChannel::tryFrom(trim((string) $channel));

            if ($channelEnum === null) {
                Log::channel('crash_guard')->warning('CrashGuard: unknown channel', ['channel' => $channel]);

                continue;
            }

            try {
                dispatch(new SendCrashReportNotificationJob($report, $channelEnum));
            } catch (Throwable $e) {
                Log::channel('crash_guard')->warning('CrashGuard: dispatch failed', [
                    'channel' => $channel,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function isDuplicate(CrashReport $report): bool
    {
        if ($report->notified_at === null) {
            return false;
        }

        $dedupMinutes = (int) config('crash-guard.dedup_minutes', 60);

        return $report->notified_at->diffInMinutes(now()) < $dedupMinutes;
    }
}
