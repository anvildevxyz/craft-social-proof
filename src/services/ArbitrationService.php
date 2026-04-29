<?php

namespace anvildev\socialproof\services;

/**
 * Composes Targeting + Fatigue + priority ordering.
 *
 * Input: array of popup descriptors (plain arrays).
 * Output: ordered subset of eligible popups for this request.
 *
 * Pure — no I/O of its own. Delegates to injected services.
 *
 * @phpstan-type PopupDescriptor array{
 *     id: int,
 *     priority: int,
 *     layout: string,
 *     trigger: array<string, mixed>|string,
 *     targeting: array<string, mixed>|string,
 *     fatigueRules: array<string, mixed>|string,
 *     html: string,
 * }
 */
class ArbitrationService
{
    public function __construct(
        private TargetingService $targeting,
        private FatigueService $fatigue,
    ) {
    }

    /**
     * @param list<PopupDescriptor> $popups
     * @param object|null $user Current authenticated user (nullable). Passed through to TargetingService.
     * @param \craft\base\Element|null $contextElement Current page's primary element (nullable).
     * @return list<PopupDescriptor>
     */
    public function arbitrate(
        array $popups,
        string $requestUrl,
        string $visitorId,
        ?object $user = null,
        ?\craft\base\Element $contextElement = null,
    ): array {
        $bypassFatigue = $this->shouldBypassFatigue($user);

        $eligible = [];
        foreach ($popups as $popup) {
            if (!$this->targeting->matches($popup['targeting'] ?? [], $requestUrl, $user, $contextElement)) {
                continue;
            }
            if (!$bypassFatigue && $this->fatigue->isFatigued(
                (int) $popup['id'],
                $visitorId,
                $popup['fatigueRules'] ?? [],
            )) {
                continue;
            }
            $eligible[] = $popup;
        }

        usort($eligible, static fn ($a, $b) => ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0));

        return $eligible;
    }

    /**
     * Demo mode bypass: if the plugin setting is on AND the current user has the
     * manage-popups permission, skip fatigue filtering so authors can QA popups on
     * the live site regardless of shown/dismiss counts.
     */
    private function shouldBypassFatigue(?object $user): bool
    {
        if ($user === null) {
            return false;
        }
        // Unit tests may call arbitrate() without Plugin bootstrap. Guard via class_exists.
        if (!class_exists(\anvildev\socialproof\Plugin::class, false)) {
            return false;
        }
        $settings = \anvildev\socialproof\Plugin::getInstance()?->getSettings();
        if (!$settings || !$settings->popupDemoMode) {
            return false;
        }
        return $user->can(\anvildev\socialproof\Plugin::PERMISSION_MANAGE_POPUPS);
    }
}
