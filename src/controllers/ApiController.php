<?php

namespace anvildev\socialproof\controllers;

use anvildev\socialproof\elements\NotificationElement;
use anvildev\socialproof\models\Settings;
use anvildev\socialproof\Plugin;
use Craft;
use craft\web\Controller;
use yii\web\Response;
use yii\web\TooManyRequestsHttpException;

class ApiController extends Controller
{
    private const TRACK_RATE_LIMIT = 60;
    private const HEARTBEAT_RATE_LIMIT = 4;

    protected array|bool|int $allowAnonymous = [
        'get-notifications',
        'track-event',
        'heartbeat',
    ];

    public $enableCsrfValidation = true;

    public function actionGetNotifications(): Response
    {
        $request = Craft::$app->getRequest();
        $sessionId = $this->_getOrCreateSessionId();
        $pageUrl = $request->getParam('url');

        $settings = Plugin::$plugin->getSettings();

        if (!$settings->enabled) {
            return $this->asJson([
                'success' => true,
                'notifications' => [],
                'settings' => $this->_getPublicSettings($settings),
            ]);
        }

        $session = Craft::$app->getSession();
        $shownIds = $session->get('socialproof_shown_ids', []);

        $siteId = Craft::$app->getSites()->getCurrentSite()->id;

        $notifications = Plugin::$plugin->notifications->getNotificationsForVisitor($sessionId, $pageUrl, $shownIds, $siteId);

        $newIds = array_filter(array_map(fn($n) => $n->id, $notifications));
        if (!empty($newIds)) {
            $session->set('socialproof_shown_ids', array_values(array_unique(array_merge($shownIds, $newIds))));
        }

        $formattedNotifications = array_map(
            fn($notification) => $notification->toFrontendArray(),
            $notifications
        );

        return $this->asJson([
            'success' => true,
            'notifications' => $formattedNotifications,
            'settings' => $this->_getPublicSettings($settings),
        ]);
    }

    public function actionTrackEvent(): Response
    {
        $this->requirePostRequest();
        $this->_enforceRateLimit('sp_track', self::TRACK_RATE_LIMIT);

        $request = Craft::$app->getRequest();
        $notificationId = $request->getBodyParam('notificationId');
        $eventType = $request->getBodyParam('eventType');
        $pageUrl = $request->getBodyParam('pageUrl');
        $metadata = $request->getBodyParam('metadata', []);

        if (!in_array($eventType, ['impression', 'click', 'dismiss', 'heartbeat'], true)) {
            return $this->asJson([
                'success' => false,
                'error' => 'Invalid event type',
            ]);
        }

        $sessionId = $this->_getOrCreateSessionId();

        $safeMetadata = is_array($metadata) ? $metadata : [];
        if (strlen(json_encode($safeMetadata)) > 2048) {
            $safeMetadata = [];
        }
        $pageUrl = $pageUrl ? mb_substr((string)$pageUrl, 0, 500) : null;

        // Notification.id can be a real NotificationElement id (custom notifications)
        // or a synthetic id (socialproof_orders.id for purchases, hardcoded ranges for
        // demo data). Only the first case is a valid FK target; otherwise pass null.
        $notificationId = $notificationId ? (int)$notificationId : null;
        if ($notificationId !== null && !NotificationElement::find()->id($notificationId)->status(null)->exists()) {
            $notificationId = null;
        }

        $success = Plugin::$plugin->tracking->trackEvent(
            $notificationId,
            $sessionId,
            $eventType,
            $pageUrl,
            $safeMetadata
        );

        return $this->asJson([
            'success' => $success,
        ]);
    }

    public function actionHeartbeat(): Response
    {
        $this->requirePostRequest();
        $this->_enforceRateLimit('sp_heartbeat', self::HEARTBEAT_RATE_LIMIT);

        $request = Craft::$app->getRequest();
        $pageUrl = $request->getBodyParam('pageUrl');
        $sessionId = $this->_getOrCreateSessionId();

        Plugin::$plugin->tracking->trackEvent(
            null,
            $sessionId,
            'heartbeat',
            $pageUrl
        );

        return $this->asJson([
            'success' => true,
        ]);
    }

    /**
     * @throws TooManyRequestsHttpException
     */
    private function _enforceRateLimit(string $action, int $maxPerMinute): void
    {
        $ip = Craft::$app->getRequest()->getUserIP() ?? 'unknown';
        $cacheKey = "{$action}_{$ip}";
        $cache = Craft::$app->getCache();

        $count = (int)$cache->get($cacheKey);

        if ($count >= $maxPerMinute) {
            throw new TooManyRequestsHttpException('Rate limit exceeded.');
        }

        $cache->set($cacheKey, $count + 1, 60);
    }

    private function _getOrCreateSessionId(): string
    {
        $session = Craft::$app->getSession();
        $sessionKey = 'socialproof_session_id';

        $sessionId = $session->get($sessionKey);

        if (!$sessionId) {
            $sessionId = Craft::$app->getSecurity()->generateRandomString(32);
            $session->set($sessionKey, $sessionId);
        }

        return $sessionId;
    }

    /**
     * @return array{position: string, displayDuration: int, delayBetween: int, animationIn: string, animationOut: string, showProductImage: bool, showDismissButton: bool}
     */
    private function _getPublicSettings(Settings $settings): array
    {
        return [
            'position' => $settings->position,
            'displayDuration' => $settings->displayDuration,
            'delayBetween' => $settings->delayBetween,
            'animationIn' => $settings->animationIn,
            'animationOut' => $settings->animationOut,
            'showProductImage' => $settings->showProductImage,
            'showDismissButton' => $settings->showDismissButton,
        ];
    }
}
