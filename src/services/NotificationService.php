<?php

namespace anvildev\socialproof\services;

use anvildev\socialproof\elements\NotificationElement;
use anvildev\socialproof\helpers\Time;
use anvildev\socialproof\models\Notification;
use anvildev\socialproof\models\Settings;
use anvildev\socialproof\Plugin;
use Craft;
use craft\base\Component;
use craft\db\Query;

class NotificationService extends Component
{
    public function __construct(
        private Settings $settings = new Settings(),
        private ?TrackingService $tracking = null,
        array $config = [],
    ) {
        parent::__construct($config);
    }

    /**
     * @param int[] $excludeIds Notification IDs already shown in this session
     * @return Notification[]
     */
    public function getNotificationsForVisitor(string $sessionId, ?string $pageUrl = null, array $excludeIds = [], ?int $siteId = null): array
    {
        $settings = $this->settings;

        if (!$settings->enabled) {
            return [];
        }

        if ($settings->abTestingEnabled && !$this->_isInTestGroup($sessionId, $settings->abTestPercentage)) {
            return [];
        }

        if ($pageUrl && !$this->_matchesUrlPatterns($pageUrl, $settings)) {
            return [];
        }

        $shownCount = $this->tracking?->getSessionImpressionCount($sessionId) ?? 0;
        if ($shownCount >= $settings->maxNotificationsPerSession) {
            return [];
        }

        $remaining = $settings->maxNotificationsPerSession - $shownCount;
        $notifications = [];

        if ($settings->purchaseEnabled) {
            if (Plugin::isCommerceInstalled()) {
                $purchases = $this->getRecentPurchases(min(5, $remaining));
                $notifications = array_merge($notifications, $purchases);
                $remaining -= count($purchases);
            }

            // Fall back to demo data when there are no real purchases (or Commerce isn't installed)
            if (empty($notifications) && $settings->demoMode) {
                $demos = $this->getDemoNotifications(min(5, $remaining));
                $notifications = array_merge($notifications, $demos);
                $remaining -= count($demos);
            }
        }

        if ($remaining > 0 && $settings->viewersEnabled) {
            $viewers = $this->getViewerNotification($pageUrl, $sessionId);
            if ($viewers) {
                $notifications[] = $viewers;
                $remaining--;
            }
        }

        if ($remaining > 0 && $settings->stockEnabled) {
            if (Plugin::isCommerceInstalled()) {
                $stock = $this->getLowStockNotifications(min(3, $remaining), $pageUrl);
                $notifications = array_merge($notifications, $stock);
                $remaining -= count($stock);
            } elseif ($settings->demoMode) {
                $stock = $this->getDemoStockNotifications(min(2, $remaining));
                $notifications = array_merge($notifications, $stock);
                $remaining -= count($stock);
            }
        }

        if ($remaining > 0) {
            $custom = $this->getCustomNotifications($remaining, $siteId);
            $notifications = array_merge($notifications, $custom);
        }

        if ($settings->linkTarget) {
            foreach ($notifications as $notification) {
                if (!$notification->linkTarget) {
                    $notification->linkTarget = $settings->linkTarget;
                }
            }
        }

        if (!empty($excludeIds)) {
            $notifications = array_values(array_filter($notifications, function ($n) use ($excludeIds) {
                // Null IDs (dynamic notifications like viewers) always pass through
                return $n->id === null || !in_array($n->id, $excludeIds, true);
            }));
        }

        shuffle($notifications);

        return $notifications;
    }

