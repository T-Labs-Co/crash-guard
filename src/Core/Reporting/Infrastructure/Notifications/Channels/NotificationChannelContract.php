<?php

namespace TLabsCo\CrashGuard\Core\Reporting\Infrastructure\Notifications\Channels;

use TLabsCo\CrashGuard\Models\CrashReport;

/**
 * Contract that all notification channels must implement.
 */
interface NotificationChannelContract
{
    public function send(CrashReport $report): void;
}
