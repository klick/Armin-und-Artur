<?php

namespace modules\storyapi\controllers;

use Craft;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use craft\web\ServiceUnavailableHttpException;
use modules\storyapi\Module;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Human-operated browser test page for the local x402/Base Sepolia pilot.
 *
 * The route is registered only in DEV_MODE and the action repeats that check
 * as defense in depth. No wallet credentials are ever sent to Craft.
 */
class BrowserTestController extends Controller
{
    protected array|bool|int $allowAnonymous = ['index'];
    public $enableCsrfValidation = false;

    public function actionIndex(): Response
    {
        if (!Craft::$app->getConfig()->getGeneral()->devMode) {
            throw new NotFoundHttpException();
        }

        $endpoint = '/api/v1/stories/rotkaeppchen/reading.json';
        try {
            $paymentRequired = $this->stories()->x402PaymentRequired(UrlHelper::siteUrl(ltrim($endpoint, '/')));
        } catch (\RuntimeException $exception) {
            throw new ServiceUnavailableHttpException('The local x402 browser test requires a valid test recipient configuration.', 0, $exception);
        }

        return $this->renderTemplate('story-api/x402-browser-test', [
            'endpoint' => $endpoint,
            'expectedPayment' => $paymentRequired['accepts'][0],
        ]);
    }

    private function stories(): \modules\storyapi\services\StoryReadingService
    {
        /** @var Module $module */
        $module = Craft::$app->getModule('story-api');

        return $module->getStories();
    }
}
