<?php

namespace anvildev\socialproof\services;

use anvildev\socialproof\records\PopupFatigueRecord;
use Craft;
use yii\db\Expression;

/**
 * Production FatigueStore backed by the {{%socialproof_popup_fatigue}} table.
 *
 * @phpstan-import-type FatigueRow from FatigueStore
 */
class DbFatigueStore implements FatigueStore
{
    /**
     * @return FatigueRow|null
     */
    public function get(int $popupId, string $visitorId): ?array
    {
        $record = PopupFatigueRecord::findOne(['popupId' => $popupId, 'visitorId' => $visitorId]);
        if ($record === null) {
            return null;
        }

        return [
            'shownCount' => (int) $record->shownCount,
            'lastShownAt' => $record->lastShownAt,
            'dismissedAt' => $record->dismissedAt,
            'convertedAt' => $record->convertedAt,
        ];
    }

    public function set(int $popupId, string $visitorId, array $data): void
    {
        $record = $this->findOrCreate($popupId, $visitorId);
        foreach ($data as $field => $value) {
            $record->$field = $value;
        }
        $record->save(false);
    }

    public function increment(int $popupId, string $visitorId, string $timestamp): void
    {
        $affected = Craft::$app->getDb()->createCommand()
            ->update(
                '{{%socialproof_popup_fatigue}}',
                [
                    'shownCount' => new Expression('[[shownCount]] + 1'),
                    'lastShownAt' => $timestamp,
                    'dateUpdated' => new Expression('NOW()'),
                ],
                ['popupId' => $popupId, 'visitorId' => $visitorId],
            )
            ->execute();

        if ($affected === 0) {
            $record = new PopupFatigueRecord();
            $record->popupId = $popupId;
            $record->visitorId = $visitorId;
            $record->shownCount = 1;
            $record->lastShownAt = $timestamp;
            $record->save(false);
        }
    }

    private function findOrCreate(int $popupId, string $visitorId): PopupFatigueRecord
    {
        $record = PopupFatigueRecord::findOne(['popupId' => $popupId, 'visitorId' => $visitorId]);
        if ($record === null) {
            $record = new PopupFatigueRecord();
            $record->popupId = $popupId;
            $record->visitorId = $visitorId;
            $record->shownCount = 0;
        }
        return $record;
    }
}
