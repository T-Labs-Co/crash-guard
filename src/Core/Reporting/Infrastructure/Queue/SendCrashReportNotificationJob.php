<?php

namespace TLabsCo\CrashGuard\Core\Reporting\Infrastructure\Queue;

use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use TLabsCo\CrashGuard\Core\Reporting\Domain\Enums\ReportChannel;
use TLabsCo\CrashGuard\Core\Reporting\Infrastructure\Notifications\Channels\MailNotificationChannel;
use TLabsCo\CrashGuard\Core\Reporting\Infrastructure\Notifications\Channels\SlackNotificationChannel;
use TLabsCo\CrashGuard\Models\CrashReport;

class SendCrashReportNotificationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly CrashReport $report,
        public readonly ReportChannel $channel,
    ) {
        $this->onConnection(config('crash-guard.queue.connection'));
        $this->onQueue(config('crash-guard.queue.name', 'default'));
    }

    public function handle(Mailer $mailer): void
    {
        try {
            match ($this->channel) {
                ReportChannel::Slack => (new SlackNotificationChannel($this->report))->send(),
                ReportChannel::Mail => (new MailNotificationChannel($this->report))->send($mailer),
            };
        } catch (\Throwable $e) {
            if (App::hasDebugModeEnabled()) {
                Log::debug($e->getTraceAsString());
            }
            // Log the error but don't fail the job, as notifications are best-effort
            Log::channel('crash_guard')->warning('CrashGuard: Failed to send notification', [
                'report_id' => $this->report->id,
                'channel' => $this->channel->value,
                'error' => $e->getMessage(),
            ]);
        }

    }
}
