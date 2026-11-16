<?php

namespace TLabsCo\CrashGuard\Core\Reporting\Domain\Contracts;

/**
 * Marker interface. Exceptions implementing this bypass deduplication
 * and always trigger notifications — equivalent to #[UrgentCrashReport].
 */
interface ShouldUrgentCrashGuard {}
