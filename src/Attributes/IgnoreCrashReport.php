<?php

namespace TLabsCo\CrashGuard\Attributes;

/**
 * Silently skip this exception class — no DB record, no notification.
 * Use on exception classes you do not own.
 * Attribute equivalent of ShouldIgnoreCrashReport interface.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class IgnoreCrashReport {}
