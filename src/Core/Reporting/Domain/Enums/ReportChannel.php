<?php

namespace TLabsCo\CrashGuard\Core\Reporting\Domain\Enums;

enum ReportChannel: string
{
    case Slack = 'slack';
    case Mail = 'mail';
}
