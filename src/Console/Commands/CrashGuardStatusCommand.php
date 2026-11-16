<?php

namespace TLabsCo\CrashGuard\Console\Commands;

use Illuminate\Console\Command;
use TLabsCo\CrashGuard\Core\Reporting\Domain\Enums\ReportStatus;
use TLabsCo\CrashGuard\Models\CrashReport;

/**
 * Display the current CrashGuard system status and configuration.
 */
class CrashGuardStatusCommand extends Command
{
    protected $signature = 'crash-guard:status';

    protected $description = 'Show the current CrashGuard system status and configuration.';

    public function handle(): int
    {
        $enabled = config('crash-guard.enabled', true);
        $mode = config('crash-guard.mode', 'normal');
        $dedupMinutes = config('crash-guard.dedup_minutes', 60);
        $channels = config('crash-guard.channels', []);
        $cbEnabled = config('crash-guard.circuit_breaker.enabled', true);
        $cbThreshold = config('crash-guard.circuit_breaker.threshold', 10);
        $cbCooldown = config('crash-guard.circuit_breaker.cooldown_seconds', 300);
        $pruneDays = config('crash-guard.prune_after_days', 30);
        $schedulingEnabled = config('crash-guard.scheduling.enabled', false);
        $schedulingExpression = config('crash-guard.scheduling.expression', '0 0 * * *');

        $pendingCount = CrashReport::query()->where('status', ReportStatus::Pending->value)->count();
        $totalCount = CrashReport::query()->count();

        $this->line('');
        $this->line('<info>CrashGuard Status</info>');
        $this->line('');
        $this->line('  <fg=#06B6D4>Enabled:</>          '.($enabled ? '<options=bold>YES</>' : '<options=bold>NO</>'));
        $this->line("  <fg=#06B6D4>Mode:</>             {$mode}");
        $this->line("  <fg=#06B6D4>Dedup window:</>     {$dedupMinutes} minutes");
        $this->line('  <fg=#06B6D4>Channels:</>         '.implode(', ', $channels));
        $this->line('  <fg=#06B6D4>Circuit breaker:</>  '.($cbEnabled ? "enabled (threshold: {$cbThreshold}, cooldown: {$cbCooldown}s)" : 'disabled'));
        $this->line("  <fg=#06B6D4>Pending reports:</>  {$pendingCount}");
        $this->line("  <fg=#06B6D4>Total reports:</>    {$totalCount}");
        $this->line("  <fg=#06B6D4>Prune after:</>      {$pruneDays} days");
        $this->line('  <fg=#06B6D4>Scheduling:</>       '.($schedulingEnabled ? "enabled ({$schedulingExpression})" : 'disabled'));
        $this->line('');

        return self::SUCCESS;
    }
}
