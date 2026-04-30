<?php

namespace anvildev\socialproof\controllers;

use anvildev\socialproof\Plugin;
use anvildev\socialproof\services\WebhookService;
use Craft;
use craft\web\Controller;
use yii\web\Response;

class SettingsController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $permission = match ($action->id) {
            'stats', 'stats-data' => Plugin::PERMISSION_VIEW_STATISTICS,
            default => Plugin::PERMISSION_MANAGE_SETTINGS,
        };

        $this->requirePermission($permission);

        return true;
    }

    public function actionIndex(): Response
    {
        $settings = Plugin::$plugin->getSettings();
        $commerceInstalled = Plugin::isCommerceInstalled();

        $productTypes = [];
        if ($commerceInstalled) {
            $productTypes = $this->_getProductTypes();
        }

        $webhookBreakerStates = [];
        $breaker = Plugin::$plugin->webhooks->getBreaker();
        if ($breaker !== null) {
            $now = time();
            foreach ($settings->webhooks as $w) {
                $id = (string) ($w['id'] ?? '');
                if ($id === '') {
                    continue;
                }
                $state = $breaker->inspect($id);
                $isOpen = $state['openUntil'] !== null && $state['openUntil'] > $now;
                $webhookBreakerStates[$id] = [
                    'failures' => $state['failures'],
                    'openUntil' => $state['openUntil'],
                    'isOpen' => $isOpen,
                    'retryInSeconds' => $isOpen ? max(0, $state['openUntil'] - $now) : 0,
                ];
            }
        }

        $webhookDeliveries = [];
        foreach ($settings->webhooks as $w) {
            $id = (string) ($w['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $webhookDeliveries[$id] = (new \craft\db\Query())
                ->from('{{%socialproof_webhook_deliveries}}')
                ->where(['subscriptionId' => $id])
                ->orderBy(['dispatchedAt' => SORT_DESC])
                ->limit(20)
                ->all();
        }

        return $this->renderTemplate('social-proof/settings', [
            'plugin' => Plugin::$plugin,
            'settings' => $settings,
            'commerceInstalled' => $commerceInstalled,
            'productTypes' => $productTypes,
            'positionOptions' => $this->_getPositionOptions(),
            'animationOptions' => $this->_getAnimationOptions(),
            'viewerModeOptions' => $this->_getViewerModeOptions(),
            'webhookBreakerStates' => $webhookBreakerStates,
            'webhookDeliveries' => $webhookDeliveries,
            'orderStatusOptions' => $this->_getOrderStatusOptions(),
        ]);
    }

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $settings = Plugin::$plugin->getSettings();

        $settings->enabled = (bool)$request->getBodyParam('enabled', true);
        $settings->maxNotificationsPerSession = (int)$request->getBodyParam('maxNotificationsPerSession', 10);
        $settings->demoMode = (bool)$request->getBodyParam('demoMode', false);

        $settings->purchaseEnabled = (bool)$request->getBodyParam('purchaseEnabled', true);
        $settings->purchaseLookbackHours = (int)$request->getBodyParam('purchaseLookbackHours', 24);
        $settings->anonymizeCustomers = (bool)$request->getBodyParam('anonymizeCustomers', true);
        $settings->purchaseTemplate = $request->getBodyParam('purchaseTemplate', $settings->purchaseTemplate);

        $settings->viewersEnabled = (bool)$request->getBodyParam('viewersEnabled', false);
        $settings->viewersMode = $request->getBodyParam('viewersMode', 'real');
        $settings->viewersMinimum = (int)$request->getBodyParam('viewersMinimum', 5);
        $settings->viewersMultiplier = (int)$request->getBodyParam('viewersMultiplier', 1);
        $settings->viewersTemplate = $request->getBodyParam('viewersTemplate', $settings->viewersTemplate);

        $settings->stockEnabled = (bool)$request->getBodyParam('stockEnabled', false);
        $settings->stockThreshold = (int)$request->getBodyParam('stockThreshold', 10);
        $settings->stockTemplate = $request->getBodyParam('stockTemplate', $settings->stockTemplate);

        $settings->position = $request->getBodyParam('position', 'bottom-left');
        $settings->displayDuration = (int)$request->getBodyParam('displayDuration', 5);
        $settings->delayBetween = (int)$request->getBodyParam('delayBetween', 10);
        $settings->animationIn = $request->getBodyParam('animationIn', 'slideIn');
        $settings->animationOut = $request->getBodyParam('animationOut', 'fadeOut');
        $settings->showProductImage = (bool)$request->getBodyParam('showProductImage', true);
        $settings->showDismissButton = (bool)$request->getBodyParam('showDismissButton', true);
        $settings->linkTarget = $request->getBodyParam('linkTarget', '');

        $settings->abTestingEnabled = (bool)$request->getBodyParam('abTestingEnabled', false);
        $settings->abTestPercentage = (int)$request->getBodyParam('abTestPercentage', 50);

        $settings->excludedProductTypes = $request->getBodyParam('excludedProductTypes', []) ?: [];
        $settings->excludedOrderStatusHandles = $request->getBodyParam('excludedOrderStatusHandles', []) ?: [];
        $settings->includedCategories = $request->getBodyParam('includedCategories', []) ?: [];

        $settings->includedUrlPatterns = array_filter(
            array_map('trim', explode("\n", $request->getBodyParam('includedUrlPatterns', '')))
        );
        $settings->excludedUrlPatterns = array_filter(
            array_map('trim', explode("\n", $request->getBodyParam('excludedUrlPatterns', '')))
        );

        if (!$settings->validate()) {
            Craft::$app->getSession()->setError(Craft::t('social-proof', "Couldn't save settings."));

            return $this->renderTemplate('social-proof/settings', [
                'plugin' => Plugin::$plugin,
                'settings' => $settings,
                'commerceInstalled' => Plugin::isCommerceInstalled(),
                'productTypes' => $this->_getProductTypes(),
                'positionOptions' => $this->_getPositionOptions(),
                'animationOptions' => $this->_getAnimationOptions(),
                'viewerModeOptions' => $this->_getViewerModeOptions(),
                'orderStatusOptions' => $this->_getOrderStatusOptions(),
            ]);
        }

        if (!Craft::$app->getPlugins()->savePluginSettings(Plugin::$plugin, $settings->toArray())) {
            Craft::$app->getSession()->setError(Craft::t('social-proof', "Couldn't save settings."));

            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('social-proof', 'Settings saved.'));

        return $this->redirectToPostedUrl();
    }

    public function actionStats(): Response
    {
        $request = Craft::$app->getRequest();
        $period = $request->getParam('period', '7d');

        $stats = Plugin::$plugin->tracking->getStats(['period' => $period]);
        $commerceInstalled = Plugin::isCommerceInstalled();

        $recentOrderCount = 0;
        if ($commerceInstalled) {
            $recentOrderCount = Plugin::$plugin->commerce->getRecentOrderCount(24);
        }

        return $this->renderTemplate('social-proof/stats', [
            'stats' => $stats,
            'period' => $period,
            'commerceInstalled' => $commerceInstalled,
            'recentOrderCount' => $recentOrderCount,
        ]);
    }

    public function actionStatsData(): Response
    {
        $request = Craft::$app->getRequest();
        $period = $request->getParam('period', '7d');

        $stats = Plugin::$plugin->tracking->getStats(['period' => $period]);
        $commerceInstalled = Plugin::isCommerceInstalled();

        $recentOrderCount = 0;
        if ($commerceInstalled) {
            $recentOrderCount = Plugin::$plugin->commerce->getRecentOrderCount(24);
        }

        return $this->asJson([
            'success' => true,
            'stats' => $stats,
            'commerceInstalled' => $commerceInstalled,
            'recentOrderCount' => $recentOrderCount,
        ]);
    }

    public function actionSaveWebhook(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission(Plugin::PERMISSION_MANAGE_SETTINGS);

        $settings = Plugin::$plugin->getSettings();
        $webhooks = $settings->webhooks;

        $request = Craft::$app->getRequest();
        $id = $request->getBodyParam('id') ?: WebhookService::generateDeliveryId();
        $label = (string) $request->getRequiredBodyParam('label');
        $url = (string) $request->getRequiredBodyParam('url');
        $events = array_values(array_filter(
            (array) $request->getBodyParam('events', []),
            static fn ($e) => is_string($e) && $e !== '',
        ));
        $enabled = (bool) $request->getBodyParam('enabled', true);

        $found = false;
        foreach ($webhooks as &$w) {
            if (($w['id'] ?? null) === $id) {
                $w['label'] = $label;
                $w['url'] = $url;
                $w['events'] = $events;
                $w['enabled'] = $enabled;
                $found = true;
                break;
            }
        }
        unset($w);
        if (!$found) {
            $webhooks[] = [
                'id' => $id,
                'label' => $label,
                'url' => $url,
                'events' => $events,
                'secret' => WebhookService::generateSecret(),
                'enabled' => $enabled,
            ];
        }

        $settings->webhooks = $webhooks;
        if (!$settings->validate()) {
            Craft::$app->getSession()->setError(Craft::t('social-proof', 'Webhook invalid: {err}', [
                'err' => implode('; ', $settings->getFirstErrors()),
            ]));
            return $this->redirectToPostedUrl();
        }

        Craft::$app->getPlugins()->savePluginSettings(Plugin::$plugin, $settings->toArray());
        Craft::$app->getSession()->setNotice(Craft::t('social-proof', 'Webhook saved.'));
        return $this->redirectToPostedUrl();
    }

    public function actionDeleteWebhook(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission(Plugin::PERMISSION_MANAGE_SETTINGS);

        $settings = Plugin::$plugin->getSettings();
        $id = (string) Craft::$app->getRequest()->getRequiredBodyParam('id');
        $settings->webhooks = array_values(array_filter(
            $settings->webhooks,
            fn ($w) => ($w['id'] ?? null) !== $id,
        ));
        Craft::$app->getPlugins()->savePluginSettings(Plugin::$plugin, $settings->toArray());
        Craft::$app->getSession()->setNotice(Craft::t('social-proof', 'Webhook deleted.'));
        return $this->redirectToPostedUrl();
    }

    public function actionRotateWebhookSecret(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission(Plugin::PERMISSION_MANAGE_SETTINGS);

        $settings = Plugin::$plugin->getSettings();
        $id = (string) Craft::$app->getRequest()->getRequiredBodyParam('id');
        $webhooks = $settings->webhooks;
        foreach ($webhooks as &$w) {
            if (($w['id'] ?? null) === $id) {
                $w['secret'] = WebhookService::generateSecret();
                break;
            }
        }
        unset($w);
        $settings->webhooks = $webhooks;
        Craft::$app->getPlugins()->savePluginSettings(Plugin::$plugin, $settings->toArray());
        Craft::$app->getSession()->setNotice(Craft::t('social-proof', 'Secret rotated.'));
        return $this->redirectToPostedUrl();
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function _getProductTypes(): array
    {
        if (!Plugin::isCommerceInstalled()) {
            return [];
        }

        /** @phpstan-ignore-next-line - Commerce is an optional dependency */
        $types = \craft\commerce\Plugin::getInstance()->getProductTypes()->getAllProductTypes();
        $options = [];

        foreach ($types as $type) {
            $options[] = [
                'label' => $type->name,
                'value' => $type->handle,
            ];
        }

        return $options;
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function _getOrderStatusOptions(): array
    {
        if (!Plugin::isCommerceInstalled()) {
            return [];
        }

        /** @phpstan-ignore-next-line - Commerce is an optional dependency */
        $statuses = \craft\commerce\Plugin::getInstance()->getOrderStatuses()->getAllOrderStatuses();
        $options = [];

        foreach ($statuses as $status) {
            $options[] = [
                'label' => $status->name,
                'value' => $status->handle,
            ];
        }

        return $options;
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function _getPositionOptions(): array
    {
        return [
            ['label' => Craft::t('social-proof', 'Bottom Left'), 'value' => 'bottom-left'],
            ['label' => Craft::t('social-proof', 'Bottom Right'), 'value' => 'bottom-right'],
            ['label' => Craft::t('social-proof', 'Top Left'), 'value' => 'top-left'],
            ['label' => Craft::t('social-proof', 'Top Right'), 'value' => 'top-right'],
        ];
    }

    /**
     * @return array{in: list<array{label: string, value: string}>, out: list<array{label: string, value: string}>}
     */
    private function _getAnimationOptions(): array
    {
        return [
            'in' => [
                ['label' => Craft::t('social-proof', 'Slide In'), 'value' => 'slideIn'],
                ['label' => Craft::t('social-proof', 'Fade In'), 'value' => 'fadeIn'],
                ['label' => Craft::t('social-proof', 'Bounce In'), 'value' => 'bounceIn'],
            ],
            'out' => [
                ['label' => Craft::t('social-proof', 'Slide Out'), 'value' => 'slideOut'],
                ['label' => Craft::t('social-proof', 'Fade Out'), 'value' => 'fadeOut'],
                ['label' => Craft::t('social-proof', 'Bounce Out'), 'value' => 'bounceOut'],
            ],
        ];
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function _getViewerModeOptions(): array
    {
        return [
            ['label' => Craft::t('social-proof', 'Real-time (actual visitors)'), 'value' => 'real'],
            ['label' => Craft::t('social-proof', 'Calculated (based on traffic)'), 'value' => 'calculated'],
            ['label' => Craft::t('social-proof', 'Static (minimum value)'), 'value' => 'static'],
        ];
    }

}
