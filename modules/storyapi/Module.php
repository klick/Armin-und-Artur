<?php

namespace modules\storyapi;

use Craft;
use modules\storyapi\services\StoryPreviewService;
use modules\storyapi\services\StoryReadingService;
use nystudio107\seomatic\events\RegisterSitemapUrlsEvent;
use nystudio107\seomatic\models\SitemapCustomTemplate;
use yii\base\Event;

/**
 * The small, public-facing Story API.
 *
 * Element API remains responsible for the free, entry-based catalogue. This
 * module owns artefact delivery and payment-specific HTTP behaviour.
 */
class Module extends \yii\base\Module
{
    public function init(): void
    {
        Craft::setAlias('@storyapi', __DIR__);

        // Yii otherwise derives @modules/storyapi/controllers from the
        // namespace, but @modules is not an alias in this project. Keep web
        // controllers out of the console command registry and use concrete
        // directories for both application modes.
        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->controllerNamespace = 'modules\\storyapi\\console\\controllers';
            $this->setControllerPath(__DIR__ . '/console/controllers');
        } else {
            $this->controllerNamespace = 'modules\\storyapi\\controllers';
            $this->setControllerPath(__DIR__ . '/controllers');
        }

        parent::init();

        $this->setComponents([
            'stories' => StoryReadingService::class,
            'preview' => StoryPreviewService::class,
        ]);

        Event::on(
            SitemapCustomTemplate::class,
            SitemapCustomTemplate::EVENT_REGISTER_SITEMAP_URLS,
            [self::class, 'registerPublicSitemapUrls'],
        );
    }

    public function getStories(): StoryReadingService
    {
        /** @var StoryReadingService $stories */
        $stories = $this->get('stories');

        return $stories;
    }

    public function getPreview(): StoryPreviewService
    {
        /** @var StoryPreviewService $preview */
        $preview = $this->get('preview');

        return $preview;
    }

    /**
     * Add public, non-entry pages to SEOmatic's custom sitemap.
     */
    public static function registerPublicSitemapUrls(RegisterSitemapUrlsEvent $event): void
    {
        foreach ($event->sitemaps as $sitemap) {
            $path = parse_url((string)($sitemap['loc'] ?? ''), PHP_URL_PATH);
            if (rtrim((string)$path, '/') === '/vorlese-api') {
                return;
            }
        }

        $event->sitemaps[] = [
            'loc' => '/vorlese-api',
            'changefreq' => 'monthly',
            'priority' => '0.8',
        ];
    }
}
