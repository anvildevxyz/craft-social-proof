<?php
/**
 * Test bootstrap — loads autoloader and minimal Yii framework.
 *
 * We bootstrap just enough of Yii for model validation to work,
 * without initialising the full Craft application.
 */

// Load the plugin's own autoloader first so its PHPUnit classes take priority.
// (Composer uses spl_autoload_register with prepend=true, so the last-loaded
// autoloader wins. We re-register the plugin's autoloader after loading the
// parent's to keep our PHPUnit version in front of the SPL stack.)
$pluginAutoloader = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($pluginAutoloader)) {
    require_once $pluginAutoloader;
}

// Composer autoloader (plugin + all vendor classes)
$autoloader = dirname(__DIR__, 3) . '/vendor/autoload.php';

if (!file_exists($autoloader)) {
    fwrite(STDERR, "Composer autoloader not found at: {$autoloader}\n");
    fwrite(STDERR, "Run 'composer install' in the Craft project root first.\n");
    exit(1);
}

require_once $autoloader;

// Re-register the plugin's autoloader AFTER the parent's so it is prepended
// to the SPL stack and its PHPUnit 10.x classes win over PHPUnit 11.x from
// the parent project.
if (file_exists($pluginAutoloader)) {
    $loader = require $pluginAutoloader;
    // Unregister and re-register to move it to the front of the SPL stack
    $loader->unregister();
    $loader->register(true); // true = prepend
}

// Minimal Yii bootstrap (needed for Model validation, events, etc.)
if (!class_exists('Yii')) {
    require_once dirname(__DIR__, 3) . '/vendor/yiisoft/yii2/Yii.php';
}

// Load the Craft class (extends Yii with Craft-specific methods like Craft::t())
if (!class_exists('Craft')) {
    require_once dirname(__DIR__, 3) . '/vendor/craftcms/cms/src/Craft.php';
}

// Create a minimal console application so Yii::$app exists
// This is required for validators and i18n to work
new yii\console\Application([
    'id' => 'social-proof-tests',
    'basePath' => dirname(__DIR__),
    'components' => [
        'i18n' => [
            'translations' => [
                'social-proof' => [
                    'class' => yii\i18n\PhpMessageSource::class,
                    'basePath' => dirname(__DIR__) . '/src/translations',
                    'forceTranslation' => true,
                ],
            ],
        ],
    ],
]);
