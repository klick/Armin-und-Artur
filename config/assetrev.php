<?php
return array(
    '*' => array(
        'strategies' => [
            'manifest' => \club\assetrev\utilities\strategies\ManifestFileStrategy::class,
            'querystring' => \club\assetrev\utilities\strategies\QueryStringStrategy::class,
            'passthrough' => function ($filename, $config) {
                return $filename;
            },
        ],
        'pipeline' => 'manifest|querystring|passthrough',
        'manifestPath' => 'web/rev-manifest.json',
        'assetsBasePath' => 'web/',
        'assetUrlPrefix' => getenv('ASSET_BASE_URL'),
    ),
);