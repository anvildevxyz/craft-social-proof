<?php

namespace anvildev\socialproof\elements\db;

use anvildev\socialproof\elements\PopupElement;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;

class PopupQuery extends ElementQuery
{
    /** @var string|string[]|null */
    public mixed $layout = null;

    public ?int $minPriority = null;

    public function layout(mixed $value): static
    {
        $this->layout = $value;
        return $this;
    }

    public function minPriority(?int $value): static
    {
        $this->minPriority = $value;
        return $this;
    }

    protected function beforePrepare(): bool
    {
        $this->joinElementTable('socialproof_popups');

        // Join per-site settings table in the main query only.
        // The subQuery can't reference elements_sites because Craft joins it
        // after beforePrepare() runs; the main query has it available.
        $this->query->innerJoin(
            '{{%socialproof_popup_sites}} popup_sites',
            '[[popup_sites.popupId]] = [[socialproof_popups.id]] AND [[popup_sites.siteId]] = [[elements_sites.siteId]]'
        );

        $this->query->addSelect([
            'socialproof_popups.layout',
            'socialproof_popups.customTemplate',
            'socialproof_popups.trigger',
            'socialproof_popups.targeting',
            'socialproof_popups.fatigueRules',
            'socialproof_popups.priority',
            'socialproof_popups.propagationMethod',
            'popup_sites.layoutSettings',
        ]);

        if ($this->layout) {
            $this->subQuery->andWhere(Db::parseParam('socialproof_popups.layout', $this->layout));
        }

        if ($this->minPriority !== null) {
            $this->subQuery->andWhere(['>=', 'socialproof_popups.priority', $this->minPriority]);
        }

        return parent::beforePrepare();
    }

    protected function statusCondition(string $status): mixed
    {
        return match ($status) {
            PopupElement::STATUS_ENABLED => [
                'elements.enabled' => true,
            ],
            PopupElement::STATUS_DISABLED => [
                'elements.enabled' => false,
            ],
            default => parent::statusCondition($status),
        };
    }
}
