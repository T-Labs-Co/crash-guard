<?php

namespace TLabsCo\CrashGuard\Core\Reporting\Domain\ValueObjects;

use Throwable;

/**
 * Immutable value object representing a crash fingerprint hash.
 */
readonly class CrashFingerprint
{
    private function __construct(public string $value) {}

    public static function fromThrowable(Throwable $error): self
    {
        return new self(md5($error::class.$error->getMessage().$error->getFile().$error->getLine()));
    }

    public static function fromString(string $message): self
    {
        return new self(md5($message));
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
