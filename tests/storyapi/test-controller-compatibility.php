<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);

require $projectRoot . '/vendor/autoload.php';
require_once $projectRoot . '/modules/storyapi/controllers/StoriesController.php';

$controller = new ReflectionClass(\modules\storyapi\controllers\StoriesController::class);
$csrfProperty = $controller->getProperty('enableCsrfValidation');

if ($csrfProperty->getType() !== null) {
    throw new RuntimeException('enableCsrfValidation must remain untyped to match yii\\web\\Controller.');
}

$defaults = $controller->getDefaultProperties();
if (($defaults['enableCsrfValidation'] ?? null) !== false) {
    throw new RuntimeException('CSRF validation must be disabled for the read-only Story API controller.');
}

echo "Story API controller inheritance checks passed\n";
