<?php

namespace TLabsCo\CrashGuard\Attributes;

/**
 * Mark this exception class as urgent — bypasses the deduplication window
 * and always triggers notifications immediately.
 * Attribute equivalent of setting mode to CrashReportMode::Urgent.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class UrgentCrashReport {}
