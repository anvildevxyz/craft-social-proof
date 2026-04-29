<?php

namespace anvildev\socialproof\enums;

enum NotificationType: string
{
    case Purchase = 'purchase';
    case Viewers = 'viewers';
    case Stock = 'stock';
    case Custom = 'custom';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
