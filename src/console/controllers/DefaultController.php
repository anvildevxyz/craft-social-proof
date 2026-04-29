<?php

namespace anvildev\socialproof\console\controllers;

use anvildev\socialproof\Plugin;
use Craft;
use craft\console\Controller;
use yii\console\ExitCode;

class DefaultController extends Controller
{
    /**
     * @var int Days of impression data to keep (default: 90)
     */
    public int $impressionDays = 90;

    /**
     * @var int Hours of order cache to keep (default: 48)
     */
    public int $orderHours = 48;

    /**
     * @var int Max orders to import (default: 50)
     */
    public int $limit = 50;

    /**
     * @var string|null Session ID to purge (for GDPR right to erasure)
     */
    public ?string $sessionId = null;

    public function options($actionID): array
    {
        $options = parent::options($actionID);

        return match ($actionID) {
            'cleanup' => array_merge($options, ['impressionDays', 'orderHours']),
            'import-orders' => array_merge($options, ['limit']),
            'purge-session' => array_merge($options, ['sessionId']),
            default => $options,
        };
    }

    /**
     * Cleans up old impression and order cache data.
     *
     * Examples:
     *   craft social-proof/default/cleanup
     *   craft social-proof/default/cleanup --impression-days=30
     *   craft social-proof/default/cleanup --order-hours=24
     */
    public function actionCleanup(): int
    {
        $this->stdout("Cleaning up Social Proof data...\n");

        $impressionsDeleted = Plugin::$plugin->tracking->cleanupOldData($this->impressionDays);
        $this->stdout("  Impressions removed: {$impressionsDeleted} (older than {$this->impressionDays} days)\n");

        $ordersDeleted = Plugin::$plugin->commerce->cleanupOldOrders($this->orderHours);
        $this->stdout("  Order cache removed: {$ordersDeleted} (older than {$this->orderHours} hours)\n");

        $this->stdout("Done.\n");
        return ExitCode::OK;
    }

    /**
     * Imports recent Commerce orders into the notification cache.
     *
     * Examples:
     *   craft social-proof/default/import-orders
     *   craft social-proof/default/import-orders --limit=100
     */
    public function actionImportOrders(): int
    {
        /** @phpstan-ignore-next-line - Commerce is an optional dependency */
        if (!class_exists(\craft\commerce\Plugin::class)) {
            $this->stderr("Craft Commerce is not installed.\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Importing recent orders (limit: {$this->limit})...\n");

        $count = Plugin::$plugin->commerce->importRecentOrders($this->limit);

        $this->stdout("Imported {$count} orders.\n");
        return ExitCode::OK;
    }

    /**
     * Displays current notification statistics.
     *
     * Examples:
     *   craft social-proof/default/stats
     */
    public function actionStats(): int
    {
        $stats = Plugin::$plugin->tracking->getStats(['period' => '7d']);

        $this->stdout("Social Proof Stats (last 7 days)\n");
        $this->stdout("────────────────────────────────\n");
        $this->stdout("  Impressions:     {$stats['impressions']}\n");
        $this->stdout("  Clicks:          {$stats['clicks']}\n");
        $this->stdout("  Dismisses:       {$stats['dismisses']}\n");
        $this->stdout("  CTR:             {$stats['ctr']}%\n");
        $this->stdout("  Unique visitors: {$stats['uniqueVisitors']}\n");

        /** @phpstan-ignore-next-line - Commerce is an optional dependency */
        if (class_exists(\craft\commerce\Plugin::class)) {
            $orderCount = Plugin::$plugin->commerce->getRecentOrderCount(24);
            $this->stdout("  Orders (24h):    {$orderCount}\n");
        }

        return ExitCode::OK;
    }

    /**
     * Purges all tracking data for a specific session (GDPR right to erasure).
     *
     * Examples:
     *   craft social-proof/default/purge-session --session-id=abc123def456
     */
    public function actionPurgeSession(): int
    {
        if (empty($this->sessionId)) {
            $this->stderr("Session ID is required. Use --session-id=<id>\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Purging tracking data for session: {$this->sessionId}\n");

        $deleted = Craft::$app->getDb()->createCommand()
            ->delete('{{%socialproof_impressions}}', ['sessionId' => $this->sessionId])
            ->execute();

        if ($deleted > 0) {
            $this->stdout("Deleted {$deleted} tracking records.\n");
        } else {
            $this->stdout("No records found for that session.\n");
        }

        return ExitCode::OK;
    }
}
