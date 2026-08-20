<?php

namespace modules\storyapi\controllers;

use Craft;
use craft\web\Controller;
use modules\storyapi\Module;
use yii\base\InvalidArgumentException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/** DEV-only, human-operated single-narrator preview page. */
class PreviewController extends Controller
{
    protected array|bool|int $allowAnonymous = ['index', 'render'];

    public function actionIndex(): Response
    {
        $this->requireDevMode();
        $request = Craft::$app->getRequest();

        return $this->renderTemplate('story-api/reading-preview', [
            'stories' => $this->preview()->getStoryIndex(),
            'configuration' => $this->preview()->getConfiguration(),
            'renderEndpoint' => '/__story-api/reading-preview/render',
            'csrfParam' => $request->csrfParam,
            'csrfToken' => $request->getCsrfToken(),
        ]);
    }

    public function actionRender(): Response
    {
        $this->requireDevMode();
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $storyId = trim((string)$request->getBodyParam('storyId'));
        $sceneId = trim((string)$request->getBodyParam('sceneId'));
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $storyId) !== 1 || preg_match('/^s[0-9]{2,}$/', $sceneId) !== 1) {
            return $this->jsonError('Invalid story or scene selection.', 400);
        }

        try {
            $result = $this->preview()->render($storyId, $sceneId);
        } catch (InvalidArgumentException $exception) {
            return $this->jsonError($exception->getMessage(), 400);
        } catch (\RuntimeException $exception) {
            return $this->jsonError($exception->getMessage(), 503);
        }

        $response = Craft::$app->getResponse();
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', $result['contentType']);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Preview-Characters', (string)$result['preview']['previewCharacters']);
        if (is_string($result['characterCost']) && $result['characterCost'] !== '') {
            $response->headers->set('X-ElevenLabs-Character-Cost', $result['characterCost']);
        }
        if (is_string($result['requestId']) && $result['requestId'] !== '') {
            $response->headers->set('X-ElevenLabs-Request-Id', $result['requestId']);
        }
        $response->data = $result['audio'];

        return $response;
    }

    private function requireDevMode(): void
    {
        if (!Craft::$app->getConfig()->getGeneral()->devMode) {
            throw new NotFoundHttpException();
        }
    }

    private function jsonError(string $message, int $status): Response
    {
        $response = Craft::$app->getResponse();
        $response->statusCode = $status;
        $response->format = Response::FORMAT_JSON;
        $response->data = ['error' => $message];

        return $response;
    }

    private function preview(): \modules\storyapi\services\StoryPreviewService
    {
        /** @var Module $module */
        $module = Craft::$app->getModule('story-api');

        return $module->getPreview();
    }
}
