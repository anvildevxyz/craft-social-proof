<?php

namespace anvildev\socialproof\services;

use anvildev\socialproof\helpers\Time;
use anvildev\socialproof\models\Settings;
use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\base\NestedElementInterface;
use craft\db\Query;
use craft\elements\db\AssetQuery;
use craft\helpers\DateTimeHelper;

class CommerceService extends Component
{
    public function __construct(
        private Settings $settings = new Settings(),
        array $config = [],
    ) {
        parent::__construct($config);
    }

    /**
     * @phpstan-ignore-next-line - Commerce is an optional dependency
     */
    public function handleOrderComplete(\craft\commerce\elements\Order $order): void
    {
        $settings = $this->settings;

        if (!$settings->purchaseEnabled) {
            return;
        }

        foreach ($order->getLineItems() as $lineItem) {
            $purchasable = $lineItem->getPurchasable();

            if (!$purchasable) {
                continue;
            }

            if ($this->_shouldExcludeProduct($purchasable, $settings)) {
                continue;
            }

            $customerInfo = $this->_getCustomerInfo($order, $settings->anonymizeCustomers);

            $this->_storeOrderData([
                'orderId' => $order->id,
                'productId' => $purchasable->id,
                'productName' => $this->_getProductName($lineItem, $purchasable),
                'productImage' => $this->_getProductImage($purchasable),
                'productUrl' => $this->_getProductUrl($purchasable),
                'customerName' => $customerInfo['name'],
                'customerLocation' => $customerInfo['location'],
                'orderTotal' => $order->totalPrice,
            ]);
        }
    }

