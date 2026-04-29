<?php

namespace anvildev\socialproof\assets;

use Craft;
use craft\web\AssetBundle;

class PopupFrontendAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = '@anvildev/socialproof/resources/popups';

        $suffix = Craft::$app->getConfig()->getGeneral()->devMode ? '' : '.min';

        $this->js = [
            // Order matters: renderer before queue before event-sender
            "js/renderer{$suffix}.js",
            "js/queue{$suffix}.js",
            "js/event-sender{$suffix}.js",
            "js/triggers/time-on-page{$suffix}.js",
            "js/triggers/exit-intent{$suffix}.js",
            "js/triggers/scroll-depth{$suffix}.js",
            "js/triggers/element-click{$suffix}.js",
            "js/triggers/page-match{$suffix}.js",
            "js/triggers/user-state{$suffix}.js",
            "js/layouts/newsletter{$suffix}.js",
            "js/layouts/discount{$suffix}.js",
            // Main entry last so all triggers + layouts are registered
            "js/popups{$suffix}.js",
        ];

        $this->css = [
            "css/social-proof-popups{$suffix}.css",
        ];

        parent::init();
    }
}
