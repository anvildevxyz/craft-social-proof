<?php

namespace anvildev\socialproof\controllers;

use anvildev\socialproof\elements\PopupElement;
use anvildev\socialproof\helpers\Time;
use anvildev\socialproof\Plugin;
use anvildev\socialproof\popups\VisitorCookie;
use anvildev\socialproof\services\PopupService;
use Craft;
use craft\web\Controller;
use yii\web\Response;

class PopupApiController extends Controller
{
    /** @deprecated Use {@see VisitorCookie::NAME}. Kept as an alias for external Twig consumers. */
    public const VISITOR_COOKIE = VisitorCookie::NAME;
    /** @deprecated Use {@see VisitorCookie::TTL_SECONDS}. */
    public const VISITOR_COOKIE_TTL = VisitorCookie::TTL_SECONDS;

    public array|int|bool $allowAnonymous = true;

    public $enableCsrfValidation = false;

    public function actionCandidates(): Response
    {
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();
        $visitorId = $this->resolveVisitorId();
        $requestUrl = $request->getReferrer() ?: '/';

        $user = Craft::$app->getUser()->getIdentity();
        $contextElement = null;
        if ($requestUrl !== '/' && $requestUrl !== '') {
            $path = parse_url($requestUrl, PHP_URL_PATH) ?: '/';
            $path = ltrim($path, '/');
            if ($path !== '') {
                $contextElement = Craft::$app->getElements()->getElementByUri($path) ?: null;
            }
        }
        $popups = Plugin::getInstance()->arbitration->arbitrate(
            $this->loadEligiblePopupDescriptors(),
            $requestUrl,
            $visitorId,
            $user,
            $contextElement,
        );

        return $this->asJson([
            'candidates' => $popups,
            'visitorId' => $visitorId,
        ]);
    }

    public function actionEvent(): Response
    {
        $this->requirePostRequest();
        if (!$this->checkRateLimit()) {
            return $this->asJson(['ok' => false, 'error' => 'rate limit exceeded']);
        }
        // Do NOT require Accept: application/json — this endpoint is called via
        // navigator.sendBeacon() which cannot set an Accept header. The response
        // is still JSON (asJson below) for clients that read it, but we accept
        // beacon requests without a JSON Accept.

        $request = Craft::$app->getRequest();
        $popupId = (int) $request->getRequiredBodyParam('popupId');
        $eventType = $request->getRequiredBodyParam('eventType');
        $pageUrl = $request->getBodyParam('pageUrl');

        if (!in_array($eventType, PopupService::EVENT_TYPES, true)) {
            return $this->asJson(['ok' => false, 'error' => 'invalid eventType']);
        }

        $visitorId = $this->resolveVisitorId();
        $sessionId = Craft::$app->getSession()->getId();

        Plugin::getInstance()->popups->recordEvent($popupId, $visitorId, $eventType, $sessionId, $pageUrl);

        if ($eventType === PopupService::EVENT_CONVERT) {
            try {
                Plugin::getInstance()->attribution->recordConvert((int) $popupId, $visitorId, $sessionId);
            } catch (\Throwable $e) {
                Craft::warning('Popup attribution record failed: ' . $e->getMessage(), __METHOD__);
            }
        }

        $popup = PopupElement::find()->id((int) $popupId)->status(null)->one();
        Plugin::getInstance()->webhooks->dispatchForEvent(
            'popup.' . $eventType,
            [
                'popup' => $popup ? [
                    'id' => $popup->id,
                    'title' => $popup->title,
                    'layout' => $popup->layout,
                ] : ['id' => (int) $popupId],
                'visitor_id' => $visitorId,
                'session_id' => $sessionId,
                'page_url' => $pageUrl,
                'event_time' => Time::utcNow()->format(\DateTimeInterface::ATOM),
            ],
        );

        $fatigue = Plugin::getInstance()->fatigue;
        match ($eventType) {
            PopupService::EVENT_IMPRESSION => $fatigue->recordImpression($popupId, $visitorId),
            PopupService::EVENT_DISMISS => $fatigue->recordDismiss($popupId, $visitorId),
            PopupService::EVENT_CONVERT => $fatigue->recordConvert($popupId, $visitorId),
            default => null,
        };

        return $this->asJson(['ok' => true]);
    }

    /**
     * @phpstan-import-type PopupDescriptor from \anvildev\socialproof\services\ArbitrationService
     * @return list<PopupDescriptor>
     */
    private function loadEligiblePopupDescriptors(): array
    {
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        $popups = PopupElement::find()
            ->status(PopupElement::STATUS_ENABLED)
            ->siteId($siteId)
            ->all();

        $popupService = Plugin::getInstance()->popups;
        $descriptors = [];
        foreach ($popups as $popup) {
            $descriptors[] = [
                'id' => $popup->id,
                'priority' => $popup->priority,
                'layout' => $popup->layout,
                'trigger' => $popup->trigger,
                'targeting' => $popup->targeting,
                'fatigueRules' => $popup->fatigueRules,
                'html' => $popupService->renderLayout($popup),
            ];
        }
        return $descriptors;
    }

    private function checkRateLimit(): bool
    {
        $settings = Plugin::getInstance()->getSettings();
        $limit = (int) $settings->popupEventRateLimit;
        if ($limit <= 0) {
            return true;
        }
        $ip = Craft::$app->getRequest()->getUserIP() ?: 'unknown';
        $cache = Craft::$app->getCache();
        $key = 'sp_popup_event_' . sha1($ip);
        $count = (int) $cache->get($key);
        if ($count >= $limit) {
            return false;
        }
        $cache->set($key, $count + 1, 60);
        return true;
    }

    private function resolveVisitorId(): string
    {
        return VisitorCookie::resolve(Craft::$app->getRequest(), Craft::$app->getResponse());
    }
}
