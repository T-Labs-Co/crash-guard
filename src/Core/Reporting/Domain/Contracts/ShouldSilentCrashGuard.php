<?php

namespace TLabsCo\CrashGuard\Core\Reporting\Domain\Contracts;

/**
 * Marker interface. Exceptions implementing this are persisted to the
 * database but no notification is dispatched to any channel.
 * Useful for non-urgent, high-frequency errors you want to track silently.
 */
interface ShouldSilentCrashGuard {}
