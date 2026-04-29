<?php

namespace anvildev\socialproof\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;

/**
 * Adds multi-site support by moving per-site settings to a new table.
 *
 * - Creates `socialproof_notification_sites` table
 * - Migrates existing `settings` data from the notifications table into one row per site
 * - Drops the `settings` column from the notifications table
 */
class m250212_000000_add_multisite_support extends Migration
{
    public function safeUp(): bool
    {
        $this->createTable('{{%socialproof_notification_sites}}', [
            'id' => $this->primaryKey(),
            'notificationId' => $this->integer()->notNull(),
            'siteId' => $this->integer()->notNull(),
            'settings' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Unique: one settings row per notification per site
        $this->createIndex(
            'idx_socialproof_notification_sites_notificationId_siteId',
            '{{%socialproof_notification_sites}}',
            ['notificationId', 'siteId'],
            true
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

        // Migrate existing settings → one row per site per notification
        $allSites = Craft::$app->getSites()->getAllSites();
        $notifications = (new Query())
            ->select(['id', 'settings'])
            ->from('{{%socialproof_notifications}}')
            ->all();

        foreach ($notifications as $notification) {
            $settings = $notification['settings'];

            foreach ($allSites as $site) {
                $this->insert('{{%socialproof_notification_sites}}', [
                    'notificationId' => $notification['id'],
                    'siteId' => $site->id,
                    'settings' => $settings,
                    'dateCreated' => new \yii\db\Expression('NOW()'),
                    'dateUpdated' => new \yii\db\Expression('NOW()'),
                ]);
            }
        }

        $this->dropColumn('{{%socialproof_notifications}}', 'settings');

        return true;
    }

    public function safeDown(): bool
    {
        $this->addColumn(
            '{{%socialproof_notifications}}',
            'settings',
            $this->text()->after('type')
        );

        // Restore settings from the primary site's row
        $primarySite = Craft::$app->getSites()->getPrimarySite();
        $siteSettings = (new Query())
            ->select(['notificationId', 'settings'])
            ->from('{{%socialproof_notification_sites}}')
            ->where(['siteId' => $primarySite->id])
            ->all();

        foreach ($siteSettings as $row) {
            $this->update(
                '{{%socialproof_notifications}}',
                ['settings' => $row['settings']],
                ['id' => $row['notificationId']]
            );
        }

        $this->dropTableIfExists('{{%socialproof_notification_sites}}');

        return true;
    }
}
