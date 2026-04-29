<?php

namespace anvildev\socialproof\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property string $layout
 * @property string|null $customTemplate
 * @property string $trigger              JSON
 * @property string|null $targeting       JSON
 * @property string|null $fatigueRules    JSON
 * @property int $priority
 * @property string $propagationMethod
 */
class PopupRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%socialproof_popups}}';
    }
}
