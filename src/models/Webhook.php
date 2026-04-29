<?php

namespace anvildev\socialproof\models;

use anvildev\socialproof\enums\PopupEvent;

/**
 * @phpstan-type WebhookSubscription array{id: string, label: string, url: string, events: list<string>, secret: string, enabled?: bool}
 */
class Webhook
{
    /** @var list<string> popup.{impression,click,dismiss,convert} */
    public const EVENTS = ['popup.impression', 'popup.click', 'popup.dismiss', 'popup.convert'];

    /**
     * Validator for a single webhook subscription row. Returns an array of
     * human-readable errors; empty array means the row is valid.
     *
     * @param array<string, mixed> $row
     * @return string[]
     */
    public static function validateRow(array $row): array
    {
        $errors = [];
        foreach (['id', 'label', 'url', 'events', 'secret'] as $key) {
            if (!array_key_exists($key, $row)) {
                $errors[] = "Missing field: {$key}";
            }
        }
        if (isset($row['url']) && !preg_match('#^https?://#i', (string) $row['url'])) {
            $errors[] = 'URL must be http(s).';
        }
        if (array_key_exists('events', $row)) {
            if (!is_array($row['events'])) {
                $errors[] = 'Events must be an array.';
            } elseif (empty($row['events'])) {
                $errors[] = 'Select at least one event.';
            } else {
                foreach ($row['events'] as $e) {
                    if (!in_array($e, self::EVENTS, true)) {
                        $errors[] = "Unknown event: {$e}";
                    }
                }
            }
        }
        if (isset($row['secret']) && !preg_match('/^[a-f0-9]{32,}$/i', (string) $row['secret'])) {
            $errors[] = 'Secret must be at least 32 hex chars.';
        }
        return $errors;
    }
}
