<?php

namespace anvildev\socialproof\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property string $type
 * @property string $position
 * @property int $displayDuration
 * @property int $delayBetween
 * @property string $propagationMethod
 */
class NotificationRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%socialproof_notifications}}';
    }

    public function rules(): array
    {
        return [
            [['type'], 'required'],
            [['type'], 'string', 'max' => 50],
            [['position'], 'string', 'max' => 20],
            [['propagationMethod'], 'string', 'max' => 20],
            [['displayDuration', 'delayBetween'], 'integer'],
        ];
    }
}
