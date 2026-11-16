<?php

namespace TLabsCo\CrashGuard\Attributes;

/**
 * Force-report this exception class, bypassing ignored_exceptions[] config
 * and ShouldIgnoreCrashReport. Use on exception classes you do not own.
 * Attribute equivalent of ShouldReportToCrashGuard interface.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class ReportToCrashGuard {}
