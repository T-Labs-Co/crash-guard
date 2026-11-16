<?php

namespace TLabsCo\CrashGuard\Attributes;

/**
 * Persist this exception to the database in debug mode — no notifications dispatched.
 * Attribute equivalent of setting mode to CrashReportMode::Debug.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class DebugCrashReport {}
