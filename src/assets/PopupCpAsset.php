<?php

namespace anvildev\socialproof\assets;

use Craft;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class PopupCpAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = '@anvildev/socialproof/resources/cp';
        $this->depends = [CpAsset::class];

        $suffix = Craft::$app->getConfig()->getGeneral()->devMode ? '' : '.min';
        $this->js = ["js/popup-edit{$suffix}.js"];

        parent::init();
    }
}
