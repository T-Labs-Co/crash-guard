<?php

namespace TLabsCo\CrashGuard\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use TLabsCo\CrashGuard\Console\Commands\CrashGuardHelpCommand;
use TLabsCo\CrashGuard\Console\Commands\CrashGuardPruneCommand;
use TLabsCo\CrashGuard\Console\Commands\CrashGuardResetCommand;
use TLabsCo\CrashGuard\Console\Commands\CrashGuardStatusCommand;
use TLabsCo\CrashGuard\Console\Commands\CrashGuardTestCommand;
use TLabsCo\CrashGuard\Core\Reporting\Application\Contracts\CrashGuardContract;
use TLabsCo\CrashGuard\Core\Reporting\Application\Contracts\CrashReportRepositoryContract;
use TLabsCo\CrashGuard\Core\Reporting\Application\Services\CircuitBreaker;
use TLabsCo\CrashGuard\Core\Reporting\Application\Services\CrashGuardManager;
use TLabsCo\CrashGuard\Core\Reporting\Application\Services\NotificationDispatcher;
use TLabsCo\CrashGuard\Core\Reporting\Application\Services\ReportWriter;
use TLabsCo\CrashGuard\Core\Reporting\Application\Services\RequestContextResolver;
use TLabsCo\CrashGuard\Core\Reporting\Domain\Services\ErrorLevelResolver;
use TLabsCo\CrashGuard\Core\Reporting\Domain\Services\ExceptionPolicy;
use TLabsCo\CrashGuard\Core\Reporting\Domain\Services\FingerprintGenerator;
use TLabsCo\CrashGuard\Core\Reporting\Infrastructure\Notifications\ChannelRegistry;
use TLabsCo\CrashGuard\Core\Reporting\Infrastructure\Repositories\CrashReportRepository;

class CrashGuardServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/crash-guard.php',
            'crash-guard'
        );

        $this->registerLogChannel();

        $this->app->singleton(FingerprintGenerator::class);
        $this->app->singleton(ExceptionPolicy::class);
        $this->app->singleton(ErrorLevelResolver::class);
        $this->app->singleton(NotificationDispatcher::class);
        $this->app->singleton(RequestContextResolver::class);
        $this->app->singleton(ReportWriter::class);
        $this->app->singleton(CircuitBreaker::class);
        $this->app->singleton(CrashReportRepositoryContract::class, CrashReportRepository::class);
        $this->app->singleton(CrashGuardContract::class, CrashGuardManager::class);

        $this->app->singleton(ChannelRegistry::class);
    }

    public function boot(): void
    {
        $this->registerLogChannel();
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'crash-guard');

        $this->commands([
            CrashGuardHelpCommand::class,
            CrashGuardPruneCommand::class,
            CrashGuardResetCommand::class,
            CrashGuardStatusCommand::class,
            CrashGuardTestCommand::class,
        ]);

        $this->registerScheduling();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/crash-guard.php' => config_path('crash-guard.php'),
            ], 'crash-guard-config');

            $this->publishes([
                __DIR__.'/../../resources/views' => resource_path('views/vendor/crash-guard'),
            ], 'crash-guard-views');

            $this->publishes([
                __DIR__.'/../Database/Migrations' => database_path('migrations'),
            ], 'crash-guard-migrations');
        }
    }

    /**
     * Register a custom log channel for notifications.
     */
    protected function registerLogChannel(): void
    {
        /** @var array<string, mixed> $loggingConfig */
        $loggingConfig = (array) config('crash-guard.logging', []);

        if (($loggingConfig['enabled'] ?? true) === false) {
            $this->app->make('config')->set('logging.channels.crash_guard', [
                'driver' => 'null',
            ]);

            return;
        }

        $this->app->make('config')->set('logging.channels.crash_guard', $loggingConfig);
    }

    /**
     * Register built-in scheduling for the prune command.
     */
    protected function registerScheduling(): void
    {
        if (! (bool) config('crash-guard.scheduling.enabled', false)) {
            return;
        }

        if (! $this->app->bound(Schedule::class)) {
            return;
        }

        $schedule = $this->app->make(Schedule::class);
        $expression = config('crash-guard.scheduling.expression', '0 0 * * *');
        $pruneDays = config('crash-guard.scheduling.prune_days', 30);

        $schedule->command("crash-guard:prune --days={$pruneDays}")
            ->cron($expression)
            ->withoutOverlapping();
    }
}
