<?php

namespace anvildev\socialproof\services;

use anvildev\socialproof\helpers\Time;
use anvildev\socialproof\models\Impression;
use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\helpers\DateTimeHelper;

class TrackingService extends Component
{
    /**
     * @param int|null $notificationId null for dynamic notifications (e.g. viewer counts)
     * @param string $eventType one of: 'impression', 'click', 'dismiss', 'heartbeat'
     * @param array<string, mixed> $metadata
     */
    public function trackEvent(
        ?int $notificationId,
        string $sessionId,
        string $eventType,
        ?string $pageUrl = null,
        array $metadata = []
    ): bool {
        $impression = new Impression([
            'notificationId' => $notificationId,
            'sessionId' => $sessionId,
            'eventType' => $eventType,
            'pageUrl' => $pageUrl,
            'metadata' => $metadata,
        ]);

        if (!$impression->validate()) {
            Craft::error('Invalid impression data: ' . json_encode($impression->getErrors()), __METHOD__);
            return false;
        }

        Craft::$app->getDb()->createCommand()
            ->insert('{{%socialproof_impressions}}', [
                'notificationId' => $impression->notificationId,
                'sessionId' => $impression->sessionId,
                'eventType' => $impression->eventType,
                'pageUrl' => $impression->pageUrl,
                'metadata' => json_encode($impression->metadata),
                'dateCreated' => DateTimeHelper::currentUTCDateTime()->format('Y-m-d H:i:s'),
            ])
            ->execute();

        return true;
    }

    public function getSessionImpressionCount(string $sessionId): int
    {
        $today = Time::utcNow()->format('Y-m-d 00:00:00');

        return (int)(new Query())
            ->from('{{%socialproof_impressions}}')
            ->where([
                'sessionId' => $sessionId,
                'eventType' => 'impression',
            ])
            ->andWhere(['>=', 'dateCreated', $today])
            ->count();
    }

    /**
     * @param array{period?: string, notificationId?: int|null} $options period (e.g. '7d', '30d', '90d') and optional notificationId
     */
    public function getStats(array $options = []): array
    {
        $period = $options['period'] ?? '7d';
        $notificationId = $options['notificationId'] ?? null;

        // Parse "<n>d" period; clamp to a safe range to bound query cost
        $days = preg_match('/^(\d+)d$/', $period, $m) ? (int)$m[1] : 7;
        $days = max(1, min($days, 365));
        $startDate = Time::utcNow()->modify("-{$days} days");

        $baseQuery = (new Query())
            ->from('{{%socialproof_impressions}}')
            ->where(['>=', 'dateCreated', $startDate->format('Y-m-d H:i:s')]);

        if ($notificationId) {
            $baseQuery->andWhere(['notificationId' => $notificationId]);
        }

        $impressions = (clone $baseQuery)
            ->andWhere(['eventType' => 'impression'])
            ->count();

        $clicks = (clone $baseQuery)
            ->andWhere(['eventType' => 'click'])
            ->count();

        $dismisses = (clone $baseQuery)
            ->andWhere(['eventType' => 'dismiss'])
            ->count();

        $ctr = $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0;

        $dailyStats = $this->_getDailyStats($startDate, $notificationId);
        $topNotifications = $this->_getTopNotifications($startDate, 5);

        $uniqueVisitors = (clone $baseQuery)
            ->select(['sessionId'])
            ->distinct()
            ->count();

        return [
            'impressions' => (int)$impressions,
            'clicks' => (int)$clicks,
            'dismisses' => (int)$dismisses,
            'ctr' => $ctr,
            'uniqueVisitors' => (int)$uniqueVisitors,
            'dailyStats' => $dailyStats,
            'topNotifications' => $topNotifications,
            'period' => $period,
        ];
    }

    /**
     * @return list<array{date: string, impressions: int, clicks: int, dismisses: int}>
     */
    private function _getDailyStats(\DateTimeImmutable $startDate, ?int $notificationId = null): array
    {
        $db = Craft::$app->getDb();
        $dateFormat = $db->getIsMysql() ? "DATE_FORMAT(dateCreated, '%Y-%m-%d')" : "TO_CHAR(dateCreated, 'YYYY-MM-DD')";

        $query = (new Query())
            ->select([
                'date' => $dateFormat,
                'impressions' => "SUM(CASE WHEN eventType = 'impression' THEN 1 ELSE 0 END)",
                'clicks' => "SUM(CASE WHEN eventType = 'click' THEN 1 ELSE 0 END)",
                'dismisses' => "SUM(CASE WHEN eventType = 'dismiss' THEN 1 ELSE 0 END)",
            ])
            ->from('{{%socialproof_impressions}}')
            ->where(['>=', 'dateCreated', $startDate->format('Y-m-d H:i:s')])
            ->groupBy([$dateFormat])
            ->orderBy(['date' => SORT_ASC]);

        if ($notificationId) {
            $query->andWhere(['notificationId' => $notificationId]);
        }

        $results = $query->all();

        // Fill in zero rows for days with no events so the chart spans the full range
        $stats = [];
        $currentDate = clone $startDate;
        $now = Time::utcNow();

        while ($currentDate <= $now) {
            $dateKey = $currentDate->format('Y-m-d');
            $dayData = null;

            foreach ($results as $row) {
                if ($row['date'] === $dateKey) {
                    $dayData = $row;
                    break;
                }
            }

            $stats[] = [
                'date' => $dateKey,
                'impressions' => $dayData ? (int)$dayData['impressions'] : 0,
                'clicks' => $dayData ? (int)$dayData['clicks'] : 0,
                'dismisses' => $dayData ? (int)$dayData['dismisses'] : 0,
            ];

            $currentDate = $currentDate->modify('+1 day');
        }

        return $stats;
    }

