<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$controller = file_get_contents($root . '/modules/storyapi/controllers/StoriesController.php');
$service = file_get_contents($root . '/modules/storyapi/services/StoryReadingService.php');
$routes = file_get_contents($root . '/config/routes.php');
check(is_string($controller) && str_contains($controller, "['schema', 'openapi', 'story', 'reading']"), 'All public Story API controller actions must allow anonymous GET access');
check(is_string($routes) && str_contains($routes, "'api/openapi.json' => 'story-api/stories/openapi'"), 'OpenAPI must have a public Craft route');
check(is_string($routes) && str_contains($routes, "'schemas/story-reading-1.3.schema.json' => 'story-api/stories/schema'"), 'The canonical JSON Schema ID must resolve publicly');
check(is_string($routes) && str_contains($routes, "'api/v1/stories/<id:[a-z0-9]+(?:-[a-z0-9]+)*>.json' => 'story-api/stories/story'"), 'Free detail endpoint must have a public Craft route');
check(is_string($service) && str_contains($service, 'public function getOpenApiDocument(): array'), 'Service must provide the OpenAPI contract');

$publicStart = strpos((string)$service, 'public function getPublicStory(string $id): array');
$openApiStart = strpos((string)$service, 'public function getOpenApiDocument(): array');
check($publicStart !== false && $openApiStart !== false && $openApiStart > $publicStart, 'Public story response must be an isolated implementation');
$publicStoryMethod = substr((string)$service, $publicStart, $openApiStart - $publicStart);
foreach (['readingPolicy', 'formatArchitecture', 'cast', 'scenes', 'speakerResolution', 'providerNotes'] as $paidKey) {
    check(!str_contains($publicStoryMethod, "'{$paidKey}'"), "Free story whitelist must never expose {$paidKey}");
}
foreach (['id', 'title', 'language', 'sourceUrl', 'textPolicy', 'originalText', 'paragraphs'] as $publicKey) {
    check(str_contains($publicStoryMethod, "'{$publicKey}'"), "Free story whitelist must include {$publicKey}");
}

$artifact = json_decode((string)file_get_contents($root . '/resources/story-reading/rotkaeppchen.reading.json'), true, 512, JSON_THROW_ON_ERROR);
$allowed = ['id', 'title', 'language', 'sourceUrl', 'textPolicy', 'originalText'];
$simulatedPublic = [
    'id' => $artifact['story']['id'],
    'title' => $artifact['story']['title'],
    'language' => $artifact['story']['language'],
    'sourceUrl' => $artifact['story']['sourceUrl'],
    'textPolicy' => $artifact['story']['textPolicy'],
    'originalText' => ['format' => $artifact['originalText']['format'], 'source' => $artifact['originalText']['source'], 'paragraphs' => $artifact['originalText']['paragraphs']],
];
check(array_keys($simulatedPublic) === $allowed, 'Regression fixture must prove the public response is an allowlist');
foreach (['readingPolicy', 'formatArchitecture', 'cast', 'scenes', 'speakerResolution', 'providerNotes'] as $paidKey) {
    check(!array_key_exists($paidKey, $simulatedPublic), "Free story output must not leak {$paidKey}");
}

$openApiMethod = strstr((string)$service, 'public function getOpenApiDocument(): array');
check(is_string($openApiMethod) && str_contains($openApiMethod, "'openapi' => '3.1.1'"), 'OpenAPI document must declare OpenAPI 3.1');
check(str_contains($openApiMethod, '$this->getPaymentDiscoveryMetadata()'), 'OpenAPI payment documentation must follow the active deployment configuration');
check(!str_contains($openApiMethod, 'Production uses Base mainnet'), 'OpenAPI must not hard-code production payment settings into every environment');
check(str_contains($openApiMethod, "'security' => []"), 'OpenAPI must explicitly declare anonymous discovery access');
foreach (['listStories', 'getPublicStory', 'getReadingSchema', 'buyStoryReading'] as $operationId) {
    check(str_contains($openApiMethod, "'operationId' => '{$operationId}'"), "OpenAPI must provide stable operationId {$operationId}");
}
foreach (['/api/v1/stories.json', '/api/v1/stories/{id}.json', '/api/v1/story-reading.schema.json', '/api/v1/stories/{id}/reading.json', 'PAYMENT-REQUIRED', 'PAYMENT-SIGNATURE', 'PAYMENT-RESPONSE', "'402'", "'503'"] as $needle) {
    check(str_contains($openApiMethod, $needle), "OpenAPI contract must document {$needle}");
}
check(!str_contains($openApiMethod, "'in' => 'header', 'required' => true"), 'OpenAPI response headers must use Header Objects, not Parameter Objects');

foreach (['web/llms.txt', 'web/llms-full.txt'] as $file) {
    $content = file_get_contents($root . '/' . $file);
    check(is_string($content) && str_starts_with($content, '# '), "{$file} must be an LLM-readable Markdown document with an H1");
    check(!preg_match('/(CDP_API_KEY|STORY_API_X402_PAY_TO|0x[a-fA-F0-9]{40})/', $content), "{$file} must not disclose credentials or recipient addresses");
}
$llms = (string)file_get_contents($root . '/web/llms.txt');
check(str_contains($llms, 'https://arminundartur.de/llms-full.txt'), 'llms.txt must link to llms-full.txt');
foreach (['https://arminundartur.de/api/openapi.json', 'https://arminundartur.de/api/v1/stories.json', 'https://arminundartur.de/schemas/story-reading-1.3.schema.json'] as $url) {
    check(str_contains($llms, $url), "llms.txt must link to {$url}");
}

echo "Public discovery and paid-data boundary checks passed\n";
