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
    // SEOmatic normally registers these rules dynamically. Keep them explicit
    // here so the public sitemap remains available even when Craft builds its
    // site URL rules before SEOmatic's listener has been attached.
    'sitemap.xml' => 'seomatic/sitemap/sitemap-index-redirect',
    'sitemap.xsl' => 'seomatic/sitemap/sitemap-styles',
    'sitemap-empty.xsl' => 'seomatic/sitemap/sitemap-empty-styles',
    'sitemaps-<groupId:\d+>-sitemap.xml' => 'seomatic/sitemap/sitemap-index',
    'sitemaps-<groupId:\d+>-global-custom-<siteId:\d+>-<file:[-\w\.*]+>' => 'seomatic/sitemap/sitemap-custom',
    'sitemaps-<groupId:\d+>-<type:[\w\.*]+>-<handle:[\w\.*]+>-<siteId:\d+>-<file:[-\w\.*]+>' => 'seomatic/sitemap/sitemap',
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
    $routes['__story-api/reading-preview/render'] = 'story-api/preview/render';
    $routes['__story-api/reading-preview'] = 'story-api/preview/index';
}

return $routes;
