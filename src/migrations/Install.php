<?php

namespace anvildev\socialproof\migrations;

use craft\db\Migration;

class Install extends Migration
{
    public function safeUp(): bool
    {
        $this->createTables();
        $this->createIndexes();
        $this->addForeignKeys();

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%socialproof_webhook_deliveries}}');
        $this->dropTableIfExists('{{%socialproof_impressions}}');
        $this->dropTableIfExists('{{%socialproof_orders}}');
        $this->dropTableIfExists('{{%socialproof_notification_sites}}');
        $this->dropTableIfExists('{{%socialproof_notifications}}');

        return true;
    }

    private function createTables(): void
    {
        // id references the elements table (no auto-increment) — Craft element infrastructure
        $this->createTable('{{%socialproof_notifications}}', [
            'id' => $this->integer()->notNull(),
            'type' => $this->string(50)->notNull()->defaultValue('purchase'),
            'position' => $this->string(20)->notNull()->defaultValue('bottom-left'),
            'displayDuration' => $this->integer()->notNull()->defaultValue(5),
            'delayBetween' => $this->integer()->notNull()->defaultValue(10),
            'propagationMethod' => $this->string(20)->notNull()->defaultValue('all'),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
            'PRIMARY KEY([[id]])',
        ]);

        $this->createTable('{{%socialproof_notification_sites}}', [
            'id' => $this->primaryKey(),
            'notificationId' => $this->integer()->notNull(),
            'siteId' => $this->integer()->notNull(),
            'settings' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%socialproof_impressions}}', [
            'id' => $this->primaryKey(),
            'notificationId' => $this->integer(),
            'sessionId' => $this->string(64)->notNull(),
            'eventType' => $this->string(20)->notNull()->defaultValue('impression'),
            'pageUrl' => $this->string(500),
            'metadata' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
        ]);

        $this->createTable('{{%socialproof_orders}}', [
            'id' => $this->primaryKey(),
            'orderId' => $this->integer(),
            'productId' => $this->integer(),
            'productName' => $this->string(255)->notNull(),
            'productImage' => $this->string(500),
            'productUrl' => $this->string(500),
            'customerName' => $this->string(100),
            'customerLocation' => $this->string(100),
            'orderTotal' => $this->decimal(14, 4),
            'dateCreated' => $this->dateTime()->notNull(),
        ]);

        $this->createTable('{{%socialproof_webhook_deliveries}}', [
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
    }

    private function createIndexes(): void
    {
        $this->createIndex(
            'idx_socialproof_notifications_type',
            '{{%socialproof_notifications}}',
            ['type']
        );

        $this->createIndex(
            'idx_socialproof_notification_sites_notificationId_siteId',
            '{{%socialproof_notification_sites}}',
            ['notificationId', 'siteId'],
            true
        );

        $this->createIndex(
            'idx_socialproof_impressions_notificationId',
            '{{%socialproof_impressions}}',
            ['notificationId']
        );
        $this->createIndex(
            'idx_socialproof_impressions_sessionId',
            '{{%socialproof_impressions}}',
            ['sessionId']
        );
        $this->createIndex(
            'idx_socialproof_impressions_eventType_dateCreated',
            '{{%socialproof_impressions}}',
            ['eventType', 'dateCreated']
        );
        $this->createIndex(
            'idx_socialproof_impressions_dateCreated',
            '{{%socialproof_impressions}}',
            ['dateCreated']
        );

        $this->createIndex(
            'idx_socialproof_orders_orderId',
            '{{%socialproof_orders}}',
            ['orderId']
        );
        $this->createIndex(
            'idx_socialproof_orders_dateCreated',
            '{{%socialproof_orders}}',
            ['dateCreated']
        );

        $this->createIndex(
            'idx_socialproof_webhook_deliveries_sub_dispatched',
            '{{%socialproof_webhook_deliveries}}',
            ['subscriptionId', 'dispatchedAt']
        );
        $this->createIndex(
            'idx_socialproof_webhook_deliveries_dispatchedAt',
            '{{%socialproof_webhook_deliveries}}',
            ['dispatchedAt']
        );
        $this->createIndex(
            'idx_socialproof_webhook_deliveries_deliveryId',
            '{{%socialproof_webhook_deliveries}}',
            ['deliveryId']
        );
    }

    private function addForeignKeys(): void
    {
        $this->addForeignKey(
            'fk_socialproof_notifications_id',
            '{{%socialproof_notifications}}',
            ['id'],
            '{{%elements}}',
            ['id'],
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_socialproof_notification_sites_notificationId',
            '{{%socialproof_notification_sites}}',
            ['notificationId'],
            '{{%socialproof_notifications}}',
            ['id'],
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_socialproof_notification_sites_siteId',
            '{{%socialproof_notification_sites}}',
            ['siteId'],
            '{{%sites}}',
            ['id'],
            'CASCADE',
            'CASCADE'
        );

        // SET NULL on delete to preserve impression stats after a notification is deleted
        $this->addForeignKey(
            'fk_socialproof_impressions_notificationId',
            '{{%socialproof_impressions}}',
            ['notificationId'],
            '{{%socialproof_notifications}}',
            ['id'],
            'SET NULL',
            'CASCADE'
        );
    }
}
