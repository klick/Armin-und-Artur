<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
require_once $root . '/modules/storyapi/services/StoryPreviewService.php';

use modules\storyapi\services\StoryPreviewService;

function expectPreview(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function expectPreviewFailure(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (yii\base\InvalidArgumentException $exception) {
        expectPreview(str_contains($exception->getMessage(), $message), 'Unexpected preview failure: ' . $exception->getMessage());
        return;
    }
    throw new RuntimeException("Expected preview failure containing: {$message}");
}

$artifact = json_decode(
    (string)file_get_contents($root . '/resources/story-reading/rotkaeppchen.reading.json'),
    true,
    512,
    JSON_THROW_ON_ERROR,
);

$scene = StoryPreviewService::prepareScene($artifact, 's01', 2000);
expectPreview($scene['storyId'] === 'rotkaeppchen', 'Preview must retain the canonical story ID');
expectPreview($scene['sceneId'] === 's01', 'Preview must retain the selected scene ID');
expectPreview(str_starts_with($scene['providerText'], '[very slowly] [warm, grandfatherly storytelling]'), 'Preview must use the approved fairy-tale prompt');
expectPreview(str_ends_with($scene['providerText'], $scene['sourceText']), 'Provider text must end with an unchanged source excerpt');
expectPreview(!str_contains($scene['sourceText'], 'Die Grossmutter aber wohnte draussen im Wald'), 'Scene one must stop at the next scene anchor');
expectPreview($scene['modelId'] === 'eleven_v3' && $scene['stability'] === 0.5, 'Preview must use the approved ElevenLabs model settings');

$shortPreview = StoryPreviewService::prepareScene($artifact, 's01', 100);
expectPreview($shortPreview['truncated'] === true, 'Small preview limits must mark the excerpt as truncated');
expectPreview($shortPreview['previewCharacters'] <= 100, 'Truncated preview must honor the configured character limit');
expectPreview(str_starts_with($scene['sourceText'], $shortPreview['sourceText']), 'Truncation must return an unchanged prefix of the source scene');

expectPreviewFailure(
    static fn() => StoryPreviewService::prepareScene($artifact, 's99', 2000),
    'Unknown scene',
);
expectPreviewFailure(
    static fn() => StoryPreviewService::prepareScene($artifact, 's01', 99),
    'outside the safe range',
);

$routes = (string)file_get_contents($root . '/config/routes.php');
expectPreview(str_contains($routes, "__story-api/reading-preview/render"), 'Preview render route is missing');
expectPreview(
    strpos($routes, "if (filter_var(App::env('DEV_MODE')") < strpos($routes, "__story-api/reading-preview"),
    'Preview routes must remain inside the DEV_MODE block',
);

$controller = (string)file_get_contents($root . '/modules/storyapi/controllers/PreviewController.php');
expectPreview(substr_count($controller, 'requireDevMode()') >= 3, 'Both preview actions must repeat the DEV_MODE guard');
expectPreview(!str_contains($controller, 'enableCsrfValidation'), 'Paid preview POST must retain Yii CSRF validation');
expectPreview(!str_contains($controller, 'getCsrfParam()'), 'Craft request exposes csrfParam as a property, not a getCsrfParam() method');
expectPreview(str_contains($controller, '$request->csrfParam'), 'Preview form must receive Craft\'s configured CSRF parameter name');

$browserFiles = (string)file_get_contents($root . '/templates/story-api/reading-preview.twig')
    . (string)file_get_contents($root . '/web/js/story-reading-preview.js');
expectPreview(!str_contains($browserFiles, 'xi-api-key'), 'Browser files must never construct the ElevenLabs authentication header');
expectPreview(!str_contains($browserFiles, 'App::env'), 'Browser files must never read server environment values');
expectPreview(str_contains($browserFiles, 'Eine Szene, eine durchgehende Erzählerstimme'), 'UI must state the single-narrator contract');

echo "Story reading single-narrator preview checks passed\n";
