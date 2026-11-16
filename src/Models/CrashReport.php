<?php

namespace TLabsCo\CrashGuard\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use TLabsCo\CrashGuard\Core\Reporting\Domain\Enums\CrashReportMode;
use TLabsCo\CrashGuard\Core\Reporting\Domain\Enums\ReportStatus;

/**
 * @property int $id
 * @property string $fingerprint
 * @property string $level
 * @property string $title
 * @property string $message
 * @property string|null $trace
 * @property array<string, mixed>|null $context
 * @property ReportStatus $status
 * @property CrashReportMode|null $mode
 * @property int $occurrence_count
 * @property Carbon|CarbonInterface|null $first_seen_at
 * @property Carbon|CarbonInterface|null $last_seen_at
 * @property Carbon|CarbonInterface|null $notified_at
 * @property Carbon|CarbonInterface|null $read_at
 * @property Carbon|CarbonInterface|null $resolved_at
 * @property Carbon|CarbonInterface|null $ignored_at
 * @property Carbon|CarbonInterface|null $paused_until
 * @property string|null $user_id
 * @property string|null $user_email
 * @property string|null $ip
 * @property string|null $user_agent
 * @property string|null $route
 * @property Carbon|CarbonInterface $created_at
 * @property Carbon|CarbonInterface $updated_at
 *
 * @method static Builder<self>|self pending()
 * @method static Builder<self>|self active()
 * @method static Builder<self>|self snoozed()
 * @method static Builder<self>|self pruneable(int $days)
 */
class CrashReport extends Model
{
    protected $table = 'crash_guard_reports';

    protected $fillable = [
        'fingerprint',
        'level',
        'title',
        'message',
        'trace',
        'context',
        'status',
        'mode',
        'occurrence_count',
        'first_seen_at',
        'last_seen_at',
        'notified_at',
        'read_at',
        'resolved_at',
        'ignored_at',
        'paused_until',
        'user_id',
        'user_email',
        'ip',
        'user_agent',
        'route',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'context' => 'array',
            'status' => ReportStatus::class,
            'mode' => CrashReportMode::class,
            'occurrence_count' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'notified_at' => 'datetime',
            'read_at' => 'datetime',
            'resolved_at' => 'datetime',
            'ignored_at' => 'datetime',
            'paused_until' => 'datetime',
        ];
    }

    /** Reports waiting to be actioned.
     * @param  Builder<self>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', ReportStatus::Pending->value);
    }

    /** Pending reports that are not currently snoozed.
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', ReportStatus::Pending->value)
            ->where(function (Builder $q): void {
                $q->whereNull('paused_until')
                    ->orWhere('paused_until', '<', now());
            });
    }

    /** Reports currently snoozed (paused_until is in the future).
     * @param  Builder<self>  $query
     */
    public function scopeSnoozed(Builder $query): void
    {
        $query->whereNotNull('paused_until')
            ->where('paused_until', '>=', now());
    }

    /** Resolved or ignored reports eligible for pruning.
     * @param  Builder<self>  $query
     */
    public function scopePruneable(Builder $query, int $days): void
    {
        $query->whereIn('status', [ReportStatus::Resolved->value, ReportStatus::Ignored->value])
            ->where('updated_at', '<', now()->subDays($days));
    }
}
