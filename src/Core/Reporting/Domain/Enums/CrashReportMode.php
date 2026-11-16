<?php

namespace TLabsCo\CrashGuard\Core\Reporting\Domain\Enums;

enum CrashReportMode: string
{
    case Normal = 'normal';
    case Debug = 'debug';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::Debug => 'Debug',
            self::Urgent => 'Urgent',
        };
    }
}
