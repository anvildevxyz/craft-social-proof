<?php

namespace anvildev\socialproof\migrations;

use craft\db\Migration;

class m260426_000000_add_webhook_deliveries extends Migration
{
    public function safeUp(): bool
    {
        $table = '{{%socialproof_webhook_deliveries}}';
        if ($this->db->tableExists($table)) {
            return true;
        }

        $this->createTable($table, [
            'id' => $this->primaryKey(),
            'subscriptionId' => $this->string(64)->notNull(),
            'event' => $this->string(64)->notNull(),
            'deliveryId' => $this->string(64)->notNull(),
            'statusCode' => $this->integer(),
            'responseSnippet' => $this->text(),
            'transportError' => $this->text(),
            'dispatchedAt' => $this->dateTime()->notNull(),
            'completedAt' => $this->dateTime(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createIndex(null, $table, ['subscriptionId', 'dispatchedAt']);
        $this->createIndex(null, $table, ['dispatchedAt']);
        $this->createIndex(null, $table, ['deliveryId']);

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%socialproof_webhook_deliveries}}');
        return true;
    }
}
