<?php

namespace Izzudin96\Billplz\Concerns;

trait NormalizesStrings
{
    private static function toStringOrNull(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        return (string) $value;
    }
}
