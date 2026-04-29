<?php

namespace anvildev\socialproof\controllers;

use anvildev\socialproof\elements\PopupElement;
use anvildev\socialproof\Plugin;
use Craft;
use craft\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class PopupsController extends Controller
{
    public function actionIndex(): Response
    {
        $this->requirePermission(Plugin::PERMISSION_MANAGE_POPUPS);
        return $this->renderTemplate('social-proof/popups/index');
    }

    public function actionEdit(?int $popupId = null, ?string $site = null): Response
    {
        $this->requirePermission(Plugin::PERMISSION_MANAGE_POPUPS);
        \Craft::$app->getView()->registerAssetBundle(\anvildev\socialproof\assets\PopupCpAsset::class);

        $sitesService = Craft::$app->getSites();
        $siteModel = $site ? $sitesService->getSiteByHandle($site) : $sitesService->getCurrentSite();

        if ($popupId) {
            $popup = PopupElement::find()->id($popupId)->siteId($siteModel->id)->one();
            if (!$popup) {
                throw new NotFoundHttpException('Popup not found');
            }
        } else {
            $popup = new PopupElement();
            $popup->siteId = $siteModel->id;
        }

        $sectionOptions = [];
        foreach (Craft::$app->getEntries()->getAllSections() as $section) {
            $sectionOptions[] = ['label' => $section->name, 'value' => $section->handle];
        }

        $groupOptions = [];
        foreach (Craft::$app->getUserGroups()->getAllGroups() as $group) {
            $groupOptions[] = ['label' => $group->name, 'value' => $group->handle];
        }

        $selectedEntries = [];
        $entryIds = $popup->targeting['entryIds'] ?? [];
        if (!empty($entryIds)) {
            $selectedEntries = \craft\elements\Entry::find()->id($entryIds)->all();
        }

        $currentLoggedIn = 'either';
        if (isset($popup->targeting['loggedIn'])) {
            $currentLoggedIn = $popup->targeting['loggedIn'] ? 'yes' : 'no';
        }

        return $this->renderTemplate('social-proof/popups/_edit', [
            'popup' => $popup,
            'title' => $popup->id ? $popup->title : Craft::t('social-proof', 'New popup'),
            'triggerOptions' => [
                ['label' => Craft::t('social-proof', 'Time on page'), 'value' => 'time-on-page'],
                ['label' => Craft::t('social-proof', 'Exit intent'), 'value' => 'exit-intent'],
            ],
            'sectionOptions' => $sectionOptions,
            'groupOptions' => $groupOptions,
            'selectedEntries' => $selectedEntries,
            'currentLoggedIn' => $currentLoggedIn,
        ]);
    }

    public function actionPreview(int $popupId): Response
    {
        $this->requirePermission(Plugin::PERMISSION_MANAGE_POPUPS);

        $popup = PopupElement::find()->id($popupId)->one();
        if (!$popup) {
            throw new NotFoundHttpException('Popup not found');
        }

        return $this->renderTemplate('social-proof/popups/_preview', [
            'popup' => $popup,
        ]);
    }

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission(Plugin::PERMISSION_MANAGE_POPUPS);

        $request = Craft::$app->getRequest();
        $popupId = $request->getBodyParam('popupId');

        if ($popupId) {
            $popup = PopupElement::find()->id($popupId)->one();
            if (!$popup) {
                throw new NotFoundHttpException();
            }
        } else {
            $popup = new PopupElement();
        }

        $popup->title = $request->getRequiredBodyParam('title');
        $popup->enabled = (bool) $request->getBodyParam('enabled', true);
        $popup->layout = $request->getBodyParam('layout', 'announcement');
        $popup->priority = (int) $request->getBodyParam('priority', 50);

        $triggerType = $request->getBodyParam('triggerType');
        $popup->trigger = match ($triggerType) {
            'time-on-page' => ['type' => 'time-on-page', 'seconds' => (int) $request->getBodyParam('triggerSeconds', 10)],
            'exit-intent' => ['type' => 'exit-intent'],
            'scroll-depth' => ['type' => 'scroll-depth', 'percent' => (int) $request->getBodyParam('triggerScrollPercent', 50)],
            'element-click' => ['type' => 'element-click', 'selector' => (string) $request->getBodyParam('triggerSelector', '')],
            'page-match' => ['type' => 'page-match'],
            'user-state' => ['type' => 'user-state'],
            default => ['type' => 'time-on-page', 'seconds' => 10],
        };

        $popup->layoutSettings = [
            'headline' => $request->getBodyParam('headline'),
            'body' => $request->getBodyParam('body'),
            'ctaText' => $request->getBodyParam('ctaText'),
            'ctaUrl' => $request->getBodyParam('ctaUrl'),
            'emailPlaceholder' => $request->getBodyParam('emailPlaceholder'),
            'submitText' => $request->getBodyParam('submitText'),
            'webhookUrl' => $request->getBodyParam('webhookUrl'),
            'successText' => $request->getBodyParam('successText'),
            'code' => $request->getBodyParam('code'),
            'copyButtonText' => $request->getBodyParam('copyButtonText'),
            'enableCopy' => (bool) $request->getBodyParam('enableCopy', true),
        ];

        $targeting = [];

        $includeRaw = (string) $request->getBodyParam('includeUrlPatterns', '');
        $excludeRaw = (string) $request->getBodyParam('excludeUrlPatterns', '');
        $include = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $includeRaw) ?: []), 'strlen'));
        $exclude = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $excludeRaw) ?: []), 'strlen'));
        if ($include || $exclude) {
            $urlPatterns = [];
            if ($include) { $urlPatterns['include'] = $include; }
            if ($exclude) { $urlPatterns['exclude'] = $exclude; }
            $targeting['urlPatterns'] = $urlPatterns;
        }

        $sections = (array) $request->getBodyParam('sections', []);
        $sections = array_values(array_filter($sections, 'strlen'));
        if ($sections) { $targeting['sections'] = $sections; }

        $entryIds = (array) $request->getBodyParam('entryIds', []);
        $entryIds = array_values(array_map('intval', array_filter($entryIds, 'is_numeric')));
        if ($entryIds) { $targeting['entryIds'] = $entryIds; }

        $userGroups = (array) $request->getBodyParam('userGroups', []);
        $userGroups = array_values(array_filter($userGroups, 'strlen'));
        if ($userGroups) { $targeting['userGroups'] = $userGroups; }

        $loggedIn = $request->getBodyParam('loggedIn', 'either');
        if ($loggedIn === 'yes') { $targeting['loggedIn'] = true; }
        elseif ($loggedIn === 'no') { $targeting['loggedIn'] = false; }

        $popup->targeting = $targeting;
        $popup->fatigueRules = [
            'maxPerVisitor' => (int) $request->getBodyParam('maxPerVisitor', 3),
            'minHoursBetween' => (int) $request->getBodyParam('minHoursBetween', 24),
            'stopAfterDismiss' => (bool) $request->getBodyParam('stopAfterDismiss', true),
            'stopAfterConvert' => (bool) $request->getBodyParam('stopAfterConvert', true),
        ];

        if (!Craft::$app->getElements()->saveElement($popup)) {
            Craft::$app->getSession()->setError(Craft::t('social-proof', 'Couldn\'t save popup.'));
            Craft::$app->getUrlManager()->setRouteParams(['popup' => $popup]);
            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('social-proof', 'Popup saved.'));
        return $this->redirectToPostedUrl($popup);
    }

    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission(Plugin::PERMISSION_MANAGE_POPUPS);

        $popupId = Craft::$app->getRequest()->getRequiredBodyParam('popupId');
        $popup = PopupElement::find()->id($popupId)->one();
        if (!$popup) {
            throw new NotFoundHttpException();
        }
        Craft::$app->getElements()->deleteElement($popup);
        return $this->redirect('social-proof/popups');
    }
}
