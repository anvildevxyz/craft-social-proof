<?php

namespace anvildev\socialproof\assets;

use Craft;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset as CraftCpAsset;

class CpAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = '@anvildev/socialproof/resources/cp';

        $this->depends = [
            CraftCpAsset::class,
        ];

        $suffix = Craft::$app->getConfig()->getGeneral()->devMode ? '' : '.min';

        $this->css = [
            "css/social-proof-cp{$suffix}.css",
        ];

        parent::init();
    }
}
