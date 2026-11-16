<?php

namespace TLabsCo\CrashGuard\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use RuntimeException;
use TLabsCo\CrashGuard\Core\Reporting\Domain\Contracts\ShouldSilentCrashGuard;
use TLabsCo\CrashGuard\Facades\CrashGuard;
use TLabsCo\CrashGuard\Models\CrashReport;

class CrashGuardTestCommand extends Command
{
    protected $signature = 'crash-guard:test
                            {--case= : Run a specific case: exception|string|silent|urgent|debug|resolve|read|ignore|pause|disabled|all (default: all)}';

    protected $description = 'Test all CrashGuard report cases to verify the integration is working.';

    public function handle(): int
    {
        $rawCase = $this->option('case');
        $case = is_string($rawCase) ? $rawCase : 'all';

        $cases = match ($case) {
            'all' => ['exception', 'string', 'silent', 'urgent', 'debug', 'resolve', 'read', 'ignore', 'pause', 'disabled'],
            default => [$case],
        };

        foreach ($cases as $name) {
            $this->runCase($name);
        }

        return self::SUCCESS;
    }

    private function runCase(string $case): void
    {
        match ($case) {
            'exception' => $this->caseException(),
            'string' => $this->caseString(),
            'silent' => $this->caseSilent(),
            'urgent' => $this->caseUrgent(),
            'debug' => $this->caseDebug(),
            'resolve' => $this->caseResolve(),
            'read' => $this->caseRead(),
            'ignore' => $this->caseIgnore(),
            'pause' => $this->casePause(),
            'disabled' => $this->caseDisabled(),
            default => $this->error("Unknown case: {$case}"),
        };
    }

    /** Case 1: Report a real exception. */
    private function caseException(): void
    {
        $this->line('→ [exception] Reporting a RuntimeException...');

        $report = CrashGuard::report(
            new RuntimeException('[crash-guard:test] Exception report case'),
            ['test' => true, 'case' => 'exception'],
        );

        $this->printResult($report);
    }

    /** Case 2: Report a plain string message. */
    private function caseString(): void
    {
        $this->line('→ [string] Reporting a plain string message...');

        $report = CrashGuard::report(
            '[crash-guard:test] String report case',
            ['test' => true, 'case' => 'string'],
        );

        $this->printResult($report);
    }

    /** Case 3: Report a silent exception (stored in DB, no notification). */
    private function caseSilent(): void
    {
        $this->line('→ [silent] Reporting a silent anonymous exception...');

        $silent = new class('[crash-guard:test] Silent report case') extends RuntimeException implements ShouldSilentCrashGuard {};

        $report = CrashGuard::report($silent, ['test' => true, 'case' => 'silent']);

        $this->printResult($report);
    }

    /** Case 4: Report as urgent. */
    private function caseUrgent(): void
    {
        $this->line('→ [urgent] Reporting as urgent...');

        $report = CrashGuard::reportAsUrgent(
            new RuntimeException('[crash-guard:test] Urgent report case'),
            ['test' => true, 'case' => 'urgent'],
        );

        $this->printResult($report);
    }

    /** Case 5: Report as debug. */
    private function caseDebug(): void
    {
        $this->line('→ [debug] Reporting as debug...');

        $report = CrashGuard::reportAsDebug(
            new RuntimeException('[crash-guard:test] Debug report case'),
            ['test' => true, 'case' => 'debug'],
        );

        $this->printResult($report);
    }

    /** Case 6: Report then resolve. */
    private function caseResolve(): void
    {
        $this->line('→ [resolve] Reporting then resolving...');

        $report = CrashGuard::report(
            new RuntimeException('[crash-guard:test] Resolve case'),
            ['test' => true, 'case' => 'resolve'],
        );

        if ($report instanceof CrashReport) {
            CrashGuard::resolve($report);
            $this->info("  ✓ Report #{$report->id} resolved.");
        } else {
            $this->printResult($report);
        }
    }

    /** Case 7: Report then mark as read. */
    private function caseRead(): void
    {
        $this->line('→ [read] Reporting then marking as read...');

        $report = CrashGuard::report(
            new RuntimeException('[crash-guard:test] Read case'),
            ['test' => true, 'case' => 'read'],
        );

        if ($report instanceof CrashReport) {
            CrashGuard::read($report);
            $this->info("  ✓ Report #{$report->id} marked as read.");
        } else {
            $this->printResult($report);
        }
    }

    /** Case 8: Report then ignore. */
    private function caseIgnore(): void
    {
        $this->line('→ [ignore] Reporting then ignoring...');

        $report = CrashGuard::report(
            new RuntimeException('[crash-guard:test] Ignore case'),
            ['test' => true, 'case' => 'ignore'],
        );

        if ($report instanceof CrashReport) {
            CrashGuard::ignore($report);
            $this->info("  ✓ Report #{$report->id} ignored.");
        } else {
            $this->printResult($report);
        }
    }

    /** Case 9: Report then pause notifications. */
    private function casePause(): void
    {
        $until = Carbon::now()->addHour();
        $this->line("→ [pause] Reporting then pausing until {$until->toDateTimeString()}...");

        $report = CrashGuard::report(
            new RuntimeException('[crash-guard:test] Pause case'),
            ['test' => true, 'case' => 'pause'],
        );

        if ($report instanceof CrashReport) {
            CrashGuard::pause($report, $until);
            $this->info("  ✓ Report #{$report->id} paused until {$until->toDateTimeString()}.");
        } else {
            $this->printResult($report);
        }
    }

    /** Case 10: Verify reporting is disabled when config is off. */
    private function caseDisabled(): void
    {
        $this->line('→ [disabled] Checking isEnabled() reflects config...');

        $enabled = CrashGuard::isEnabled();

        if ($enabled) {
            $this->info('  ✓ Reporting is currently ENABLED (crash-guard.enabled=true).');
        } else {
            $this->warn('  ✓ Reporting is currently DISABLED (crash-guard.enabled=false) — report() will return null.');
        }
    }

    private function printResult(?CrashReport $report): void
    {
        if ($report instanceof CrashReport) {
            $this->info("  ✓ Report #{$report->id} created (fingerprint: {$report->fingerprint}).");
        } else {
            $this->warn('  ✗ No report created (reporting disabled or exception skipped).');
        }
    }
}
