#!/usr/bin/env php
<?php

declare(strict_types=1);

use craft\elements\Entry;

require_once __DIR__ . '/StoryReadingBatch.php';

$options = getopt('', ['manifest:', 'output-dir:', 'public-base-url::']);
$manifestPath = isset($options['manifest']) ? (string)$options['manifest'] : '';
$outputDirectory = isset($options['output-dir']) ? rtrim((string)$options['output-dir'], '/') : '';
$publicBaseUrl = isset($options['public-base-url']) && $options['public-base-url'] !== false
    ? rtrim((string)$options['public-base-url'], '/')
    : rtrim((string)(getenv('STORY_READING_PUBLIC_BASE_URL') ?: 'https://arminundartur.de'), '/');

if ($manifestPath === '' || $outputDirectory === '') {
    fwrite(STDERR, "Usage: php scripts/story-reading/export-sources.php --manifest=PATH --output-dir=PATH [--public-base-url=https://arminundartur.de]\n");
    exit(2);
}
if (filter_var($publicBaseUrl, FILTER_VALIDATE_URL) === false) {
    fwrite(STDERR, "--public-base-url must be an absolute URL.\n");
    exit(2);
}

try {
    $manifestStories = StoryReadingBatch::readManifest($manifestPath);

    $root = dirname(__DIR__, 2);
    require $root . '/bootstrap.php';
    /** @var craft\console\Application $app */
    $app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/console.php';

    foreach ($manifestStories as $selection) {
        $id = (string)$selection['id'];
        $siteHandle = (string)($selection['siteHandle'] ?? 'default');
        $fieldHandle = (string)($selection['fieldHandle'] ?? 'body');

        $query = Entry::find()
            ->site($siteHandle)
            ->status('live')
            ->drafts(false)
            ->revisions(false);
        if (isset($selection['entryId'])) {
            $query->id((int)$selection['entryId']);
        }
        if (isset($selection['slug'])) {
            $query->slug((string)$selection['slug']);
        }
        if (isset($selection['sectionId'])) {
            $query->sectionId((int)$selection['sectionId']);
        }

        /** @var Entry|null $entry */
        $entry = $query->one();
        if ($entry === null) {
            throw new RuntimeException("{$id}: no live, non-draft Craft entry matches the manifest selector.");
        }
        $section = $entry->getSection();
        $site = $entry->getSite();
        if ($section === null) {
            throw new RuntimeException("{$id}: Craft entry has no section.");
        }

        foreach (['entryId' => $entry->id, 'entryUid' => $entry->uid, 'sectionId' => $section->id, 'siteHandle' => $site->handle] as $key => $actual) {
            if (array_key_exists($key, $selection) && $selection[$key] !== $actual) {
                throw new RuntimeException("{$id}: manifest {$key} does not match Craft ({$selection[$key]} != {$actual}).");
            }
        }

        $fieldValue = $entry->getFieldValue($fieldHandle);
        if (!is_string($fieldValue) && !$fieldValue instanceof Stringable) {
            throw new RuntimeException("{$id}: field {$fieldHandle} is not published HTML/stringable data.");
        }
        $publishedHtml = (string)$fieldValue;
        $paragraphs = StoryReadingBatch::extractParagraphs($publishedHtml);

        $uri = trim((string)$entry->uri, '/');
        $sourceUrl = (string)($selection['sourceUrl'] ?? ($publicBaseUrl . '/' . encodeUriPath($uri)));
        $language = isset($selection['language'])
            ? (string)$selection['language']
            : (string)(explode('-', $site->language, 2)[0] ?: $site->language);
        $source = [
            'sourceFormatVersion' => StoryReadingBatch::FORMAT_VERSION,
            'story' => [
                'id' => $id,
                'title' => (string)$entry->title,
                'language' => $language,
                'sourceUrl' => $sourceUrl,
                'sourceOfTruth' => 'published_html',
                'textPolicy' => 'read_original_verbatim',
            ],
            'cms' => [
                'system' => 'craft',
                'siteHandle' => $site->handle,
                'entryId' => $entry->id,
                'entryUid' => $entry->uid,
                'sectionId' => $section->id,
            ],
            'publishedHtml' => [
                'fieldHandle' => $fieldHandle,
                'sha256' => hash('sha256', $publishedHtml),
                'value' => $publishedHtml,
            ],
            'originalText' => [
                'format' => 'paragraphs',
                'source' => 'published_html',
                'typographyNormalisation' => StoryReadingBatch::TYPOGRAPHY_NORMALISATIONS,
                'paragraphs' => $paragraphs,
            ],
        ];
        $source['integrity'] = [
            'protectedPayloadSha256' => hash('sha256', StoryReadingBatch::canonicalJson([
                'story' => $source['story'],
                'cms' => $source['cms'],
                'originalText' => $source['originalText'],
            ])),
        ];

        $sourcePath = "{$outputDirectory}/sources/{$id}.source.json";
        $scaffoldPath = "{$outputDirectory}/scaffolds/{$id}.scaffold.json";
        StoryReadingBatch::writeJson($sourcePath, $source);
        StoryReadingBatch::writeJson($scaffoldPath, StoryReadingBatch::scaffold($source));
        fwrite(STDOUT, "exported {$id}: " . count($paragraphs) . " paragraphs -> {$sourcePath}\n");
    }
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}

/** Encode each URI segment while preserving path separators. */
function encodeUriPath(string $uri): string
{
    return implode('/', array_map('rawurlencode', explode('/', $uri)));
}
