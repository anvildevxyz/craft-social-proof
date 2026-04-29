<?php

namespace anvildev\socialproof\tests\unit\services;

use anvildev\socialproof\services\PopupService;
use craft\web\View;
use PHPUnit\Framework\TestCase;

/**
 * Covers PopupService::resolveTemplate() — the pure decision function that
 * maps (layout, customTemplate) to (templatePath, templateMode).
 *
 * The `custom` layout is subtle: author templates live in the host project's
 * templates/ directory (SITE mode), while built-ins live under the plugin's
 * CP templates. Getting the mode wrong makes custom popups render as '' and
 * only surface the failure in web.log.
 */
class PopupServiceTest extends TestCase
{
    public function testCustomLayoutWithAuthorTemplateUsesSiteMode(): void
    {
        [$template, $mode] = PopupService::resolveTemplate('custom', '_popups/fm-custom');

        $this->assertSame('_popups/fm-custom', $template);
        $this->assertSame(View::TEMPLATE_MODE_SITE, $mode);
    }

    public function testCustomLayoutWithoutTemplatePathFallsBackToAnnouncementInCpMode(): void
    {
        foreach ([null, ''] as $empty) {
            [$template, $mode] = PopupService::resolveTemplate('custom', $empty);
            $this->assertSame('social-proof/popups/_layouts/announcement', $template);
            $this->assertSame(View::TEMPLATE_MODE_CP, $mode);
        }
    }

    /**
     * @dataProvider builtInLayouts
     */
    public function testBuiltInLayoutsUseCpMode(string $layout): void
    {
        [$template, $mode] = PopupService::resolveTemplate($layout, null);

        $this->assertSame("social-proof/popups/_layouts/{$layout}", $template);
        $this->assertSame(View::TEMPLATE_MODE_CP, $mode);
    }

    public function testBuiltInLayoutIgnoresStrayCustomTemplatePath(): void
    {
        // A built-in layout should never pick up a stray customTemplate value —
        // only `custom` triggers the site-mode branch.
        [$template, $mode] = PopupService::resolveTemplate('announcement', '_popups/should-be-ignored');

        $this->assertSame('social-proof/popups/_layouts/announcement', $template);
        $this->assertSame(View::TEMPLATE_MODE_CP, $mode);
    }

    public static function builtInLayouts(): array
    {
        return [
            ['announcement'],
            ['newsletter'],
            ['discount'],
            ['bottom-bar'],
        ];
    }
}
