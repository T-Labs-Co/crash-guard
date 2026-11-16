<?php

namespace TLabsCo\CrashGuard\Console\Commands;

use Illuminate\Console\Command;
use TLabsCo\CrashGuard\Models\CrashReport;

/**
 * Deletes old resolved and ignored crash reports from the database.
 * Schedule this command via your application's scheduler or cron.
 */
class CrashGuardPruneCommand extends Command
{
    protected $signature = 'crash-guard:prune
                            {--days= : Override the prune_after_days config value}';

    protected $description = 'Prune resolved and ignored crash reports older than the configured threshold.';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('crash-guard.prune_after_days', 30));

        if ($days <= 0) {
            $this->error('The --days value must be a positive integer.');

            return self::FAILURE;
        }

        $deleted = CrashReport::query()->pruneable($days)->delete();

        $this->info("Pruned {$deleted} crash report(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
