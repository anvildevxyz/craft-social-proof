<?php

namespace anvildev\socialproof\variables;

use anvildev\socialproof\assets\FrontendAsset;
use anvildev\socialproof\assets\PopupFrontendAsset;
use anvildev\socialproof\controllers\PopupApiController;
use anvildev\socialproof\elements\PopupElement;
use anvildev\socialproof\Plugin;
use anvildev\socialproof\popups\VisitorCookie;
use Craft;
use craft\helpers\Json;
use craft\web\View;
use Twig\Markup;

class SocialProofVariable
{
    /**
     * Outputs the notification container and registers the JS/CSS assets.
     *
     * Usage in Twig:
     *   {{ craft.socialProof.init() }}
     *   {{ craft.socialProof.init({ position: 'bottom-right' }) }}
     *
     * @param array<string, mixed> $options
     */
    public function init(array $options = []): Markup
    {
        $settings = Plugin::$plugin->getSettings();

        if (!$settings->enabled) {
            return new Markup('', 'utf-8');
        }

        $view = Craft::$app->getView();

        $view->registerAssetBundle(FrontendAsset::class);

        $config = [
            'csrfToken' => Craft::$app->getRequest()->getCsrfToken(),
            'endpoint' => '/actions/social-proof/api/get-notifications',
            'trackEndpoint' => '/actions/social-proof/api/track-event',
            'heartbeatEndpoint' => '/actions/social-proof/api/heartbeat',
        ];

        // Allow-list only display options from templates; never let CSRF/endpoint be overridden
        $allowedKeys = ['position', 'displayDuration', 'delayBetween', 'animationIn', 'animationOut'];
        $config = array_merge($config, array_intersect_key($options, array_flip($allowedKeys)));

        $configJson = json_encode($config, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $view->registerJs("window.socialProofConfig = {$configJson};", View::POS_HEAD);

        $position = $options['position'] ?? $settings->position;
        $containerHtml = '<div id="social-proof-container" class="social-proof-container social-proof-' . htmlspecialchars($position) . '"></div>';

        return new Markup($containerHtml, 'utf-8');
    }

    public function isEnabled(): bool
    {
        return Plugin::$plugin->getSettings()->enabled;
    }

    /**
     * @return array{enabled: bool, position: string, displayDuration: int, delayBetween: int, purchaseEnabled: bool, viewersEnabled: bool, stockEnabled: bool}
     */
    public function getSettings(): array
    {
        $settings = Plugin::$plugin->getSettings();

        return [
            'enabled' => $settings->enabled,
            'position' => $settings->position,
            'displayDuration' => $settings->displayDuration,
            'delayBetween' => $settings->delayBetween,
            'purchaseEnabled' => $settings->purchaseEnabled,
            'viewersEnabled' => $settings->viewersEnabled,
            'stockEnabled' => $settings->stockEnabled,
        ];
    }

    /**
     * Usage:
     *   {{ craft.socialProof.viewerCount() }}
     *   {{ craft.socialProof.viewerCount('/products/widget') }}
     */
    public function viewerCount(?string $url = null): int
    {
        if (!Plugin::$plugin->getSettings()->viewersEnabled) {
            return 0;
        }

        return Plugin::$plugin->notifications->getViewerCount($url);
    }

    public function recentPurchaseCount(int $hours = 24): int
    {
        return Plugin::$plugin->commerce->getRecentOrderCount($hours);
    }

    public function isCommerceInstalled(): bool
    {
        return Plugin::isCommerceInstalled();
    }

    /**
     * @deprecated Use {@see VisitorCookie::NAME} — alias for external Twig/extension consumers.
     */
    public const POPUP_VISITOR_COOKIE = VisitorCookie::NAME;
    /** @deprecated Use {@see VisitorCookie::TTL_SECONDS}. */
    public const POPUP_VISITOR_COOKIE_TTL = VisitorCookie::TTL_SECONDS;

    /**
     * Emit the inline popup candidate JSON + register the popup asset bundle.
     * Call from your Twig layout just before </body>:
     *
     *     {{ craft.socialProof.initPopups() }}
     */
    public function initPopups(): Markup
    {
        $view = Craft::$app->getView();
        $view->registerAssetBundle(PopupFrontendAsset::class, View::POS_END);

        $request = Craft::$app->getRequest();
        $requestUrl = $request->getUrl();
        $visitorId = $this->resolvePopupVisitorId();

        $descriptors = $this->buildPopupDescriptors();
        $user = Craft::$app->getUser()->getIdentity();
        $contextElement = Craft::$app->getUrlManager()->getMatchedElement() ?: null;
        $candidates = Plugin::getInstance()->arbitration->arbitrate(
            $descriptors,
            $requestUrl,
            $visitorId,
            $user,
            $contextElement,
        );

        $payload = Json::encode([
            'visitorId' => $visitorId,
            'candidates' => $candidates,
            'eventEndpoint' => '/social-proof/popups/api/event',
        ]);

        return new Markup(
            '<script id="social-proof-popups-data" type="application/json">' . $payload . '</script>',
            'UTF-8',
        );
    }

    /**
     * Async variant of initPopups() — emits no inline candidate JSON; instead,
     * loads an asset bundle that fetches candidates from /api/candidates after
     * DOMContentLoaded. Use on full-page-cached sites where inline payloads
     * would be stale.
     */
    public function initPopupsAsync(): Markup
    {
        $view = Craft::$app->getView();
        $view->registerAssetBundle(\anvildev\socialproof\assets\PopupAsyncFrontendAsset::class, View::POS_END);
        $html = '<script>window.smartPopupsEventEndpoint = "/social-proof/popups/api/event";</script>';
        return new Markup($html, 'UTF-8');
    }

    /**
     * Render a popup's layout HTML for preview iframe use.
     * Public entry so the preview template can access it via Twig.
     */
    public function renderPopupForPreview(PopupElement $popup): string
    {
        return Plugin::getInstance()->popups->renderLayout($popup);
    }

    /**
     * @phpstan-import-type PopupDescriptor from \anvildev\socialproof\services\ArbitrationService
     * @return list<PopupDescriptor>
     */
    private function buildPopupDescriptors(): array
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

    private function resolvePopupVisitorId(): string
    {
        return VisitorCookie::resolve(Craft::$app->getRequest(), Craft::$app->getResponse());
    }
}
