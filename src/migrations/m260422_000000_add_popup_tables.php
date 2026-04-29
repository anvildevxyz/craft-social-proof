<?php

namespace anvildev\socialproof\migrations;

use craft\db\Migration;

/**
 * Adds the 4 popup subsystem tables alongside the existing notification tables.
 *
 * - socialproof_popups: non-localized popup config, linked to elements
 * - socialproof_popup_sites: per-site settings (localized copy)
 * - socialproof_popup_impressions: event log (impression/click/dismiss/convert)
 * - socialproof_popup_fatigue: server-side fatigue store per (popupId, visitorId)
 *
 * Does NOT touch the existing socialproof_notifications / socialproof_impressions /
 * socialproof_orders tables — the two subsystems are independent.
 */
class m260422_000000_add_popup_tables extends Migration
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
        $this->dropTableIfExists('{{%socialproof_popup_fatigue}}');
        $this->dropTableIfExists('{{%socialproof_popup_impressions}}');
        $this->dropTableIfExists('{{%socialproof_popup_sites}}');
        $this->dropTableIfExists('{{%socialproof_popups}}');
        return true;
    }

    private function createTables(): void
    {
        $this->createTable('{{%socialproof_popups}}', [
            'id' => $this->integer()->notNull(),
            'layout' => $this->string(30)->notNull()->defaultValue('announcement'),
            'customTemplate' => $this->string(500),
            'trigger' => $this->text()->notNull(),
            'targeting' => $this->text(),
            'fatigueRules' => $this->text(),
            'priority' => $this->integer()->notNull()->defaultValue(50),
            'propagationMethod' => $this->string(20)->notNull()->defaultValue('all'),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
            'PRIMARY KEY([[id]])',
        ]);

        $this->createTable('{{%socialproof_popup_sites}}', [
            'id' => $this->primaryKey(),
            'popupId' => $this->integer()->notNull(),
            'siteId' => $this->integer()->notNull(),
            'layoutSettings' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%socialproof_popup_impressions}}', [
            'id' => $this->primaryKey(),
            'popupId' => $this->integer(),
            'visitorId' => $this->string(64)->notNull(),
            'sessionId' => $this->string(64),
            'eventType' => $this->string(20)->notNull()->defaultValue('impression'),
            'pageUrl' => $this->string(500),
            'dateCreated' => $this->dateTime()->notNull(),
        ]);

        $this->createTable('{{%socialproof_popup_fatigue}}', [
            'id' => $this->primaryKey(),
            'popupId' => $this->integer()->notNull(),
            'visitorId' => $this->string(64)->notNull(),
            'shownCount' => $this->integer()->notNull()->defaultValue(0),
            'lastShownAt' => $this->dateTime(),
            'dismissedAt' => $this->dateTime(),
            'convertedAt' => $this->dateTime(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
        ]);
    }

    private function createIndexes(): void
    {
        $this->createIndex('idx_socialproof_popups_layout', '{{%socialproof_popups}}', ['layout']);
        $this->createIndex('idx_socialproof_popup_sites_popupId_siteId', '{{%socialproof_popup_sites}}', ['popupId', 'siteId'], true);
        $this->createIndex('idx_socialproof_popup_impressions_popupId', '{{%socialproof_popup_impressions}}', ['popupId']);
        $this->createIndex('idx_socialproof_popup_impressions_visitorId', '{{%socialproof_popup_impressions}}', ['visitorId']);
        $this->createIndex('idx_socialproof_popup_impressions_eventType_dateCreated', '{{%socialproof_popup_impressions}}', ['eventType', 'dateCreated']);
        $this->createIndex('idx_socialproof_popup_fatigue_popupId_visitorId', '{{%socialproof_popup_fatigue}}', ['popupId', 'visitorId'], true);
        $this->createIndex('idx_socialproof_popup_fatigue_lastShownAt', '{{%socialproof_popup_fatigue}}', ['lastShownAt']);
    }

    private function addForeignKeys(): void
    {
        $this->addForeignKey('fk_socialproof_popups_id', '{{%socialproof_popups}}', ['id'], '{{%elements}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_socialproof_popup_sites_popupId', '{{%socialproof_popup_sites}}', ['popupId'], '{{%socialproof_popups}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_socialproof_popup_sites_siteId', '{{%socialproof_popup_sites}}', ['siteId'], '{{%sites}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_socialproof_popup_impressions_popupId', '{{%socialproof_popup_impressions}}', ['popupId'], '{{%socialproof_popups}}', ['id'], 'SET NULL', 'CASCADE');
        $this->addForeignKey('fk_socialproof_popup_fatigue_popupId', '{{%socialproof_popup_fatigue}}', ['popupId'], '{{%socialproof_popups}}', ['id'], 'CASCADE', 'CASCADE');
    }
}
