<?php

namespace anvildev\socialproof\assets;

use Craft;
use craft\web\AssetBundle;

class FrontendAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = '@anvildev/socialproof/resources/frontend';

        $suffix = Craft::$app->getConfig()->getGeneral()->devMode ? '' : '.min';

        $this->css = [
            "css/social-proof{$suffix}.css",
        ];

        $this->js = [
            "js/social-proof{$suffix}.js",
        ];

        parent::init();
    }
}
