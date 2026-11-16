<?php

namespace TLabsCo\CrashGuard\Attributes;

/**
 * Persist this exception to the database but suppress all channel notifications.
 * Use on exception classes you do not own.
 * Attribute equivalent of ShouldSilentCrashGuard interface.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class SilentCrashReport {}
