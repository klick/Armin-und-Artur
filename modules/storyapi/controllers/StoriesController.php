<?php

namespace modules\storyapi\controllers;

use Craft;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use craft\web\ServiceUnavailableHttpException;
use modules\storyapi\Module;
use yii\base\InvalidArgumentException;
use yii\web\Response;

class StoriesController extends Controller
{
    protected array|bool|int $allowAnonymous = ['schema', 'reading'];
    public $enableCsrfValidation = false;

    public function actionSchema(): Response
    {
        $response = $this->asJson($this->stories()->getSchema());
        $response->headers->set('Content-Type', 'application/schema+json; charset=UTF-8');

        return $response;
    }

    public function actionReading(string $id): Response
    {
        $stories = $this->stories();
        $artifact = $stories->getArtifact($id);

        if (!$stories->isX402Enabled()) {
            throw new ServiceUnavailableHttpException('Story API payments are disabled; protected artefacts remain closed.');
        }

        $resourceUrl = UrlHelper::siteUrl("api/v1/stories/{$id}/reading.json");
        try {
            $paymentRequired = $stories->x402PaymentRequired($resourceUrl);
        } catch (\RuntimeException $exception) {
            throw new ServiceUnavailableHttpException('Story API payment configuration is incomplete.', 0, $exception);
        }

        $signature = Craft::$app->getRequest()->getHeaders()->get('PAYMENT-SIGNATURE');
        if (!$signature) {
            return $this->paymentRequired($paymentRequired);
        }

        try {
            $paymentPayload = $stories->decodePaymentSignature($signature);
            $settlement = $stories->verifyAndSettle($paymentPayload, $paymentRequired['accepts'][0]);
        } catch (InvalidArgumentException $exception) {
            $paymentRequired['error'] = $exception->getMessage();

            return $this->paymentRequired($paymentRequired);
        } catch (\RuntimeException $exception) {
            Craft::error($exception->getMessage(), __METHOD__);
            throw new ServiceUnavailableHttpException('The payment facilitator is unavailable.', 0, $exception);
        }

        $response = $this->asJson($artifact);
        $response->headers->set('PAYMENT-RESPONSE', base64_encode(json_encode($settlement, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)));

        return $response;
    }

    private function paymentRequired(array $payload): Response
    {
        $response = $this->asJson($payload);
        $response->statusCode = 402;
        $response->headers->set('PAYMENT-REQUIRED', base64_encode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)));

        return $response;
    }

    private function stories(): \modules\storyapi\services\StoryReadingService
    {
        /** @var Module $module */
        $module = Craft::$app->getModule('story-api');

        return $module->getStories();
    }
}
