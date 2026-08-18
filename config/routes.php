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

return [
    'api/v1/story-reading.schema.json' => 'story-api/stories/schema',
    'api/v1/stories/<id:[a-z0-9]+(?:-[a-z0-9]+)*>/reading.json' => 'story-api/stories/reading',
];
