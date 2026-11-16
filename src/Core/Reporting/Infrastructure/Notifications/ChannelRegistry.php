<?php

namespace TLabsCo\CrashGuard\Core\Reporting\Infrastructure\Notifications;

use TLabsCo\CrashGuard\Core\Reporting\Domain\Enums\ReportChannel;
use TLabsCo\CrashGuard\Core\Reporting\Infrastructure\Notifications\Channels\NotificationChannelContract;

/**
 * Registry that maps ReportChannel enum values to channel implementations.
 */
class ChannelRegistry
{
    /** @var array<string, NotificationChannelContract> */
    private array $channels = [];

    public function register(ReportChannel|string $key, NotificationChannelContract $channel): void
    {
        $this->channels[$key instanceof ReportChannel ? $key->value : $key] = $channel;
    }

    public function resolve(ReportChannel $channel): ?NotificationChannelContract
    {
        return $this->channels[$channel->value] ?? null;
    }
}
