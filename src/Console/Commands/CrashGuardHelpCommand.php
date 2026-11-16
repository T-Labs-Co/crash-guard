<?php

namespace TLabsCo\CrashGuard\Console\Commands;

use Illuminate\Console\Command;

/**
 * Display help and usage information for CrashGuard commands.
 */
class CrashGuardHelpCommand extends Command
{
    protected $signature = 'crash-guard:help';

    protected $description = 'Show help and usage information for CrashGuard commands.';

    public function handle(): int
    {
        $this->line('');
        $this->line('<info>CrashGuard — Help & Usage</info>');
        $this->line('');
        $this->line('Lightweight exception capture, deduplication & notification for Laravel.');
        $this->line('');

        $this->line('<options=bold>Available Commands</options=bold>');
        $this->line('');

        $commands = [
            [
                'signature' => 'crash-guard:help',
                'description' => 'Show this help message and usage information.',
            ],
            [
                'signature' => 'crash-guard:status',
                'description' => 'Show the current CrashGuard system status and configuration.',
            ],
            [
                'signature' => 'crash-guard:test',
                'description' => 'Smoke-test all CrashGuard report cases.',
            ],
            [
                'signature' => 'crash-guard:prune [--days]',
                'description' => 'Delete old resolved/ignored crash reports.',
            ],
            [
                'signature' => 'crash-guard:reset',
                'description' => 'Reset circuit breaker counters.',
            ],
        ];

        foreach ($commands as $cmd) {
            $this->line("  <fg=#06B6D4>{$cmd['signature']}</>");
            $this->line("    {$cmd['description']}");
            $this->line('');
        }

        $this->line('<options=bold>Global Helper</options=bold>');
        $this->line('');
        $this->line('  crash_report($exception, $context)');
        $this->line('    Report an exception or message to CrashGuard.');
        $this->line('');

        $this->line('<options=bold>Facade</options=bold>');
        $this->line('');
        $this->line('  CrashGuard::report($exception, $context)');
        $this->line('    Report an exception or message via the CrashGuard facade.');
        $this->line('');

        $this->line('<options=bold>Attributes</options=bold>');
        $this->line('');
        $this->line('  #[UrgentCrashReport]     Mark an exception as urgent.');
        $this->line('  #[SilentCrashReport]     Mark an exception as silent (no notification).');
        $this->line('  #[IgnoreCrashReport]     Ignore an exception entirely.');
        $this->line('  #[DebugCrashReport]      Mark an exception for debug mode.');
        $this->line('  #[ReportToCrashGuard]    Explicitly report an exception.');
        $this->line('');

        $this->line('<options=bold>Options</options=bold>');
        $this->line('');
        $this->line('  Use <fg=#06B6D4>php artisan help <command></> for detailed help on a specific command.');
        $this->line('');

        return self::SUCCESS;
    }
}
