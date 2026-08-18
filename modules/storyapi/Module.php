<?php

namespace modules\storyapi;

use Craft;
use modules\storyapi\services\StoryReadingService;

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

        if (!Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->controllerNamespace = 'modules\\storyapi\\controllers';
        }

        parent::init();

        $this->setComponents([
            'stories' => StoryReadingService::class,
        ]);
    }

    public function getStories(): StoryReadingService
    {
        /** @var StoryReadingService $stories */
        $stories = $this->get('stories');

        return $stories;
    }
}