    /**
     * @return Notification[]
     */
    public function getDemoNotifications(int $limit = 5): array
    {
        $settings = $this->settings;

        $demoData = [
            [
                'customerName' => 'Sarah',
                'customerLocation' => 'Zürich',
                'productName' => 'Premium Consultation Package',
                'timeAgo' => '2 minutes ago',
            ],
            [
                'customerName' => 'Michael',
                'customerLocation' => 'Basel',
                'productName' => 'UX Audit Service',
                'timeAgo' => '5 minutes ago',
            ],
            [
                'customerName' => 'Anna',
                'customerLocation' => 'Bern',
                'productName' => 'Website Redesign',
                'timeAgo' => '8 minutes ago',
            ],
            [
                'customerName' => 'Thomas',
                'customerLocation' => 'Geneva',
                'productName' => 'Brand Strategy Workshop',
                'timeAgo' => '12 minutes ago',
            ],
            [
                'customerName' => 'Laura',
                'customerLocation' => 'Lausanne',
                'productName' => 'Digital Marketing Plan',
                'timeAgo' => '18 minutes ago',
            ],
            [
                'customerName' => 'Daniel',
                'customerLocation' => 'Lucerne',
                'productName' => 'E-commerce Setup',
                'timeAgo' => '25 minutes ago',
            ],
            [
                'customerName' => 'Nina',
                'customerLocation' => 'Winterthur',
                'productName' => 'Mobile App Design',
                'timeAgo' => '32 minutes ago',
            ],
            [
                'customerName' => 'Marco',
                'customerLocation' => 'St. Gallen',
                'productName' => 'SEO Optimization',
                'timeAgo' => '45 minutes ago',
            ],
        ];

        shuffle($demoData);
        $demoData = array_slice($demoData, 0, $limit);

        $notifications = [];
        $id = 9000; // Pseudo-IDs for demo rows; never persisted, real element IDs may overlap

        foreach ($demoData as $data) {
            $notification = new Notification([
                'id' => $id++,
                'type' => 'purchase',
                'customerName' => $data['customerName'],
                'customerLocation' => $data['customerLocation'],
                'productName' => $data['productName'],
                'productUrl' => '/leistungen',
                'productImage' => null,
                'timeAgo' => $data['timeAgo'],
            ]);

            $notification->message = $this->_renderTemplate($settings->purchaseTemplate, [
                'customer' => $notification->customerName,
                'location' => $notification->customerLocation,
                'product' => $notification->productName,
            ]);

            $notifications[] = $notification;
        }

        return $notifications;
    }

    /**
     * @return Notification[]
     */
    public function getDemoStockNotifications(int $limit = 3): array
    {
        $settings = $this->settings;

        $demoData = [
            ['productName' => 'Strategy Workshop Seats', 'count' => 3],
            ['productName' => 'Early Bird Packages', 'count' => 5],
            ['productName' => 'VIP Consultation Slots', 'count' => 2],
        ];

        $demoData = array_slice($demoData, 0, $limit);
        $notifications = [];
        $id = 9100;

        foreach ($demoData as $data) {
            $notification = new Notification([
                'id' => $id++,
                'type' => 'stock',
                'productName' => $data['productName'],
                'productUrl' => '/leistungen',
                'count' => $data['count'],
            ]);

            $notification->message = $this->_renderTemplate($settings->stockTemplate, [
                'count' => $notification->count,
                'product' => $notification->productName,
            ]);

            $notifications[] = $notification;
        }

        return $notifications;
    }

    /**
     * @return Notification[]
     */
    public function getRecentPurchases(int $limit = 10): array
    {
        $settings = $this->settings;
        $lookbackDate = Time::utcNow()->modify("-{$settings->purchaseLookbackHours} hours");

        $orders = (new Query())
            ->select(['id', 'productName', 'productImage', 'productUrl', 'customerName', 'customerLocation', 'dateCreated'])
            ->from('{{%socialproof_orders}}')
            ->where(['>=', 'dateCreated', $lookbackDate->format('Y-m-d H:i:s')])
            ->orderBy(['dateCreated' => SORT_DESC])
            ->limit($limit)
            ->all();

        $notifications = [];

        foreach ($orders as $order) {
            $notification = new Notification([
                'id' => (int)$order['id'],
                'type' => 'purchase',
                'customerName' => $order['customerName'],
                'customerLocation' => $order['customerLocation'],
                'productName' => $order['productName'],
                'productUrl' => $order['productUrl'],
                'productImage' => $settings->showProductImage ? $order['productImage'] : null,
                'timeAgo' => $this->_formatTimeAgo($order['dateCreated']),
            ]);

            $notification->message = $this->_renderTemplate($settings->purchaseTemplate, [
                'customer' => $notification->customerName,
                'location' => $notification->customerLocation,
                'product' => $notification->productName,
            ]);

            $notifications[] = $notification;
        }

        return $notifications;
    }

    public function getViewerNotification(?string $url = null, ?string $excludeSessionId = null): ?Notification
    {
        $settings = $this->settings;
        $count = $this->getViewerCount($url, $excludeSessionId);

        if ($count < 1 || $count < $settings->viewersMinimum) {
            return null;
        }

        $notification = new Notification([
            'type' => 'viewers',
            'count' => $count,
        ]);

        $notification->message = $this->_renderTemplate($settings->viewersTemplate, [
            'count' => $count,
        ]);

        return $notification;
    }

