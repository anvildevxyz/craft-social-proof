<?php

namespace anvildev\socialproof\migrations;

use craft\db\Migration;

/**
 * Adds propagationMethod column to socialproof_notifications table.
 *
 * Allows each notification to independently control how it propagates
 * across sites (none, all, siteGroup, language).
 */
class m250212_100000_add_propagation_method extends Migration
{
    public function safeUp(): bool
    {
        $this->addColumn(
            '{{%socialproof_notifications}}',
            'propagationMethod',
            $this->string(20)->notNull()->defaultValue('all')->after('delayBetween')
        );

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropColumn('{{%socialproof_notifications}}', 'propagationMethod');

        return true;
    }
}
