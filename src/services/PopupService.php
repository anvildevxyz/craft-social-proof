<?php

namespace anvildev\socialproof\services;

use anvildev\socialproof\elements\PopupElement;
use anvildev\socialproof\enums\PopupEvent;
use anvildev\socialproof\records\PopupImpressionRecord;
use Craft;
use craft\web\View;

/**
 * Application-layer service for popups — renders layout Twig templates to HTML
 * and records events to the socialproof_popup_impressions table.
 */
class PopupService
{
    public const EVENT_IMPRESSION = PopupEvent::Impression->value;
    public const EVENT_CLICK = PopupEvent::Click->value;
    public const EVENT_DISMISS = PopupEvent::Dismiss->value;
    public const EVENT_CONVERT = PopupEvent::Convert->value;

    /** @var list<string> */
    public const EVENT_TYPES = ['impression', 'click', 'dismiss', 'convert'];

    /**
     * Resolve a popup's layout + customTemplate to the [templatePath, templateMode] pair.
     *
     * Built-in layouts live under the plugin's src/templates/popups/_layouts/
     * (CP template mode). The `custom` layout renders a site-author-provided
     * Twig template from the host project's templates/ directory (SITE mode).
     * If `custom` is selected but no customTemplate path is set, we fall back
     * to the built-in `announcement` layout so the popup still renders.
     *
     * Pure function — extracted for direct unit testing without a Craft runtime.
     *
     * @return array{0: string, 1: string}
     */
    public static function resolveTemplate(string $layout, ?string $customTemplate): array
    {
        if ($layout === 'custom' && !empty($customTemplate)) {
            return [$customTemplate, View::TEMPLATE_MODE_SITE];
        }

        $fallback = $layout === 'custom' ? 'announcement' : $layout;
        return ['social-proof/popups/_layouts/' . $fallback, View::TEMPLATE_MODE_CP];
    }

    public function renderLayout(PopupElement $popup): string
    {
        [$template, $mode] = self::resolveTemplate($popup->layout, $popup->customTemplate);

        return Craft::$app->getView()->renderTemplate(
            $template,
            ['popup' => $popup, 'settings' => $popup->layoutSettings],
            $mode,
        );
    }

    public function recordEvent(
        int $popupId,
        string $visitorId,
        string $eventType,
        ?string $sessionId = null,
        ?string $pageUrl = null,
    ): void {
        $record = new PopupImpressionRecord();
        $record->popupId = $popupId;
        $record->visitorId = $visitorId;
        $record->sessionId = $sessionId;
        $record->eventType = $eventType;
        $record->pageUrl = $pageUrl;
        $record->save(false);
    }
}
