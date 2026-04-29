<?php

namespace anvildev\socialproof\enums;

enum PopupLayout: string
{
    case Announcement = 'announcement';
    case Newsletter = 'newsletter';
    case Discount = 'discount';
    case BottomBar = 'bottom-bar';
    case Custom = 'custom';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
