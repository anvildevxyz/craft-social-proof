<?php

namespace anvildev\socialproof\console\controllers;

use anvildev\socialproof\elements\PopupElement;
use anvildev\socialproof\records\PopupFatigueRecord;
use anvildev\socialproof\records\PopupImpressionRecord;
use craft\console\Controller;
use yii\console\ExitCode;

class PopupsController extends Controller
{
    public int $impressionDays = 90;
    public int $fatigueDays = 180;
    public ?int $popupId = null;
    public int $since = 7;
    public string $session = '';

    public function options($actionID): array
    {
        $options = parent::options($actionID);
        return match ($actionID) {
            'cleanup' => array_merge($options, ['impressionDays', 'fatigueDays']),
            'stats' => array_merge($options, ['popupId', 'since']),
            'purge-session' => array_merge($options, ['session']),
            default => $options,
        };
    }

    public function actionCleanup(): int
    {
        $impCutoff = (new \DateTimeImmutable("-{$this->impressionDays} days"))->format('Y-m-d H:i:s');
        $fatCutoff = (new \DateTimeImmutable("-{$this->fatigueDays} days"))->format('Y-m-d H:i:s');
        $imp = PopupImpressionRecord::deleteAll(['<', 'dateCreated', $impCutoff]);
        $fat = PopupFatigueRecord::deleteAll(['<', 'lastShownAt', $fatCutoff]);
        $this->stdout("Pruned {$imp} impression rows older than {$this->impressionDays} days.\n");
        $this->stdout("Pruned {$fat} fatigue rows older than {$this->fatigueDays} days.\n");
        return ExitCode::OK;
    }

    public function actionStats(): int
    {
        $cutoff = (new \DateTimeImmutable("-{$this->since} days"))->format('Y-m-d H:i:s');

        $query = (new \craft\db\Query())
            ->select([
                'popupId',
                'impressions' => "SUM(CASE WHEN eventType='impression' THEN 1 ELSE 0 END)",
                'clicks' => "SUM(CASE WHEN eventType='click' THEN 1 ELSE 0 END)",
                'dismisses' => "SUM(CASE WHEN eventType='dismiss' THEN 1 ELSE 0 END)",
                'converts' => "SUM(CASE WHEN eventType='convert' THEN 1 ELSE 0 END)",
            ])
            ->from('{{%socialproof_popup_impressions}}')
            ->where(['>=', 'dateCreated', $cutoff])
            ->groupBy('popupId');

        if ($this->popupId !== null) {
            $query->andWhere(['popupId' => $this->popupId]);
        }

        $rows = $query->all();

        $revenue = \anvildev\socialproof\Plugin::getInstance()->attribution->revenueByPopup($this->since);

        $this->stdout(sprintf("%-6s %-32s %10s %8s %10s %10s %6s %12s\n", 'id', 'title', 'imps', 'clicks', 'dismisses', 'converts', 'CTR', 'revenue'));
        $this->stdout(str_repeat('-', 105) . "\n");

        foreach ($rows as $row) {
            // status(null) so disabled popups still resolve; anyStatus() would too but status(null) is explicit
            $popup = !empty($row['popupId']) ? PopupElement::find()->id($row['popupId'])->status(null)->one() : null;
            $title = $popup ? substr($popup->title, 0, 30) : '(deleted)';
            $imp = (int) $row['impressions'];
            $ctr = $imp > 0 ? round((int) $row['clicks'] / $imp * 100, 1) . '%' : '-';
            $rev = number_format((float) ($revenue[(int) $row['popupId']]['revenue'] ?? 0), 2, '.', '');
            $this->stdout(sprintf("%-6s %-32s %10s %8s %10s %10s %6s %12s\n",
                $row['popupId'] ?? '-',
                $title,
                $imp,
                (int) $row['clicks'],
                (int) $row['dismisses'],
                (int) $row['converts'],
                $ctr,
                $rev,
            ));
        }
        if (empty($rows)) {
            $this->stdout("No popup events in the last {$this->since} days.\n");
        }
        return ExitCode::OK;
    }

    /**
     * GDPR right-to-erasure: remove all rows for a given visitorId.
     *
     * Usage: craft social-proof/popups/purge-session --session=<visitorId>
     */
    public function actionPurgeSession(): int
    {
        if (empty($this->session)) {
            $this->stderr("Error: --session=<visitorId> is required\n");
            return ExitCode::USAGE;
        }
        $imp = PopupImpressionRecord::deleteAll(['visitorId' => $this->session]);
        $fat = PopupFatigueRecord::deleteAll(['visitorId' => $this->session]);
        $this->stdout("Removed {$imp} impression rows and {$fat} fatigue rows for visitorId {$this->session}.\n");
        return ExitCode::OK;
    }
}
