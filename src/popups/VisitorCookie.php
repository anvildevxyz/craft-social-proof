<?php

namespace anvildev\socialproof\popups;

use Craft;
use craft\helpers\StringHelper;
use yii\web\Cookie;
use yii\web\Request;
use yii\web\Response;

final class VisitorCookie
{
    public const NAME = 'social_proof_popup_visitor';
    public const TTL_SECONDS = 365 * 24 * 3600;

    /**
     * Read the visitor id cookie or set a fresh one. Returns the id.
     */
    public static function resolve(Request $request, Response $response): string
    {
        $cookie = $request->getCookies()->get(self::NAME);
        if ($cookie !== null && $cookie->value !== '' && preg_match('/^[a-z0-9-]{30,40}$/', $cookie->value)) {
            return $cookie->value;
        }

        $visitorId = StringHelper::UUID();
        $response->getCookies()->add(new Cookie([
            'name' => self::NAME,
            'value' => $visitorId,
            'httpOnly' => true,
            'expire' => time() + self::TTL_SECONDS,
            'sameSite' => Cookie::SAME_SITE_LAX,
        ]));
        return $visitorId;
    }
}
