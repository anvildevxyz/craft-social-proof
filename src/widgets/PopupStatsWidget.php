<?php

namespace anvildev\socialproof\widgets;

use anvildev\socialproof\elements\PopupElement;
use Craft;
use craft\base\Widget;
use craft\web\View;

class PopupStatsWidget extends Widget
{
    public int $dayRange = 30;

    public static function displayName(): string
    {
        return Craft::t('social-proof', 'Popup statistics');
    }

    public static function icon(): ?string
    {
        return null;
    }

    public static function maxColspan(): ?int
    {
        return 2;
    }

    public function getTitle(): ?string
    {
        return Craft::t('social-proof', 'Popup statistics');
    }

    public function getBodyHtml(): ?string
    {
        $rows = $this->fetchStats();
        $revenue = \anvildev\socialproof\Plugin::getInstance()->attribution->revenueByPopup($this->dayRange);
        foreach ($rows as &$row) {
            $rid = (int) ($row['popupId'] ?? 0);
            $row['revenue'] = $revenue[$rid]['revenue'] ?? '0.0000';
            $row['orders'] = $revenue[$rid]['orders'] ?? 0;
        }
        unset($row);
        $popupTitles = [];
        foreach ($rows as $row) {
            if (!empty($row['popupId'])) {
                // status(null) so disabled popups still resolve their title
                $popup = PopupElement::find()->id($row['popupId'])->status(null)->one();
                if ($popup) {
                    $popupTitles[$row['popupId']] = $popup->title;
                }
            }
        }
        return Craft::$app->getView()->renderTemplate('social-proof/_widgets/popup-stats-body', [
            'rows' => $rows,
            'dayRange' => $this->dayRange,
            'popupTitles' => $popupTitles,
        ], View::TEMPLATE_MODE_CP);
    }

    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('social-proof/_widgets/popup-stats-settings', [
            'widget' => $this,
        ], View::TEMPLATE_MODE_CP);
    }

    /**
     * @return list<array{popupId: ?int, impressions: int|string, clicks: int|string, dismisses: int|string, converts: int|string}>
     */
    private function fetchStats(): array
    {
        $cutoff = (new \DateTimeImmutable("-{$this->dayRange} days"))->format('Y-m-d H:i:s');
        return (new \craft\db\Query())
            ->select([
                'popupId',
                'impressions' => "SUM(CASE WHEN eventType='impression' THEN 1 ELSE 0 END)",
                'clicks' => "SUM(CASE WHEN eventType='click' THEN 1 ELSE 0 END)",
                'dismisses' => "SUM(CASE WHEN eventType='dismiss' THEN 1 ELSE 0 END)",
                'converts' => "SUM(CASE WHEN eventType='convert' THEN 1 ELSE 0 END)",
            ])
            ->from('{{%socialproof_popup_impressions}}')
            ->where(['>=', 'dateCreated', $cutoff])
            ->groupBy('popupId')
            ->orderBy(['impressions' => SORT_DESC])
            ->all();
    }
}
