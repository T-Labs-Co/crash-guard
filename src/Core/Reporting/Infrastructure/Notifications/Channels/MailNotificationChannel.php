<?php

namespace TLabsCo\CrashGuard\Core\Reporting\Infrastructure\Notifications\Channels;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use TLabsCo\CrashGuard\Models\CrashReport;

class MailNotificationChannel extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly CrashReport $report)
    {
        // Default to config mail.to, but allow override via report context
        $this->to(config('crash-guard.mail.to', []));
        $this->from(config('crash-guard.mail.from', config('mail.from.address')));
    }

    public function envelope(): Envelope
    {
        $appName = config('app.name');
        $level = strtoupper($this->report->level);
        $mode = $this->report->mode?->label() ?? 'Normal';

        return new Envelope(
            subject: "[{$appName}] {$level}: {$mode} - {$this->report->title}",
        );
    }

    public function content(): Content
    {
        $report = $this->report;

        return new Content(
            markdown: 'crash-guard::mail.crash-report',
            with: [
                'level' => strtoupper($report->level),
                'title' => $report->title,
                'appName' => config('app.name'),
                'env' => config('app.env'),
                'message' => $report->message,
                'trace' => $report->trace,
                'firstSeen' => $report->first_seen_at?->timezone(config('app.timezone'))->format('H:i d/m') ?? '—',
                'lastSeen' => $report->last_seen_at?->timezone(config('app.timezone'))->format('H:i d/m') ?? '—',
                'occurrences' => $report->occurrence_count,
                'mode' => $report->mode?->label() ?? 'Normal',
                'userId' => $report->user_id,
                'userEmail' => $report->user_email,
                'ip' => $report->ip,
                'route' => $report->route,
                /* @phpstan-ignore-next-line */
                'context' => ! empty($report->context) && is_array($report->context)
                    ? json_encode($report->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                    /* @phpstan-ignore-next-line */
                    : (is_string($report->context) ? $report->context : null),
            ],
        );
    }
}
