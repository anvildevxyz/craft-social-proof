<?php

namespace anvildev\socialproof\elements\db;

use anvildev\socialproof\elements\NotificationElement;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;

class NotificationQuery extends ElementQuery
{
    /** @var string|string[]|null */
    public mixed $type = null;

    /** @var string|string[]|null */
    public mixed $position = null;

    public ?int $displayDuration = null;

    public ?int $delayBetween = null;

    public function type(mixed $value): static
    {
        $this->type = $value;
        return $this;
    }

    protected function beforePrepare(): bool
    {
        $this->joinElementTable('socialproof_notifications');

        // Join per-site settings table in the main query only.
        // The subQuery can't reference elements_sites because Craft joins it
        // after beforePrepare() runs; the main query has it available.
        $this->query->innerJoin(
            '{{%socialproof_notification_sites}} notification_sites',
            '[[notification_sites.notificationId]] = [[socialproof_notifications.id]] AND [[notification_sites.siteId]] = [[elements_sites.siteId]]'
        );

        $this->query->addSelect([
            'socialproof_notifications.type',
            'notification_sites.settings',
            'socialproof_notifications.position',
            'socialproof_notifications.displayDuration',
            'socialproof_notifications.delayBetween',
            'socialproof_notifications.propagationMethod',
        ]);

        if ($this->type) {
            $this->subQuery->andWhere(Db::parseParam('socialproof_notifications.type', $this->type));
        }

        if ($this->position) {
            $this->subQuery->andWhere(Db::parseParam('socialproof_notifications.position', $this->position));
        }

        if ($this->displayDuration !== null) {
            $this->subQuery->andWhere(['socialproof_notifications.displayDuration' => $this->displayDuration]);
        }

        if ($this->delayBetween !== null) {
            $this->subQuery->andWhere(['socialproof_notifications.delayBetween' => $this->delayBetween]);
        }

        return parent::beforePrepare();
    }

    protected function statusCondition(string $status): mixed
    {
        return match ($status) {
            NotificationElement::STATUS_ENABLED => [
                'elements.enabled' => true,
            ],
            NotificationElement::STATUS_DISABLED => [
                'elements.enabled' => false,
            ],
            default => parent::statusCondition($status),
        };
    }
}
