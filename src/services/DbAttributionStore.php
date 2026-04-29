<?php

namespace anvildev\socialproof\services;

use anvildev\socialproof\records\PopupAttributionRecord;
use craft\db\Query;
use craft\helpers\Db;

/**
 * @phpstan-import-type RevenueByPopup from AttributionStore
 */
class DbAttributionStore implements AttributionStore
{
    public function recordPending(int $popupId, string $visitorId, ?string $sessionId, \DateTimeInterface $convertedAt): void
    {
        $rec = new PopupAttributionRecord();
        $rec->popupId = $popupId;
        $rec->visitorId = $visitorId;
        $rec->sessionId = $sessionId;
        $rec->status = PopupAttributionRecord::STATUS_PENDING;
        $rec->convertedAt = Db::prepareDateForDb($convertedAt);
        $rec->save(false);
    }

    public function attribute(
        string $visitorId,
        ?string $sessionId,
        int $orderId,
        string $orderTotal,
        ?string $currency,
        \DateTimeInterface $completedAt,
        int $windowHours,
    ): ?int {
        $cutoff = (clone $completedAt)->modify("-{$windowHours} hours");
        $query = PopupAttributionRecord::find()
            ->where(['status' => PopupAttributionRecord::STATUS_PENDING])
            ->andWhere(['>=', 'convertedAt', Db::prepareDateForDb($cutoff)])
            ->orderBy(['convertedAt' => SORT_DESC, 'id' => SORT_DESC])
            ->limit(1);

        if ($sessionId !== null) {
            $query->andWhere(['or', ['visitorId' => $visitorId], ['sessionId' => $sessionId]]);
        } else {
            $query->andWhere(['visitorId' => $visitorId]);
        }

        /** @var PopupAttributionRecord|null $row */
        $row = $query->one();
        if (!$row) {
            return null;
        }

        $row->status = PopupAttributionRecord::STATUS_ATTRIBUTED;
        $row->orderId = $orderId;
        $row->orderTotal = $orderTotal;
        $row->currency = $currency;
        $row->attributedAt = Db::prepareDateForDb($completedAt);
        $row->save(false);

        return (int) $row->id;
    }

    public function expireOlderThan(\DateTimeInterface $cutoff): int
    {
        return PopupAttributionRecord::updateAll(
            ['status' => PopupAttributionRecord::STATUS_EXPIRED],
            ['and', ['status' => PopupAttributionRecord::STATUS_PENDING], ['<', 'convertedAt', Db::prepareDateForDb($cutoff)]],
        );
    }

    /**
     * @return RevenueByPopup
     */
    public function revenueByPopup(\DateTimeInterface $since): array
    {
        $rows = (new Query())
            ->select(['popupId', 'revenue' => 'SUM(orderTotal)', 'orders' => 'COUNT(*)'])
            ->from(PopupAttributionRecord::tableName())
            ->where(['status' => PopupAttributionRecord::STATUS_ATTRIBUTED])
            ->andWhere(['>=', 'attributedAt', Db::prepareDateForDb($since)])
            ->groupBy('popupId')
            ->all();

        $out = [];
        foreach ($rows as $r) {
            if ($r['popupId'] !== null) {
                $out[(int) $r['popupId']] = [
                    'revenue' => (string) $r['revenue'],
                    'orders' => (int) $r['orders'],
                ];
            }
        }
        return $out;
    }
}
