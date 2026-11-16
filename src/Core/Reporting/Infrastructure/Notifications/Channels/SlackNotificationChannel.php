<?php

namespace TLabsCo\CrashGuard\Core\Reporting\Infrastructure\Notifications\Channels;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;
use TLabsCo\CrashGuard\Core\Reporting\Domain\Enums\CrashReportMode;
use TLabsCo\CrashGuard\Models\CrashReport;

class SlackNotificationChannel
{
    public function __construct(private readonly CrashReport $report) {}

    public function send(): void
    {
        $webhookUrl = config('crash-guard.slack.webhook_url');

        if (blank($webhookUrl)) {
            return;
        }

        try {
            $skipEnvironments = config('crash-guard.slack.skip_environments', ['local']);
            if (in_array(app()->environment(), $skipEnvironments)) {
                Log::channel('stack')->info('CrashGuard: Skipping Slack notification in '.app()->environment().' environment', [
                    'webhook_url' => $webhookUrl,
                    'payload' => $this->buildPayload(),
                ]);

                return;
            }
            Http::post($webhookUrl, $this->buildPayload());
        } catch (Throwable $e) {
            Log::channel('crash_guard')->warning('CrashGuard: Failed to send Slack notification', [
                'webhook_url' => $webhookUrl,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function buildPayload(): array
    {
        $report = $this->report;

        $levelEmoji = match ($report->level) {
            'warning' => ':warning:',
            'info' => ':information_source:',
            default => ':red_circle:',
        };

        $modeEmoji = match ($report->mode) {
            CrashReportMode::Urgent => ':rotating_light: *URGENT*',
            CrashReportMode::Debug => ':beetle: *DEBUG*',
            CrashReportMode::Normal => ':eyes: *OOPS*',
            default => null,
        };

        $contextParts = array_filter([
            '*App:* '.config('app.name'),
            '*Env:* '.config('app.env'),
            '*Occurrences:* '.$report->occurrence_count,
            '*First seen:* '.$report->first_seen_at?->timezone(config('app.timezone'))->format('H:i d/m'),
            $report->route ? '*Route:* '.$report->route : null,
            $report->user_email ? '*User:* '.$report->user_email : null,
            $report->ip ? '*IP:* '.$report->ip : null,
        ]);

        $modeName = $report->mode?->label() ?? 'Normal';

        return [
            'blocks' => [
                [
                    'type' => 'header',
                    'text' => [
                        'type' => 'plain_text',
                        'text' => "{$levelEmoji} [{$report->level}] {$report->title}",
                        'emoji' => true,
                    ],
                ],
                ...($modeEmoji !== null ? [[
                    'type' => 'section',
                    'text' => ['type' => 'mrkdwn', 'text' => $modeEmoji],
                ]] : []),
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => '*Message:*'."\n".mb_substr($report->message, 0, 500),
                    ],
                ],
                ...($report->trace ? [[
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => '*Trace:*'."\n".'```'.mb_substr(explode("\n", $report->trace)[0], 0, 1000).'```',
                    ],
                ]] : []),
                [
                    'type' => 'context',
                    'elements' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => implode(' | ', $contextParts),
                        ],
                    ],
                ],
            ],
        ];
    }
}
