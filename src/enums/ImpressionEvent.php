<?php

namespace anvildev\socialproof\enums;

enum ImpressionEvent: string
{
    case Impression = 'impression';
    case Click = 'click';
    case Dismiss = 'dismiss';
    case Heartbeat = 'heartbeat';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
