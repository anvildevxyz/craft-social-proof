<?php

namespace anvildev\socialproof\widgets;

use anvildev\socialproof\Plugin;
use Craft;
use craft\base\Widget;

class StatsWidget extends Widget
{
    public static function displayName(): string
    {
        return Craft::t('social-proof', 'Social Proof Stats');
    }

    public static function icon(): ?string
    {
        return Craft::getAlias('@anvildev/socialproof/icon.svg');
    }

    public function getBodyHtml(): ?string
    {
        $stats = Plugin::$plugin->tracking->getWidgetStats();

        return Craft::$app->getView()->renderTemplate(
            'social-proof/widgets/stats',
            [
                'stats' => $stats,
            ]
        );
    }

    public static function maxColspan(): ?int
    {
        return 2;
    }
}
