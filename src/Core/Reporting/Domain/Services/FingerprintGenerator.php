<?php

namespace TLabsCo\CrashGuard\Core\Reporting\Domain\Services;

use Throwable;

class FingerprintGenerator
{
    public function generate(Throwable|string $error): string
    {
        if ($error instanceof Throwable) {
            return md5($error::class.$error->getMessage().$error->getFile().$error->getLine());
        }

        return md5($error);
    }
}
