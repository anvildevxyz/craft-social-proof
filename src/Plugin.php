<?php

namespace anvildev\socialproof;

use anvildev\socialproof\elements\NotificationElement;
use anvildev\socialproof\models\Settings;
use anvildev\socialproof\services\CommerceService;
use anvildev\socialproof\services\NotificationService;
use anvildev\socialproof\services\TrackingService;
use anvildev\socialproof\variables\SocialProofVariable;
use anvildev\socialproof\widgets\StatsWidget;
use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\Dashboard;
use craft\services\Elements;
use craft\services\Gc;
use craft\services\UserPermissions;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use yii\base\Event;

/**
 * @property-read NotificationService $notifications
 * @property-read TrackingService $tracking
 * @property-read CommerceService $commerce
 * @property-read Settings $settings
 * @property-read \anvildev\socialproof\services\TargetingService $targeting
 * @property-read \anvildev\socialproof\services\FatigueService $fatigue
 * @property-read \anvildev\socialproof\services\ArbitrationService $arbitration
 * @property-read \anvildev\socialproof\services\AttributionService $attribution
 * @property-read \anvildev\socialproof\services\PopupService $popups
 * @property-read \anvildev\socialproof\services\WebhookService $webhooks
 */
class Plugin extends BasePlugin
{
    public const PERMISSION_MANAGE_NOTIFICATIONS = 'socialProof-manageNotifications';
    public const PERMISSION_VIEW_STATISTICS = 'socialProof-viewStatistics';
    public const PERMISSION_MANAGE_SETTINGS = 'socialProof-manageSettings';
    public const PERMISSION_MANAGE_POPUPS = 'socialProof-managePopups';
    public const PERMISSION_VIEW_POPUP_STATISTICS = 'socialProof-viewPopupStatistics';

    public static ?Plugin $plugin = null;

    public bool $hasCpSettings = true;
    public bool $hasCpSection = true;
    public string $schemaVersion = '1.0.0';

    /**
     * Returns the version recorded in the plugin's own composer.json. We can't
     * trust the parent implementation because it reads from Craft's plugin
     * info (sourced from composer.lock), which is stale for path-repo plugins
     * when the plugin's internal version is bumped without re-running
     * `composer update` in the host project.
     */
    public function getVersion(): string
    {
        static $version = null;
        if ($version === null) {
            $composer = json_decode((string) file_get_contents(__DIR__ . '/../composer.json'), true);
            $version = is_array($composer) && isset($composer['version'])
                ? (string) $composer['version']
                : '0.0.0';
        }
        return $version;
    }

    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        Craft::setAlias('@anvildev/socialproof', $this->getBasePath());

        $this->setComponents([
            'tracking' => TrackingService::class,
            'notifications' => fn () => new NotificationService(
                $this->getSettings(),
                $this->tracking,
            ),
            'commerce' => fn () => new CommerceService($this->getSettings()),
            'targeting' => \anvildev\socialproof\services\TargetingService::class,
            'fatigue' => fn () => new \anvildev\socialproof\services\FatigueService(
                new \anvildev\socialproof\services\DbFatigueStore()
            ),
            'arbitration' => fn () => new \anvildev\socialproof\services\ArbitrationService(
                new \anvildev\socialproof\services\TargetingService(),
                new \anvildev\socialproof\services\FatigueService(
                    new \anvildev\socialproof\services\DbFatigueStore()
                ),
            ),
            'attribution' => fn () => new \anvildev\socialproof\services\AttributionService(
                new \anvildev\socialproof\services\DbAttributionStore(),
            ),
            'webhooks' => function () {
                $settings = $this->getSettings();
                return new \anvildev\socialproof\services\WebhookService(
                    new \anvildev\socialproof\services\CraftJobQueue(),
                    $settings->webhooks,
                    $this->getVersion(),
                    new \anvildev\socialproof\services\CacheBreakerStore(),
                    $settings->webhookBreakerThreshold,
                    $settings->webhookBreakerCooldownSeconds,
                );
            },
            'popups' => \anvildev\socialproof\services\PopupService::class,
        ]);

        $this->_registerElements();
        $this->_registerVariable();
        $this->_registerWidgets();
        $this->_registerCpRoutes();
        $this->_registerSiteRoutes();
        $this->_registerPermissions();
        $this->_registerGarbageCollection();
        $this->_registerWebhookEvents();

        if (self::isCommerceInstalled()) {
            $this->_registerCommerceEvents();
        }

        if (Craft::$app instanceof \craft\console\Application) {
            Craft::$app->controllerMap['social-proof/popups'] = \anvildev\socialproof\console\controllers\PopupsController::class;
        }

