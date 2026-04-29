<?php

namespace anvildev\socialproof\services;

/**
 * Pure rule evaluator — given a popup's targeting config and a request URL,
 * decides whether the popup is eligible for this request.
 *
 * No I/O. No framework deps. Fully unit-testable.
 */
class TargetingService
{
    /**
     * @param array<string, mixed> $targeting The popup's targeting config (JSON-decoded). Recognised keys:
     *     urlPatterns: array{include?: list<string>, exclude?: list<string>},
     *     loggedIn: bool,
     *     userGroups: list<string>,
     *     sections: list<string>,
     *     entryIds: list<int|string>
     * @param string $requestUrl The URL path (no scheme/host) to evaluate; query string and fragment stripped internally
     * @param object|null $user Current authenticated user, or null for anonymous. Must expose getGroups() returning objects with a public $handle property.
     * @param \craft\base\Element|null $contextElement Craft Element representing the current page's primary entry (null for template-only routes). Must expose ->id and ->getSection()->handle when provided.
     */
    public function matches(
        array $targeting,
        string $requestUrl,
        ?object $user = null,
        ?\craft\base\Element $contextElement = null,
    ): bool {
        $path = $this->stripQueryAndFragment($requestUrl);
        $urlPatterns = $targeting['urlPatterns'] ?? [];
        $include = $urlPatterns['include'] ?? [];
        $exclude = $urlPatterns['exclude'] ?? [];

        foreach ($exclude as $pattern) {
            if ($this->globMatch($pattern, $path)) {
                return false;
            }
        }

        if (!empty($include)) {
            $matched = false;
            foreach ($include as $pattern) {
                if ($this->globMatch($pattern, $path)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                return false;
            }
        }

        if (array_key_exists('loggedIn', $targeting)) {
            $isLoggedIn = $user !== null;
            if ((bool) $targeting['loggedIn'] !== $isLoggedIn) {
                return false;
            }
        }

        if (!empty($targeting['userGroups']) && is_array($targeting['userGroups'])) {
            if ($user === null) {
                return false;
            }
            $userGroupHandles = array_map(static fn ($g) => $g->handle, $user->getGroups());
            $overlap = array_intersect($targeting['userGroups'], $userGroupHandles);
            if (empty($overlap)) {
                return false;
            }
        }

        if (!empty($targeting['sections']) && is_array($targeting['sections'])) {
            if ($contextElement === null) {
                return false;
            }
            $section = method_exists($contextElement, 'getSection') ? $contextElement->getSection() : null;
            $handle = $section?->handle ?? null;
            if ($handle === null || !in_array($handle, $targeting['sections'], true)) {
                return false;
            }
        }

        if (!empty($targeting['entryIds']) && is_array($targeting['entryIds'])) {
            if ($contextElement === null) {
                return false;
            }
            if (!in_array((int) $contextElement->id, array_map('intval', $targeting['entryIds']), true)) {
                return false;
            }
        }

        return true;
    }

    private function stripQueryAndFragment(string $url): string
    {
        $cutoff = strcspn($url, '?#');
        return substr($url, 0, $cutoff);
    }

    /**
     * Glob match: * is any-chars wildcard. Other regex chars are escaped.
     * Uses === 1 so a regex compilation error (false) is not silently treated as non-match.
     */
    private function globMatch(string $pattern, string $subject): bool
    {
        $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#';
        return preg_match($regex, $subject) === 1;
    }
}