    public function getViewerCount(?string $url = null, ?string $excludeSessionId = null): int
    {
        $settings = $this->settings;

        switch ($settings->viewersMode) {
            case 'real':
                // Count unique sessions seen via impression or heartbeat in the last 5 minutes
                $fiveMinutesAgo = Time::utcNow()->modify('-5 minutes');
                $query = (new Query())
                    ->select(['sessionId'])
                    ->distinct()
                    ->from('{{%socialproof_impressions}}')
                    ->where(['>=', 'dateCreated', $fiveMinutesAgo->format('Y-m-d H:i:s')])
                    ->andWhere(['eventType' => ['impression', 'heartbeat']]);

                if ($url) {
                    $query->andWhere(['pageUrl' => $url]);
                }

                // Exclude the current visitor so they don't count themselves
                if ($excludeSessionId) {
                    $query->andWhere(['!=', 'sessionId', $excludeSessionId]);
                }

                return (int)$query->count();

            case 'calculated':
                $oneHourAgo = Time::utcNow()->modify('-1 hour');
                $query = (new Query())
                    ->select(['sessionId'])
                    ->distinct()
                    ->from('{{%socialproof_impressions}}')
                    ->where(['>=', 'dateCreated', $oneHourAgo->format('Y-m-d H:i:s')])
                    ->andWhere(['eventType' => ['impression', 'heartbeat']]);

                if ($excludeSessionId) {
                    $query->andWhere(['!=', 'sessionId', $excludeSessionId]);
                }

                $recentActivity = (int)$query->count();
                return $settings->viewersMinimum + ($recentActivity * $settings->viewersMultiplier);

            case 'static':
            default:
                return $settings->viewersMinimum;
        }
    }

    /**
     * @return Notification[]
     */
    public function getLowStockNotifications(int $limit = 5, ?string $pageUrl = null): array
    {
        if (!Plugin::isCommerceInstalled()) {
            return [];
        }

        $variantIds = $this->_resolveVariantIdsForPage($pageUrl);
        if (empty($variantIds)) {
            return [];
        }

        $settings = $this->settings;
        $notifications = [];

        /** @phpstan-ignore-next-line - Commerce is an optional dependency */
        $variants = \craft\commerce\elements\Variant::find()
            ->id($variantIds)
            ->andWhere(['purchasables_stores.inventoryTracked' => true])
            ->andWhere(['<=', 'purchasables_stores.stock', $settings->stockThreshold])
            ->andWhere(['>', 'purchasables_stores.stock', 0])
            ->orderBy(['purchasables_stores.stock' => SORT_ASC])
            ->limit($limit)
            ->all();

        foreach ($variants as $variant) {
            $product = $variant->getOwner();

            if (!$product) {
                continue;
            }

            // In Commerce 5, stock is on the purchasable store record
            $store = \craft\commerce\Plugin::getInstance()->getStores()->getPrimaryStore();
            $storeData = (new \craft\db\Query())
                ->from('{{%commerce_purchasables_stores}}')
                ->where(['purchasableId' => $variant->id, 'storeId' => $store->id])
                ->one();
            $stockCount = (int)($storeData['stock'] ?? 0);

            $notification = new Notification([
                'type' => 'stock',
                'productName' => $product->title ?? 'Product',
                'productUrl' => $product->getUrl(),
                'count' => $stockCount,
            ]);

            $notification->message = $this->_renderTemplate($settings->stockTemplate, [
                'count' => $notification->count,
                'product' => $notification->productName,
            ]);

            $notifications[] = $notification;
        }

        return $notifications;
    }

    /**
     * Resolve a visitor's page URL to the Commerce variant ids whose
     * stock should drive a "low stock" toast. Returns [] for the
     * homepage, an unresolvable URL, or a non-commerce element.
     *
     * @return int[]
     */
    private function _resolveVariantIdsForPage(?string $pageUrl): array
    {
        if ($pageUrl === null || $pageUrl === '' || $pageUrl === '/') {
            return [];
        }
        $path = ltrim((string) (parse_url($pageUrl, PHP_URL_PATH) ?: ''), '/');
        if ($path === '') {
            return [];
        }
        $element = Craft::$app->getElements()->getElementByUri($path);
        if (!$element) {
            return [];
        }
        /** @phpstan-ignore-next-line - Commerce is an optional dependency */
        if ($element instanceof \craft\commerce\elements\Variant) {
            return [(int) $element->id];
        }
        /** @phpstan-ignore-next-line - Commerce is an optional dependency */
        if ($element instanceof \craft\commerce\elements\Product) {
            $ids = [];
            foreach ($element->getVariants() as $variant) {
                $ids[] = (int) $variant->id;
            }
            return $ids;
        }
        return [];
    }

