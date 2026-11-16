<?php

namespace TLabsCo\CrashGuard\Core\Reporting\Domain\Contracts;

/**
 * Marker interface. Exceptions implementing this are always reported,
 * bypassing ignored_exceptions[] config and ShouldIgnoreCrashReport.
 * Highest priority in the skip-check chain.
 */
interface ShouldReportToCrashGuard {}
