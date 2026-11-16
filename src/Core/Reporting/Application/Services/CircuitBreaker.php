<?php

namespace TLabsCo\CrashGuard\Core\Reporting\Application\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Circuit breaker per error fingerprint.
 *
 * Tracks how many times a given fingerprint has been reported within a rolling
 * time window. Once the hit count exceeds the configured threshold the circuit
 * "opens" for the remainder of the cooldown period:
 *
 *   - Notifications are suppressed (silent mode forced).
 *   - The DB record is still updated on every occurrence.
 *
 * The circuit resets automatically once the cooldown expires.
 */
class CircuitBreaker
{
    private const string CACHE_PREFIX = 'crash-guard.circuit:';

    private const string DAILY_PREFIX = 'crash-guard.daily:';

    private const string GLOBAL_DAILY_KEY = 'crash-guard.global-daily';

    /** Record a hit and return whether the circuit is now open. */
    public function isOpen(string $fingerprint): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        return $this->hitCount($fingerprint) >= $this->threshold();
    }

    /**
     * Return true when the per-day error limit has been exceeded.
     * Notifications are silenced for the rest of the day.
     * Urgent reports bypass this check.
     */
    public function isDailyLimitExceeded(string $fingerprint): bool
    {
        $limit = (int) config('crash-guard.circuit_breaker.daily_limit', 0);

        if ($limit <= 0) {
            return false;
        }

        $key = $this->dailyKey($fingerprint);
        $count = (int) Cache::get($key, 0);

        return $count >= $limit;
    }

    /**
     * Return true when the total occurrences across all fingerprints for today
     * exceed the configured global_daily_limit.
     * Urgent reports bypass this check.
     */
    public function isGlobalDailyLimitExceeded(): bool
    {
        $limit = (int) config('crash-guard.circuit_breaker.global_daily_limit', 0);

        if ($limit <= 0) {
            return false;
        }

        $key = $this->globalDailyKey();
        $count = (int) Cache::get($key, 0);

        return $count >= $limit;
    }

    /** Record an occurrence of a fingerprint. */
    public function record(string $fingerprint): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->increment($fingerprint);
        $this->incrementDaily($fingerprint);
        $this->incrementGlobalDaily();
    }

    /** Reset the counter for a fingerprint (e.g. after resolving a report). */
    public function reset(string $fingerprint): void
    {
        Cache::forget($this->cacheKey($fingerprint));
        Cache::forget($this->dailyKey($fingerprint));
    }

    /** Reset the global daily counter. */
    public function resetGlobalDaily(): void
    {
        Cache::forget($this->globalDailyKey());
    }

    private function increment(string $fingerprint): void
    {
        $key = $this->cacheKey($fingerprint);
        $ttl = $this->cooldownSeconds();

        if (Cache::has($key)) {
            Cache::increment($key);
        } else {
            Cache::put($key, 1, $ttl);
        }
    }

    private function incrementDaily(string $fingerprint): void
    {
        $limit = (int) config('crash-guard.circuit_breaker.daily_limit', 0);
        if ($limit <= 0) {
            return;
        }

        $key = $this->dailyKey($fingerprint);
        $secondsUntilMidnight = (int) now()->secondsUntilEndOfDay();

        if (Cache::has($key)) {
            Cache::increment($key);
        } else {
            Cache::put($key, 1, $secondsUntilMidnight);
        }
    }

    private function incrementGlobalDaily(): void
    {
        $limit = (int) config('crash-guard.circuit_breaker.global_daily_limit', 0);
        if ($limit <= 0) {
            return;
        }

        $key = self::GLOBAL_DAILY_KEY.':'.now()->format('Ymd');
        $secondsUntilMidnight = (int) now()->secondsUntilEndOfDay();

        if (Cache::has($key)) {
            Cache::increment($key);
        } else {
            Cache::put($key, 1, $secondsUntilMidnight);
        }
    }

    private function hitCount(string $fingerprint): int
    {
        return (int) Cache::get($this->cacheKey($fingerprint), 0);
    }

    private function cacheKey(string $fingerprint): string
    {
        return self::CACHE_PREFIX.$fingerprint;
    }

    private function dailyKey(string $fingerprint): string
    {
        return self::DAILY_PREFIX.now()->format('Ymd').':'.$fingerprint;
    }

    private function globalDailyKey(): string
    {
        return self::GLOBAL_DAILY_KEY.':'.now()->format('Ymd');
    }

    private function isEnabled(): bool
    {
        return (bool) config('crash-guard.circuit_breaker.enabled', true);
    }

    private function threshold(): int
    {
        return max(1, (int) config('crash-guard.circuit_breaker.threshold', 10));
    }

    private function cooldownSeconds(): int
    {
        return max(60, (int) config('crash-guard.circuit_breaker.cooldown_seconds', 300));
    }
}
