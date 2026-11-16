<?php

namespace TLabsCo\CrashGuard\Core\Reporting\Domain\Enums;

enum ReportStatus: string
{
    case Pending = 'pending';
    case Resolved = 'resolved';
    case Ignored = 'ignored';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Resolved => 'Resolved',
            self::Ignored => 'Ignored',
        };
    }
}