    /**
     * @return list<array{notificationId: int, impressions: int, clicks: int, ctr: float|int}>
     */
    private function _getTopNotifications(\DateTimeImmutable $startDate, int $limit = 5): array
    {
        $results = (new Query())
            ->select([
                'notificationId',
                'impressions' => "SUM(CASE WHEN eventType = 'impression' THEN 1 ELSE 0 END)",
                'clicks' => "SUM(CASE WHEN eventType = 'click' THEN 1 ELSE 0 END)",
            ])
            ->from('{{%socialproof_impressions}}')
            ->where(['>=', 'dateCreated', $startDate->format('Y-m-d H:i:s')])
            ->andWhere(['not', ['notificationId' => null]])
            ->groupBy(['notificationId'])
            ->having(['>', "SUM(CASE WHEN eventType = 'impression' THEN 1 ELSE 0 END)", 10])
            ->orderBy(['clicks' => SORT_DESC])
            ->limit($limit)
            ->all();

        $topNotifications = [];

        foreach ($results as $row) {
            $impressions = (int)$row['impressions'];
            $clicks = (int)$row['clicks'];
            $ctr = $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0;

            $topNotifications[] = [
                'notificationId' => (int)$row['notificationId'],
                'impressions' => $impressions,
                'clicks' => $clicks,
                'ctr' => $ctr,
            ];
        }

        return $topNotifications;
    }

    public function cleanupOldData(int $daysToKeep = 90): int
    {
        $cutoffDate = Time::utcNow()->modify("-{$daysToKeep} days");

        $result = Craft::$app->getDb()->createCommand()
            ->delete('{{%socialproof_impressions}}', [
                '<', 'dateCreated', $cutoffDate->format('Y-m-d H:i:s'),
            ])
            ->execute();

        Craft::info("Cleaned up {$result} old impression records", __METHOD__);

        return $result;
    }

    public function getWidgetStats(): array
    {
        $sevenDayStats = $this->getStats(['period' => '7d']);

        // Compare against the prior 7-day window
        $now = Time::utcNow();
        $fourteenDaysAgo = $now->modify('-14 days');
        $sevenDaysAgo = $now->modify('-7 days');

        $previousImpressions = (new Query())
            ->from('{{%socialproof_impressions}}')
            ->where(['>=', 'dateCreated', $fourteenDaysAgo->format('Y-m-d H:i:s')])
            ->andWhere(['<', 'dateCreated', $sevenDaysAgo->format('Y-m-d H:i:s')])
            ->andWhere(['eventType' => 'impression'])
            ->count();

        $previousClicks = (new Query())
            ->from('{{%socialproof_impressions}}')
            ->where(['>=', 'dateCreated', $fourteenDaysAgo->format('Y-m-d H:i:s')])
            ->andWhere(['<', 'dateCreated', $sevenDaysAgo->format('Y-m-d H:i:s')])
            ->andWhere(['eventType' => 'click'])
            ->count();

        $impressionsTrend = $this->_calculateTrend($sevenDayStats['impressions'], (int)$previousImpressions);
        $clicksTrend = $this->_calculateTrend($sevenDayStats['clicks'], (int)$previousClicks);

        return [
            'impressions' => $sevenDayStats['impressions'],
            'clicks' => $sevenDayStats['clicks'],
            'ctr' => $sevenDayStats['ctr'],
            'uniqueVisitors' => $sevenDayStats['uniqueVisitors'],
            'impressionsTrend' => $impressionsTrend,
            'clicksTrend' => $clicksTrend,
            'dailyStats' => $sevenDayStats['dailyStats'],
        ];
    }

    private function _calculateTrend(int $current, int $previous): array
    {
        if ($previous === 0) {
            return [
                'direction' => $current > 0 ? 'up' : 'neutral',
                'percentage' => $current > 0 ? 100 : 0,
            ];
        }

        $change = (($current - $previous) / $previous) * 100;

        return [
            'direction' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'neutral'),
            'percentage' => abs(round($change, 1)),
        ];
    }

}
