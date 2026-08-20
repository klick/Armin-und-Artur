<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);

require $projectRoot . '/vendor/autoload.php';
require_once $projectRoot . '/modules/storyapi/controllers/StoriesController.php';
require_once $projectRoot . '/modules/storyapi/controllers/BrowserTestController.php';
require_once $projectRoot . '/modules/storyapi/controllers/PreviewController.php';

foreach ([
    \modules\storyapi\controllers\StoriesController::class,
    \modules\storyapi\controllers\BrowserTestController::class,
] as $controllerClass) {
    $controller = new ReflectionClass($controllerClass);
    $csrfProperty = $controller->getProperty('enableCsrfValidation');

    if ($csrfProperty->getType() !== null) {
        throw new RuntimeException("{$controllerClass}: enableCsrfValidation must remain untyped to match yii\\web\\Controller.");
    }

    $defaults = $controller->getDefaultProperties();
    if (($defaults['enableCsrfValidation'] ?? null) !== false) {
        throw new RuntimeException("{$controllerClass}: CSRF validation must be disabled for read-only Story API controllers.");
    }
}

$previewController = new ReflectionClass(\modules\storyapi\controllers\PreviewController::class);
expectPreviewController(
    $previewController->getProperty('enableCsrfValidation')->getDeclaringClass()->getName() === yii\web\Controller::class,
    'PreviewController must inherit Yii CSRF validation instead of disabling it.',
);

function expectPreviewController(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$storiesSource = file_get_contents($projectRoot . '/modules/storyapi/controllers/StoriesController.php');
if (
    !is_string($storiesSource)
    || preg_match('/if\s*\(!\$stories->isX402Enabled\(\)\)\s*\{\s*throw new ServiceUnavailableHttpException/s', $storiesSource) !== 1
) {
    throw new RuntimeException('Disabling x402 must never expose a protected artefact without payment.');
}

echo "Story API controller inheritance checks passed\n";
