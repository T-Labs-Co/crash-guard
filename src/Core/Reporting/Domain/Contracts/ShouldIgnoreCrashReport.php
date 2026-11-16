<?php

namespace TLabsCo\CrashGuard\Core\Reporting\Domain\Contracts;

/**
 * Marker interface. Exceptions implementing this are silently skipped —
 * no DB record is created and no notification is dispatched.
 */
interface ShouldIgnoreCrashReport {}
