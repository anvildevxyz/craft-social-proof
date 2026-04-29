<?php

namespace anvildev\socialproof\controllers;

use anvildev\socialproof\elements\NotificationElement;
use anvildev\socialproof\Plugin;
use Craft;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class NotificationsController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission(Plugin::PERMISSION_MANAGE_NOTIFICATIONS);

        return true;
    }

    public function actionIndex(): Response
    {
        return $this->renderTemplate('social-proof/notifications/index', [
            'elementType' => NotificationElement::class,
        ]);
    }

    public function actionEdit(?int $notificationId = null, ?string $site = null, ?NotificationElement $notification = null): Response
    {
        $siteHandle = $site ?? Craft::$app->getRequest()->getQueryParam('site');
        if ($siteHandle !== null) {
            $siteModel = Craft::$app->getSites()->getSiteByHandle($siteHandle);
            if (!$siteModel) {
                throw new NotFoundHttpException('Invalid site handle: ' . $siteHandle);
            }
            $siteId = $siteModel->id;
        } else {
            $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        }

        if ($notification === null) {
            if ($notificationId !== null) {
                $notification = Plugin::$plugin->notifications->getNotificationById($notificationId, $siteId);

                if (!$notification) {
                    throw new NotFoundHttpException('Notification not found');
                }
            } else {
                $notification = new NotificationElement();
                $notification->siteId = $siteId;
            }
        }

        $isNew = !$notification->id;

        $settings = is_array($notification->settings) ? $notification->settings : [];
        $linkedEntry = null;
        $linkedAsset = null;

        if (!empty($settings['linkEntry'])) {
            $entryId = is_array($settings['linkEntry']) ? ($settings['linkEntry'][0] ?? null) : $settings['linkEntry'];
            if ($entryId) {
                $linkedEntry = Entry::find()->id((int)$entryId)->status(null)->one();
            }
        }

        if (!empty($settings['linkAsset'])) {
            $assetId = is_array($settings['linkAsset']) ? ($settings['linkAsset'][0] ?? null) : $settings['linkAsset'];
            if ($assetId) {
                $linkedAsset = Asset::find()->id((int)$assetId)->status(null)->one();
            }
        }

        $currentSite = Craft::$app->getSites()->getSiteById($notification->siteId);
        $isMultiSite = Craft::$app->getIsMultiSite();

        $availableSites = [];
        if ($isMultiSite) {
            $supportedSites = $isNew
                ? Craft::$app->getSites()->getAllSites()
                : $this->_getSitesForPropagation($notification);

            foreach ($supportedSites as $s) {
                $availableSites[] = [
                    'label' => Craft::t('site', $s->name),
                    'url' => $isNew
                        ? UrlHelper::cpUrl('social-proof/notifications/new', ['site' => $s->handle])
                        : UrlHelper::cpUrl("social-proof/notifications/{$notification->id}/{$s->handle}"),
                    'selected' => $s->id === $notification->siteId,
                ];
            }
        }

        $crumbs = [
            ['label' => Craft::t('social-proof', 'Social Proof'), 'url' => 'social-proof'],
            ['label' => Craft::t('social-proof', 'Notifications'), 'url' => 'social-proof/notifications'],
        ];

        return $this->renderTemplate('social-proof/notifications/_edit', [
            'notification' => $notification,
            'isNew' => $isNew,
            'title' => $isNew ? Craft::t('social-proof', 'New Notification') : $notification->title,
            'typeOptions' => $this->_getTypeOptions(),
            'positionOptions' => $this->_getPositionOptions(),
            'propagationOptions' => $this->_getPropagationOptions($currentSite),
            'linkedEntry' => $linkedEntry,
            'linkedAsset' => $linkedAsset,
            'currentSiteId' => $notification->siteId,
            'currentSiteHandle' => $currentSite?->handle,
            'currentSiteName' => Craft::t('site', $currentSite?->name ?? ''),
            'availableSites' => $availableSites,
            'crumbs' => $crumbs,
            'isMultiSite' => $isMultiSite,
        ]);
    }

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $notificationId = $request->getBodyParam('notificationId');

        $siteId = $request->getBodyParam('siteId') ?: Craft::$app->getSites()->getCurrentSite()->id;

        if ($notificationId) {
            $notification = Plugin::$plugin->notifications->getNotificationById((int)$notificationId, (int)$siteId);

            if (!$notification) {
                throw new NotFoundHttpException('Notification not found');
            }
        } else {
            $notification = new NotificationElement();
            $notification->siteId = (int)$siteId;
        }

        $notification->title = $request->getBodyParam('title');
        $notification->type = $request->getBodyParam('type', 'purchase');
        $notification->enabled = (bool)$request->getBodyParam('enabled', true);
        $notification->position = $request->getBodyParam('position', 'bottom-left');
        $notification->displayDuration = (int)$request->getBodyParam('displayDuration', 5);
        $notification->delayBetween = (int)$request->getBodyParam('delayBetween', 10);
        $notification->propagationMethod = $request->getBodyParam('propagationMethod', NotificationElement::PROPAGATION_METHOD_ALL);

        $notification->settings = $request->getBodyParam('settings', []) ?: [];

        if (!Craft::$app->getElements()->saveElement($notification)) {
            Craft::$app->getSession()->setError(Craft::t('social-proof', "Couldn't save notification."));

            Craft::$app->getUrlManager()->setRouteParams([
                'notification' => $notification,
            ]);

            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('social-proof', 'Notification saved.'));

        $site = Craft::$app->getSites()->getSiteById($notification->siteId);
        if ($site && Craft::$app->getIsMultiSite()) {
            return $this->redirect(UrlHelper::cpUrl("social-proof/notifications/{$notification->id}/{$site->handle}"));
        }

        return $this->redirectToPostedUrl($notification);
    }

    /**
     * @return list<array{label: string, value: string, disabled?: bool}>
     */
    private function _getTypeOptions(): array
    {
        $options = [
            ['label' => Craft::t('social-proof', 'Purchase'), 'value' => 'purchase'],
            ['label' => Craft::t('social-proof', 'Viewers'), 'value' => 'viewers'],
            ['label' => Craft::t('social-proof', 'Low Stock'), 'value' => 'stock'],
            ['label' => Craft::t('social-proof', 'Custom'), 'value' => 'custom'],
        ];

        // Disable Commerce-dependent types if not installed
        /** @phpstan-ignore-next-line - Commerce is an optional dependency */
        if (!class_exists(\craft\commerce\Plugin::class)) {
            foreach ($options as &$option) {
                if (in_array($option['value'], ['purchase', 'stock'], true)) {
                    $option['disabled'] = true;
                    $option['label'] .= ' ' . Craft::t('social-proof', '(requires Commerce)');
                }
            }
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
     * @return list<array{label: string, value: string}>
     */
    private function _getPropagationOptions(?\craft\models\Site $currentSite): array
    {
        $options = [
            [
                'label' => Craft::t('social-proof', 'Only save to the {site} site', [
                    'site' => $currentSite?->name ?? 'current',
                ]),
                'value' => NotificationElement::PROPAGATION_METHOD_NONE,
            ],
            [
                'label' => Craft::t('social-proof', 'Save to all sites'),
                'value' => NotificationElement::PROPAGATION_METHOD_ALL,
            ],
            [
                'label' => Craft::t('social-proof', 'Save to sites in the same site group'),
                'value' => NotificationElement::PROPAGATION_METHOD_SITE_GROUP,
            ],
            [
                'label' => Craft::t('social-proof', 'Save to sites with the same language'),
                'value' => NotificationElement::PROPAGATION_METHOD_LANGUAGE,
            ],
        ];

        return $options;
    }

    /**
     * @return list<\craft\models\Site>
     */
    private function _getSitesForPropagation(NotificationElement $notification): array
    {
        $supportedSiteConfigs = $notification->getSupportedSites();
        $sites = [];

        foreach ($supportedSiteConfigs as $config) {
            $siteId = is_array($config) ? $config['siteId'] : $config;
            $site = Craft::$app->getSites()->getSiteById($siteId);
            if ($site) {
                $sites[] = $site;
            }
        }

        return $sites;
    }
}
