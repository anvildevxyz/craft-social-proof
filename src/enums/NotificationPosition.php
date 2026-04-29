<?php

namespace anvildev\socialproof\enums;

enum NotificationPosition: string
{
    case BottomLeft = 'bottom-left';
    case BottomRight = 'bottom-right';
    case TopLeft = 'top-left';
    case TopRight = 'top-right';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
