<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);

require $projectRoot . '/vendor/autoload.php';

use modules\storyapi\Module;
use nystudio107\seomatic\events\RegisterSitemapUrlsEvent;

function expectSitemap(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$routes = file_get_contents($projectRoot . '/config/routes.php');
expectSitemap(is_string($routes), 'The route configuration must be readable.');

foreach ([
    "'sitemap.xml' => 'seomatic/sitemap/sitemap-index-redirect'",
    "'sitemaps-<groupId:\\d+>-sitemap.xml' => 'seomatic/sitemap/sitemap-index'",
    "'sitemaps-<groupId:\\d+>-global-custom-<siteId:\\d+>-<file:[-\\w\\.*]+>' => 'seomatic/sitemap/sitemap-custom'",
] as $route) {
    expectSitemap(str_contains($routes, $route), "Missing explicit SEOmatic route: {$route}");
}

$event = (new ReflectionClass(RegisterSitemapUrlsEvent::class))->newInstanceWithoutConstructor();
$event->siteId = 1;
$event->sitemaps = [];

Module::registerPublicSitemapUrls($event);
Module::registerPublicSitemapUrls($event);

expectSitemap(count($event->sitemaps) === 1, 'The reading API page must be registered exactly once.');
expectSitemap(($event->sitemaps[0]['loc'] ?? null) === '/vorlese-api', 'The reading API URL must be canonical.');
expectSitemap(($event->sitemaps[0]['changefreq'] ?? null) === 'monthly', 'The change frequency must be explicit.');
expectSitemap(($event->sitemaps[0]['priority'] ?? null) === '0.8', 'The sitemap priority must be explicit.');

echo "Story API sitemap checks passed\n";
