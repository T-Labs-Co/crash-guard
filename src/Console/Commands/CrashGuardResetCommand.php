<?php

namespace TLabsCo\CrashGuard\Console\Commands;

use Illuminate\Console\Command;
use TLabsCo\CrashGuard\Core\Reporting\Application\Contracts\CrashReportRepositoryContract;
use TLabsCo\CrashGuard\Core\Reporting\Application\Services\CircuitBreaker;

class CrashGuardResetCommand extends Command
{
    protected $signature = 'crash-guard:reset
                            {fingerprint? : The error fingerprint to reset}
                            {--all : Reset all circuit breaker counters including global daily limit (default if no fingerprint provided)}';

    protected $description = 'Reset the circuit breaker for a specific fingerprint or all counters (default).';

    public function handle(
        CircuitBreaker $circuitBreaker,
        CrashReportRepositoryContract $crashReportRepository,
    ): int {
        $fp = $this->argument('fingerprint');
        $all = (bool) $this->option('all');

        $fingerprint = '';
        if (is_string($fp) && $fp !== '') {
            $fingerprint = $fp;
        } else {
            $all = true;
        }

        if ($all) {
            // ONLY reset fingerprints from the current day to avoid unintended resets of old errors that may still be relevant
            $fingerprints = $crashReportRepository->getTodayFingerprints();

            foreach ($fingerprints as $f) {
                $this->info("Circuit breaker reset for fingerprint: {$f}");
                $circuitBreaker->reset($f);
            }

            $circuitBreaker->resetGlobalDaily();
            $this->info('Global daily limit reset.');
        }

        if ($fingerprint) {
            $circuitBreaker->reset($fingerprint);
            $this->info("Circuit breaker reset for fingerprint: {$fingerprint}");
        }

        return self::SUCCESS;
    }
}
