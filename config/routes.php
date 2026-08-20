<?php
/**
 * Site URL Rules
 *
 * You can define custom site URL rules here, which Craft will check in addition
 * to routes defined in Settings → Routes.
 *
 * Read all about Craft’s routing behavior, here:
 * https://craftcms.com/docs/4.x/routing.html
 */

use craft\helpers\App;

$routes = [
    'api/openapi.json' => 'story-api/stories/openapi',
    'api/v1/story-reading.schema.json' => 'story-api/stories/schema',
    'schemas/story-reading-1.3.schema.json' => 'story-api/stories/schema',
    'api/v1/stories/<id:[a-z0-9]+(?:-[a-z0-9]+)*>.json' => 'story-api/stories/story',
    'api/v1/stories/<id:[a-z0-9]+(?:-[a-z0-9]+)*>/reading.json' => 'story-api/stories/reading',
];

// This browser wallet page is intentionally not routable outside Craft dev
// mode. It is a human-operated Base Sepolia test aid, not an API product.
if (filter_var(App::env('DEV_MODE'), FILTER_VALIDATE_BOOL)) {
    $routes['__story-api/x402-browser-test'] = 'story-api/browser-test/index';
}

return $routes;
