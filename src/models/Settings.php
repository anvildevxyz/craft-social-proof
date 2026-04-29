<?php

namespace anvildev\socialproof\models;

use craft\base\Model;

class Settings extends Model
{
    public bool $enabled = true;
    public int $maxNotificationsPerSession = 10;

    public bool $purchaseEnabled = true;
    public int $purchaseLookbackHours = 24;
    public bool $anonymizeCustomers = true;
    public string $purchaseTemplate = '{customer} from {location} purchased {product}';

    public bool $viewersEnabled = false;

    /**
     * @var string Mode for viewer count: 'real', 'calculated', 'static'
     */
    public string $viewersMode = 'real';

    public int $viewersMinimum = 5;
    public int $viewersMultiplier = 1;
    public string $viewersTemplate = '{count} people are viewing this right now';

    public bool $stockEnabled = false;
    public int $stockThreshold = 10;
    public string $stockTemplate = 'Only {count} left in stock!';

    /**
     * @var string Position on screen: 'bottom-left', 'bottom-right', 'top-left', 'top-right'
     */
    public string $position = 'bottom-left';

    public int $displayDuration = 5;
    public int $delayBetween = 10;
    public string $animationIn = 'slideIn';
    public string $animationOut = 'fadeOut';
    public bool $showProductImage = true;
    public bool $showDismissButton = true;

    /**
     * @var string Global link target for notifications ('' = same window, '_blank' = new window)
     */
    public string $linkTarget = '';

    public bool $abTestingEnabled = false;
    public int $abTestPercentage = 50;

    public bool $demoMode = true;

    /**
     * @var bool When true, users with socialProof-managePopups permission
     *   see popups regardless of fatigue rules.
     */
    public bool $popupDemoMode = false;

    /**
     * @var int Events per minute per IP on the /event endpoint. 0 = disabled.
     */
    public int $popupEventRateLimit = 60;

    public bool $popupAttributionEnabled = true;

    /**
     * @var int Attribution window in hours (how long after a convert event an order still counts)
     */
    public int $popupAttributionWindowHours = 24;

    /**
     * @var array Outbound webhook subscriptions. Each row: id, label, url, events[], secret, enabled.
     */
    public array $webhooks = [];

    /**
     * @var int Consecutive delivery failures before a webhook subscription's
     * circuit opens and dispatch starts skipping it. 0 disables the breaker.
     */
    public int $webhookBreakerThreshold = 5;

    /**
     * @var int Cooldown in seconds before an OPEN circuit allows the next
     * delivery attempt. Each failure during OPEN extends this window.
     */
    public int $webhookBreakerCooldownSeconds = 300;

    public array $excludedProductTypes = [];

    /**
     * @var array Category IDs to include (empty = all)
     */
    public array $includedCategories = [];

    /**
     * @var array URL patterns where notifications should appear (empty = all pages)
     */
    public array $includedUrlPatterns = [];

    public array $excludedUrlPatterns = [];

    public function defineRules(): array
    {
        return [
            [['enabled'], 'boolean'],
            [['maxNotificationsPerSession'], 'integer', 'min' => 1, 'max' => 100],

            [['purchaseEnabled', 'anonymizeCustomers'], 'boolean'],
            [['purchaseLookbackHours'], 'integer', 'min' => 1, 'max' => 168],
            [['purchaseTemplate'], 'string', 'max' => 500],

            [['viewersEnabled'], 'boolean'],
            [['viewersMode'], 'in', 'range' => ['real', 'calculated', 'static']],
            [['viewersMinimum'], 'integer', 'min' => 0, 'max' => 1000],
            [['viewersMultiplier'], 'integer', 'min' => 1, 'max' => 10],
            [['viewersTemplate'], 'string', 'max' => 500],

            [['stockEnabled'], 'boolean'],
            [['stockThreshold'], 'integer', 'min' => 1, 'max' => 1000],
            [['stockTemplate'], 'string', 'max' => 500],

            [['position'], 'in', 'range' => \anvildev\socialproof\enums\NotificationPosition::values()],
            [['displayDuration'], 'integer', 'min' => 1, 'max' => 60],
            [['delayBetween'], 'integer', 'min' => 1, 'max' => 120],
            [['animationIn'], 'in', 'range' => ['slideIn', 'fadeIn', 'bounceIn']],
            [['animationOut'], 'in', 'range' => ['slideOut', 'fadeOut', 'bounceOut']],
            [['showProductImage', 'showDismissButton'], 'boolean'],
            [['linkTarget'], 'in', 'range' => ['', '_blank']],

            [['abTestingEnabled'], 'boolean'],
            [['abTestPercentage'], 'integer', 'min' => 1, 'max' => 99],

            [['demoMode'], 'boolean'],
            [['popupDemoMode'], 'boolean'],
            [['popupEventRateLimit'], 'integer', 'min' => 0, 'max' => 10000],
            [['popupAttributionEnabled'], 'boolean'],
            [['popupAttributionWindowHours'], 'integer', 'min' => 1, 'max' => 720],
            [['webhookBreakerThreshold'], 'integer', 'min' => 0, 'max' => 1000],
            [['webhookBreakerCooldownSeconds'], 'integer', 'min' => 1, 'max' => 86400],

            [['excludedProductTypes', 'includedCategories', 'includedUrlPatterns', 'excludedUrlPatterns'], 'each', 'rule' => ['string']],

            [['webhooks'], function ($attr) {
                $rows = $this->$attr ?? [];
                if (!is_array($rows)) {
                    $this->addError($attr, 'Webhooks must be an array.');
                    return;
                }
                foreach ($rows as $i => $row) {
                    foreach (\anvildev\socialproof\models\Webhook::validateRow($row) as $err) {
                        $this->addError($attr, "[row {$i}] {$err}");
                    }
                }
                $ids = array_column($rows, 'id');
                if (count($ids) !== count(array_unique($ids))) {
                    $this->addError($attr, 'Webhook ids must be unique.');
                }
            }],
        ];
    }
}
