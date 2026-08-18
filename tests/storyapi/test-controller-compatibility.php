<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);

require $projectRoot . '/vendor/autoload.php';
require_once $projectRoot . '/modules/storyapi/controllers/StoriesController.php';
require_once $projectRoot . '/modules/storyapi/controllers/BrowserTestController.php';

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

echo "Story API controller inheritance checks passed\n";
