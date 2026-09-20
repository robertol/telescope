<?php

namespace Laravel\Telescope\Observability;

class ExceptionFingerprint
{
    public function hash(string $class, string $file, int $line, ?string $message = null): string
    {
        return md5($class.'|'.$file.'|'.$line);
    }
}
