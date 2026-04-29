<?php

namespace anvildev\socialproof\migrations;

use anvildev\socialproof\elements\NotificationElement;
use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\Db;

/**
 * Converts notifications from simple records to Craft elements
 */
class m240205_000000_convert_to_elements extends Migration
{
    public function safeUp(): bool
    {
        // Capture existing rows before mutating the schema
        $existingNotifications = (new Query())
            ->from('{{%socialproof_notifications}}')
            ->all();

        $this->safeDropForeignKey('{{%socialproof_impressions}}', 'fk_socialproof_impressions_notificationId');
        $this->safeDropForeignKey('{{%socialproof_notifications}}', 'fk_socialproof_notifications_siteId');

        $this->safeDropIndex('{{%socialproof_notifications}}', 'idx_socialproof_notifications_type_enabled');
        $this->safeDropIndex('{{%socialproof_notifications}}', 'idx_socialproof_notifications_siteId');

        // name/enabled/siteId now live in the elements/elements_sites tables
        if ($this->db->columnExists('{{%socialproof_notifications}}', 'name')) {
            $this->dropColumn('{{%socialproof_notifications}}', 'name');
        }
        if ($this->db->columnExists('{{%socialproof_notifications}}', 'enabled')) {
            $this->dropColumn('{{%socialproof_notifications}}', 'enabled');
        }
        if ($this->db->columnExists('{{%socialproof_notifications}}', 'siteId')) {
            $this->dropColumn('{{%socialproof_notifications}}', 'siteId');
        }

        $this->alterColumn('{{%socialproof_notifications}}', 'type', $this->string(50)->notNull()->defaultValue('purchase'));
        $this->alterColumn('{{%socialproof_notifications}}', 'position', $this->string(20)->notNull()->defaultValue('bottom-left'));

        $this->createTable('{{%socialproof_notifications_temp}}', [
            'id' => $this->integer()->notNull(),
            'type' => $this->string(50)->notNull()->defaultValue('purchase'),
            'settings' => $this->text(),
            'position' => $this->string(20)->notNull()->defaultValue('bottom-left'),
            'displayDuration' => $this->integer()->notNull()->defaultValue(5),
            'delayBetween' => $this->integer()->notNull()->defaultValue(10),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
            'PRIMARY KEY([[id]])',
        ]);

        foreach ($existingNotifications as $notification) {
            $element = new NotificationElement();
            $element->title = $notification['name'] ?? 'Notification';
            $element->enabled = (bool)($notification['enabled'] ?? true);
            $element->type = $notification['type'] ?? 'purchase';
            $element->settings = json_decode($notification['settings'] ?? '[]', true) ?: [];
            $element->position = $notification['position'] ?? 'bottom-left';
            $element->displayDuration = (int)($notification['displayDuration'] ?? 5);
            $element->delayBetween = (int)($notification['delayBetween'] ?? 10);

            if (Craft::$app->getElements()->saveElement($element)) {
                $this->insert('{{%socialproof_notifications_temp}}', [
                    'id' => $element->id,
                    'type' => $element->type,
                    'settings' => json_encode($element->settings),
                    'position' => $element->position,
                    'displayDuration' => $element->displayDuration,
                    'delayBetween' => $element->delayBetween,
                    'dateCreated' => Db::prepareDateForDb($element->dateCreated),
                    'dateUpdated' => Db::prepareDateForDb($element->dateUpdated),
                    'uid' => $element->uid,
                ]);
            }
        }

        $this->dropTableIfExists('{{%socialproof_notifications}}');
        $this->renameTable('{{%socialproof_notifications_temp}}', '{{%socialproof_notifications}}');

        $this->addForeignKey(
            'fk_socialproof_notifications_id',
            '{{%socialproof_notifications}}',
            ['id'],
            '{{%elements}}',
            ['id'],
            'CASCADE',
            'CASCADE'
        );

        $this->createIndex(
            'idx_socialproof_notifications_type',
            '{{%socialproof_notifications}}',
            ['type']
        );

        // SET NULL — pre-migration impression rows may point at old IDs no longer in the new table
        $this->addForeignKey(
            'fk_socialproof_impressions_notificationId',
            '{{%socialproof_impressions}}',
            ['notificationId'],
            '{{%socialproof_notifications}}',
            ['id'],
            'SET NULL',
            'CASCADE'
        );

        return true;
    }

    public function safeDown(): bool
    {
        // Not reversible
        return false;
    }

    private function safeDropForeignKey(string $table, string $name): void
    {
        try {
            $this->dropForeignKey($name, $table);
        } catch (\Exception $e) {
            // FK may not exist
        }
    }

    private function safeDropIndex(string $table, string $name): void
    {
        try {
            $this->dropIndex($name, $table);
        } catch (\Exception $e) {
            // Index may not exist
        }
    }
}
