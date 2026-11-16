<?php

declare(strict_types=1);

namespace TLabsCo\CrashGuard\Tests;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use TLabsCo\CrashGuard\Models\CrashReport;
use TLabsCo\CrashGuard\Providers\CrashGuardServiceProvider;

if (class_exists(\Orchestra\Testbench\TestCase::class)) {
    abstract class BaseTestCase extends \Orchestra\Testbench\TestCase {}
} else {
    abstract class BaseTestCase extends \Tests\TestCase {}
}

abstract class TestCase extends BaseTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->app->providerIsLoaded(CrashGuardServiceProvider::class)) {
            $this->app->register(CrashGuardServiceProvider::class);
        }

        if (! Schema::hasTable('crash_guard_reports')) {
            foreach (File::allFiles(__DIR__.'/../src/Database/Migrations') as $migration) {
                (include $migration->getRealPath())->up();
            }
        } else {
            CrashReport::query()->delete();
        }
    }

    protected function getPackageProviders($app): array
    {
        return [
            CrashGuardServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('crash-guard.enabled', true);
        $app['config']->set('crash-guard.mode', 'normal');
        $app['config']->set('crash-guard.channels', []);
        $app['config']->set('crash-guard.dedup_minutes', 60);
        $app['config']->set('crash-guard.circuit_breaker.enabled', true);
        $app['config']->set('crash-guard.circuit_breaker.threshold', 10);
        $app['config']->set('crash-guard.circuit_breaker.cooldown_seconds', 300);
        $app['config']->set('crash-guard.circuit_breaker.daily_limit', 0);
        $app['config']->set('crash-guard.circuit_breaker.global_daily_limit', 0);
        $app['config']->set('crash-guard.ignored_exceptions', []);
        $app['config']->set('crash-guard.warning_exceptions', []);
        $app['config']->set('crash-guard.trace_lines', 10);
        $app['config']->set('crash-guard.prune_after_days', 30);
        $app['config']->set('crash-guard.scheduling.enabled', false);
        $app['config']->set('crash-guard.logging', [
            'enabled' => false,
            'driver' => 'null',
        ]);
    }
}