        Craft::info(
            Craft::t('social-proof', '{name} plugin loaded', ['name' => $this->name]),
            __METHOD__
        );
    }

    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();

        $item['label'] = Craft::t('social-proof', 'Social Proof');

        $userSession = Craft::$app->getUser();
        $subnav = [];

        if ($userSession->checkPermission(self::PERMISSION_MANAGE_NOTIFICATIONS)) {
            $subnav['notifications'] = [
                'label' => Craft::t('social-proof', 'Notifications'),
                'url' => 'social-proof/notifications',
            ];
        }

        if ($userSession->checkPermission(self::PERMISSION_MANAGE_POPUPS)) {
            $subnav['popups'] = [
                'label' => Craft::t('social-proof', 'Popups'),
                'url' => 'social-proof/popups',
            ];
        }

        if ($userSession->checkPermission(self::PERMISSION_VIEW_STATISTICS)) {
            $subnav['stats'] = [
                'label' => Craft::t('social-proof', 'Statistics'),
                'url' => 'social-proof/stats',
            ];
        }

        if ($userSession->checkPermission(self::PERMISSION_MANAGE_SETTINGS)) {
            $subnav['settings'] = [
                'label' => Craft::t('social-proof', 'Settings'),
                'url' => 'social-proof/settings',
            ];
        }

        $item['subnav'] = $subnav;

        return $item;
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    protected function settingsHtml(): ?string
    {
        $commerceInstalled = self::isCommerceInstalled();

        $productTypes = [];
        if ($commerceInstalled) {
            $types = \craft\commerce\Plugin::getInstance()->getProductTypes()->getAllProductTypes();
            foreach ($types as $type) {
                $productTypes[] = [
                    'label' => $type->name,
                    'value' => $type->handle,
                ];
            }
        }

        return Craft::$app->getView()->renderTemplate(
            'social-proof/settings',
            [
                'plugin' => $this,
                'settings' => $this->getSettings(),
                'commerceInstalled' => $commerceInstalled,
                'productTypes' => $productTypes,
                'positionOptions' => [
                    ['label' => Craft::t('social-proof', 'Bottom Left'), 'value' => 'bottom-left'],
                    ['label' => Craft::t('social-proof', 'Bottom Right'), 'value' => 'bottom-right'],
                    ['label' => Craft::t('social-proof', 'Top Left'), 'value' => 'top-left'],
                    ['label' => Craft::t('social-proof', 'Top Right'), 'value' => 'top-right'],
                ],
                'animationOptions' => [
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
                ],
                'viewerModeOptions' => [
                    ['label' => Craft::t('social-proof', 'Real-time (actual visitors)'), 'value' => 'real'],
                    ['label' => Craft::t('social-proof', 'Calculated (based on traffic)'), 'value' => 'calculated'],
                    ['label' => Craft::t('social-proof', 'Static (minimum value)'), 'value' => 'static'],
                ],
            ]
        );
    }

    public static function isCommerceInstalled(): bool
    {
        /** @phpstan-ignore-next-line - Commerce is an optional dependency */
        return class_exists(\craft\commerce\Plugin::class);
    }

    private function _registerCommerceEvents(): void
    {
        if (!self::isCommerceInstalled()) {
            return;
        }

        /** @phpstan-ignore-next-line - Commerce is an optional dependency */
        Event::on(
            \craft\commerce\elements\Order::class,
            \craft\commerce\elements\Order::EVENT_AFTER_COMPLETE_ORDER,
            function (Event $event) {
                /** @var \craft\commerce\elements\Order $order */
                $order = $event->sender;
                try {
                    $this->commerce->handleOrderComplete($order);
                } catch (\Throwable $e) {
                    Craft::error('Social-proof order capture failed: ' . $e->getMessage(), __METHOD__);
                }
            }
        );

        /** @phpstan-ignore-next-line - Commerce is an optional dependency */
        Event::on(
            \craft\commerce\elements\Order::class,
            \craft\commerce\elements\Order::EVENT_AFTER_COMPLETE_ORDER,
            function (Event $event) {
                $settings = $this->getSettings();
                if (!$settings->popupAttributionEnabled) {
                    return;
                }
                /** @var \craft\commerce\elements\Order $order */
                $order = $event->sender;
                $request = Craft::$app->getRequest();
                $visitorId = null;
                $sessionId = null;
                if (!$request->getIsConsoleRequest()) {
                    $visitorId = $request->getCookies()->getValue(\anvildev\socialproof\popups\VisitorCookie::NAME);
                    $sessionId = Craft::$app->getSession()->getId();
                }
                // Empty string is just as useless as null for matching pending
                // rows; treat both as "no identifier" so we don't fall through
                // to a query that scans the whole pending pool.
                if (($visitorId === null || $visitorId === '') && ($sessionId === null || $sessionId === '')) {
                    return;
                }
                try {
                    $this->attribution->attributeOrder(
                        visitorId: $visitorId ?? '',
                        sessionId: $sessionId,
                        orderId: (int) $order->id,
                        orderTotal: (string) $order->getTotalPrice(),
                        currency: $order->currency,
                        completedAt: \anvildev\socialproof\helpers\Time::utcNow(),
                        windowHours: $settings->popupAttributionWindowHours,
                    );
                } catch (\Throwable $e) {
                    Craft::error('Popup attribution failed: ' . $e->getMessage(), __METHOD__);
                }
            }
        );
    }

    private function _registerElements(): void
    {
        Event::on(
            Elements::class,
            Elements::EVENT_REGISTER_ELEMENT_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = NotificationElement::class;
                $event->types[] = \anvildev\socialproof\elements\PopupElement::class;
            }
        );
    }

    private function _registerVariable(): void
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('socialProof', SocialProofVariable::class);
            }
        );
    }

    private function _registerWidgets(): void
    {
        Event::on(
            Dashboard::class,
            Dashboard::EVENT_REGISTER_WIDGET_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = StatsWidget::class;
                $event->types[] = \anvildev\socialproof\widgets\PopupStatsWidget::class;
            }
        );
    }

    private function _registerWebhookEvents(): void
    {
        Event::on(
            \anvildev\socialproof\jobs\SendWebhookJob::class,
            \anvildev\socialproof\jobs\SendWebhookJob::EVENT_DELIVERY_SUCCEEDED,
            function (\anvildev\socialproof\events\WebhookDeliveryEvent $e) {
                $this->webhooks->markDeliverySuccess($e->subscriptionId);
            }
        );
        Event::on(
            \anvildev\socialproof\jobs\SendWebhookJob::class,
            \anvildev\socialproof\jobs\SendWebhookJob::EVENT_DELIVERY_FAILED,
            function (\anvildev\socialproof\events\WebhookDeliveryEvent $e) {
                $this->webhooks->markDeliveryFailure($e->subscriptionId);
            }
        );
    }

    private function _registerGarbageCollection(): void
    {
        Event::on(
            Gc::class,
            Gc::EVENT_RUN,
            function () {
                Craft::$app->getGc()->deletePartialElements(
                    \anvildev\socialproof\elements\NotificationElement::class,
                    '{{%socialproof_notifications}}',
                    'id'
                );

                $this->tracking->cleanupOldData(90);
                $this->commerce->cleanupOldOrders(48);
                $this->attribution->cleanupExpired(90);
                $this->webhooks->cleanupOldDeliveries(30);
            }
        );
    }

    private function _registerPermissions(): void
    {
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function (RegisterUserPermissionsEvent $event) {
                $event->permissions[] = [
                    'heading' => Craft::t('social-proof', 'Social Proof'),
                    'permissions' => [
                        self::PERMISSION_MANAGE_NOTIFICATIONS => [
                            'label' => Craft::t('social-proof', 'Manage notifications'),
                        ],
                        self::PERMISSION_VIEW_STATISTICS => [
                            'label' => Craft::t('social-proof', 'View statistics'),
                        ],
                        self::PERMISSION_MANAGE_SETTINGS => [
                            'label' => Craft::t('social-proof', 'Manage settings'),
                        ],
                        self::PERMISSION_MANAGE_POPUPS => [
                            'label' => Craft::t('social-proof', 'Manage popups'),
                        ],
                        self::PERMISSION_VIEW_POPUP_STATISTICS => [
                            'label' => Craft::t('social-proof', 'View popup statistics'),
                        ],
                    ],
                ];
            }
        );
    }

    protected function afterUninstall(): void
    {
        // Rate-limit cache keys (sp_track_*, sp_heartbeat_*) have a 60s TTL
        // and will expire on their own. No explicit cleanup needed.
    }

    private function _registerSiteRoutes(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            static function (RegisterUrlRulesEvent $event) {
                $event->rules['POST social-proof/popups/api/event'] = 'social-proof/popup-api/event';
                $event->rules['social-proof/popups/api/candidates'] = 'social-proof/popup-api/candidates';
            }
        );
    }

    private function _registerCpRoutes(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['social-proof'] = 'social-proof/notifications/index';
                $event->rules['social-proof/notifications'] = 'social-proof/notifications/index';
                $event->rules['social-proof/notifications/new'] = 'social-proof/notifications/edit';
                $event->rules['social-proof/notifications/<notificationId:\d+>/<site:{handle}>'] = 'social-proof/notifications/edit';
                $event->rules['social-proof/notifications/<notificationId:\d+>'] = 'social-proof/notifications/edit';
                $event->rules['social-proof/popups'] = 'social-proof/popups/index';
                $event->rules['social-proof/popups/new'] = 'social-proof/popups/edit';
                // Preview must come BEFORE the site-handle rule since the handle
                // regex happily matches the literal "preview" string.
                $event->rules['social-proof/popups/<popupId:\d+>/preview'] = 'social-proof/popups/preview';
                $event->rules['social-proof/popups/<popupId:\d+>/<site:{handle}>'] = 'social-proof/popups/edit';
                $event->rules['social-proof/popups/<popupId:\d+>'] = 'social-proof/popups/edit';
                $event->rules['social-proof/stats'] = 'social-proof/settings/stats';
                $event->rules['social-proof/stats/data'] = 'social-proof/settings/stats-data';
                $event->rules['social-proof/settings'] = 'social-proof/settings/index';
            }
        );
    }
}
