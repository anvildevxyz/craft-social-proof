<?php

namespace anvildev\socialproof\enums;

enum PopupEvent: string
{
    case Impression = 'impression';
    case Click = 'click';
    case Dismiss = 'dismiss';
    case Convert = 'convert';

    public function webhookName(): string
    {
        return 'popup.' . $this->value;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return list<string> */
    public static function webhookNames(): array
    {
        return array_map(static fn (self $e): string => $e->webhookName(), self::cases());
    }
}