    /**
     * @return Notification[]
     */
    public function getCustomNotifications(int $limit = 5, ?int $siteId = null): array
    {
        $query = NotificationElement::find()
            ->status(NotificationElement::STATUS_ENABLED)
            ->type('custom')
            ->orderBy(['dateCreated' => SORT_DESC])
            ->limit($limit);

        if ($siteId !== null) {
            $query->siteId($siteId);
        }

        $elements = $query->all();

        $notifications = [];

        foreach ($elements as $element) {
            $settings = is_array($element->settings) ? $element->settings : [];

            $productUrl = null;
            $linkType = $settings['linkType'] ?? null;

            if ($linkType === 'entry' && !empty($settings['linkEntry'])) {
                $entryId = is_array($settings['linkEntry']) ? ($settings['linkEntry'][0] ?? null) : $settings['linkEntry'];
                if ($entryId) {
                    $entry = Craft::$app->getEntries()->getEntryById((int)$entryId);
                    $productUrl = $entry?->getUrl();
                }
            } elseif ($linkType === 'asset' && !empty($settings['linkAsset'])) {
                $assetId = is_array($settings['linkAsset']) ? ($settings['linkAsset'][0] ?? null) : $settings['linkAsset'];
                if ($assetId) {
                    $asset = Craft::$app->getAssets()->getAssetById((int)$assetId);
                    $productUrl = $asset?->getUrl();
                }
            } elseif ($linkType === 'url' && !empty($settings['linkUrl'])) {
                $productUrl = $settings['linkUrl'];
            } else {
                // Backwards compat: old plain URL field
                $productUrl = $settings['url'] ?? null;
            }

            $notification = new Notification([
                'id' => $element->id,
                'type' => 'custom',
                'message' => $settings['message'] ?? $element->title ?? '',
                'productUrl' => $productUrl,
                'linkTarget' => $settings['linkTarget'] ?? null,
            ]);

            $notifications[] = $notification;
        }

        return $notifications;
    }

    public function getNotificationById(int $id, ?int $siteId = null): ?NotificationElement
    {
        $query = NotificationElement::find()
            ->id($id)
            ->status(null);

        if ($siteId !== null) {
            $query->siteId($siteId);
        }

        return $query->one();
    }

    private function _isInTestGroup(string $sessionId, int $percentage): bool
    {
        // Hash the session ID so a visitor's bucket assignment is stable across requests
        $hash = crc32($sessionId);
        $bucket = abs($hash % 100);

        return $bucket < $percentage;
    }

    private function _matchesUrlPatterns(string $url, Settings $settings): bool
    {
        // If include patterns are set, URL must match at least one
        if (!empty($settings->includedUrlPatterns)) {
            $matches = false;
            foreach ($settings->includedUrlPatterns as $pattern) {
                if (fnmatch($pattern, $url)) {
                    $matches = true;
                    break;
                }
            }
            if (!$matches) {
                return false;
            }
        }

        foreach ($settings->excludedUrlPatterns as $pattern) {
            if (fnmatch($pattern, $url)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, scalar|null> $variables
     */
    private function _renderTemplate(string $template, array $variables): string
    {
        $message = $template;

        foreach ($variables as $key => $value) {
            $message = str_replace('{' . $key . '}', (string)$value, $message);
        }

        return $message;
    }

    private function _formatTimeAgo(string $dateString): string
    {
        $utc = new \DateTimeZone('UTC');
        $date = new \DateTimeImmutable($dateString, $utc);
        $now = new \DateTimeImmutable('now', $utc);
        $diff = $now->diff($date);

        if ($diff->days > 0) {
            return $diff->days === 1
                ? Craft::t('social-proof', '1 day ago')
                : Craft::t('social-proof', '{n} days ago', ['n' => $diff->days]);
        }

        if ($diff->h > 0) {
            return $diff->h === 1
                ? Craft::t('social-proof', '1 hour ago')
                : Craft::t('social-proof', '{n} hours ago', ['n' => $diff->h]);
        }

        if ($diff->i > 0) {
            return $diff->i === 1
                ? Craft::t('social-proof', '1 minute ago')
                : Craft::t('social-proof', '{n} minutes ago', ['n' => $diff->i]);
        }

        return Craft::t('social-proof', 'just now');
    }

}
