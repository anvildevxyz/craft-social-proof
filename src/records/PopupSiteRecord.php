<?php

namespace anvildev\socialproof\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int $popupId
 * @property int $siteId
 * @property string|null $layoutSettings  JSON
 */
class PopupSiteRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%socialproof_popup_sites}}';
    }
}
