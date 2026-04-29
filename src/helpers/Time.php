<?php

namespace anvildev\socialproof\helpers;

final class Time
{
    public static function utcNow(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
