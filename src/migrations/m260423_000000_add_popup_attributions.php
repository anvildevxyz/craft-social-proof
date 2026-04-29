<?php

namespace anvildev\socialproof\migrations;

use craft\db\Migration;

class m260423_000000_add_popup_attributions extends Migration
{
    public function safeUp(): bool
    {
        $table = '{{%socialproof_popup_attributions}}';
        if ($this->db->tableExists($table)) {
            return true;
        }

        $this->createTable($table, [
            'id' => $this->primaryKey(),
            'popupId' => $this->integer(),
            'visitorId' => $this->string(64)->notNull(),
            'sessionId' => $this->string(64),
            'orderId' => $this->integer(),
            'orderTotal' => $this->decimal(14, 4),
            'currency' => $this->string(3),
            'status' => $this->string(20)->notNull()->defaultValue('pending'),
            'convertedAt' => $this->dateTime()->notNull(),
            'attributedAt' => $this->dateTime(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createIndex(null, $table, ['visitorId', 'status', 'convertedAt']);
        $this->createIndex(null, $table, ['status']);
        $this->createIndex(null, $table, ['popupId']);

        $this->addForeignKey(null, $table, ['popupId'], '{{%socialproof_popups}}', ['id'], 'SET NULL');

        if ($this->db->tableExists('{{%commerce_orders}}')) {
            $this->addForeignKey(null, $table, ['orderId'], '{{%commerce_orders}}', ['id'], 'SET NULL');
        }

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%socialproof_popup_attributions}}');
        return true;
    }
}