    /** @phpstan-ignore-next-line - Commerce optional dep */
    private function _shouldExcludeProduct(\craft\commerce\base\Purchasable $purchasable, Settings $settings): bool
    {
        if (!empty($settings->excludedProductTypes)) {
            $product = $this->_getProduct($purchasable);

            if ($product !== null && method_exists($product, 'getType')) {
                $productType = $product->getType();

                if (in_array($productType->handle, $settings->excludedProductTypes, true)) {
                    return true;
                }
            }
        }

        if (!empty($settings->includedCategories)) {
            $product = $this->_getProduct($purchasable);

            if ($product !== null) {
                $categoryIds = \craft\elements\Category::find()
                    ->relatedTo($product)
                    ->ids();

                if (empty(array_intersect($categoryIds, $settings->includedCategories))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Resolve the product element a purchasable belongs to. For Variants this is the
     * parent Product (via NestedElementInterface::getOwner()). Custom Purchasables
     * that act as their own product (have getType()) are returned as-is.
     *
     * @phpstan-ignore-next-line - Commerce optional dep
     */
    private function _getProduct(\craft\commerce\base\Purchasable $purchasable): ?ElementInterface
    {
        if ($purchasable instanceof NestedElementInterface) {
            return $purchasable->getOwner();
        }

        if (method_exists($purchasable, 'getType')) {
            return $purchasable;
        }

        return null;
    }

    /** @phpstan-ignore-next-line - Commerce optional dep */
    private function _getProductName(\craft\commerce\models\LineItem $lineItem, \craft\commerce\base\Purchasable $purchasable): string
    {
        if ($lineItem->description) {
            return $lineItem->description;
        }

        $product = $this->_getProduct($purchasable);

        if ($product !== null && $product->title) {
            return $product->title;
        }

        return $purchasable->title ?? 'Product';
    }

    /** @phpstan-ignore-next-line - Commerce optional dep */
    private function _getProductImage(\craft\commerce\base\Purchasable $purchasable): ?string
    {
        $product = $this->_getProduct($purchasable);

        if ($product === null) {
            return null;
        }

        $fieldLayout = $product->getFieldLayout();
        if ($fieldLayout === null) {
            return null;
        }

        foreach (['productImage', 'image', 'images', 'featuredImage', 'photo'] as $fieldHandle) {
            if ($fieldLayout->getFieldByHandle($fieldHandle) === null) {
                continue;
            }

            $query = $product->{$fieldHandle};
            if ($query instanceof AssetQuery) {
                $asset = $query->one();
                if ($asset) {
                    return $asset->getUrl(['width' => 80, 'height' => 80]);
                }
            }
        }

        return null;
    }

    /** @phpstan-ignore-next-line - Commerce optional dep */
    private function _getProductUrl(\craft\commerce\base\Purchasable $purchasable): ?string
    {
        return $this->_getProduct($purchasable)?->getUrl();
    }

    /**
     * @phpstan-ignore-next-line - Commerce optional dep
     * @return array{name: string, location: string}
     */
    private function _getCustomerInfo(\craft\commerce\elements\Order $order, bool $anonymize): array
    {
        $name = 'Someone';
        $location = null;

        $address = $order->getBillingAddress() ?: $order->getShippingAddress();

        if ($address) {
            if ($anonymize) {
                $name = $address->firstName ?: 'Someone';
            } else {
                $name = trim(($address->firstName ?? '') . ' ' . ($address->lastName ?? '')) ?: 'Someone';
            }

            $locationParts = array_filter([
                $address->locality,
                $address->administrativeArea,
            ]);

            if (!empty($locationParts)) {
                $location = implode(', ', $locationParts);
            } elseif ($address->countryCode) {
                $location = $this->_getCountryName($address->countryCode);
            }
        }

        return [
            'name' => $name,
            'location' => $location ?? 'nearby',
        ];
    }

    private function _getCountryName(string $countryCode): string
    {
        $countries = [
            'US' => 'United States',
            'GB' => 'United Kingdom',
            'CA' => 'Canada',
            'AU' => 'Australia',
            'DE' => 'Germany',
            'FR' => 'France',
            'NL' => 'Netherlands',
            'BE' => 'Belgium',
            'CH' => 'Switzerland',
            'AT' => 'Austria',
        ];

        return $countries[$countryCode] ?? $countryCode;
    }

    private function _storeOrderData(array $data): void
    {
        Craft::$app->getDb()->createCommand()
            ->insert('{{%socialproof_orders}}', array_merge($data, [
                'dateCreated' => DateTimeHelper::currentUTCDateTime()->format('Y-m-d H:i:s'),
            ]))
            ->execute();
    }

    public function cleanupOldOrders(int $hoursToKeep = 48): int
    {
        $cutoffDate = Time::utcNow()->modify("-{$hoursToKeep} hours");

        $result = Craft::$app->getDb()->createCommand()
            ->delete('{{%socialproof_orders}}', [
                '<', 'dateCreated', $cutoffDate->format('Y-m-d H:i:s'),
            ])
            ->execute();

        Craft::info("Cleaned up {$result} old order cache records", __METHOD__);

        return $result;
    }

    /**
     * Returns true when a saved transaction represents a successful refund and
     * its order's cached notifications should be purged.
     *
     * Compared as string literals (not Commerce constants) so the service is
     * testable when Commerce is not loaded. Values match
     * \craft\commerce\records\Transaction::TYPE_REFUND ('refund') and
     * STATUS_SUCCESS ('success').
     */
    public function shouldHandleRefundTransaction(string $type, string $status): bool
    {
        return $type === 'refund' && $status === 'success';
    }

    /**
     * Returns true when an order has just transitioned into a status whose
     * cached notifications should be purged. Null status (e.g. an order with
     * no orderStatus assigned) is never handled.
     *
     * @param array<int,string> $excludedStatusHandles
     */
    public function shouldHandleStatusChange(?string $statusHandle, array $excludedStatusHandles): bool
    {
        if ($statusHandle === null) {
            return false;
        }

        return in_array($statusHandle, $excludedStatusHandles, true);
    }

    /**
     * Remove every cached purchase notification belonging to an order. Called
     * when the order is refunded or transitions to a status that should
     * suppress its notifications. Idempotent — calling on an order with no
     * cached rows is a no-op.
     *
     * @return int Number of rows deleted.
     */
    public function removeOrderFromCache(int $orderId): int
    {
        $deleted = (int) Craft::$app->getDb()->createCommand()
            ->delete('{{%socialproof_orders}}', ['orderId' => $orderId])
            ->execute();

        if ($deleted > 0) {
            Craft::info(
                "Removed {$deleted} cached notification row(s) for order {$orderId}",
                __METHOD__,
            );
        }

        return $deleted;
    }

    public function getRecentOrderCount(int $hours = 24): int
    {
        $cutoffDate = Time::utcNow()->modify("-{$hours} hours");

        return (int)(new Query())
            ->from('{{%socialproof_orders}}')
            ->where(['>=', 'dateCreated', $cutoffDate->format('Y-m-d H:i:s')])
            ->count();
    }

    public function importRecentOrders(int $limit = 50): int
    {
        if (!\anvildev\socialproof\Plugin::isCommerceInstalled()) {
            return 0;
        }

        $lookbackDate = Time::utcNow()
            ->modify("-{$this->settings->purchaseLookbackHours} hours");

        $orders = \craft\commerce\elements\Order::find()
            ->isCompleted(true)
            ->dateOrdered('>= ' . $lookbackDate->format('Y-m-d H:i:s'))
            ->limit($limit)
            ->orderBy(['dateOrdered' => SORT_DESC])
            ->all();

        $count = 0;

        foreach ($orders as $order) {
            $exists = (new Query())
                ->from('{{%socialproof_orders}}')
                ->where(['orderId' => $order->id])
                ->exists();

            if (!$exists) {
                $this->handleOrderComplete($order);
                $count++;
            }
        }

        return $count;
    }
}
