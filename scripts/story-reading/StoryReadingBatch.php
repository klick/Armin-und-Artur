<?php

declare(strict_types=1);

/**
 * Pure helpers shared by the Craft exporter, the semantic validator and their
 * regression tests. Keeping these rules outside Craft makes the QA checks easy
 * to run in CI without a database.
 */
final class StoryReadingBatch
{
    public const FORMAT_VERSION = 1;
    public const READING_SCHEMA_VERSION = '1.3';
    public const TYPOGRAPHY_NORMALISATIONS = [
        'soft_hyphen_removed',
        'zero_width_space_removed',
        'nonbreaking_space_normalised',
        'inline_whitespace_normalised',
        'line_breaks_in_verse_normalised_to_spaces',
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function readManifest(string $path): array
    {
        $manifest = self::readJson($path);
        $stories = $manifest['stories'] ?? null;
        if (($manifest['manifestVersion'] ?? null) !== self::FORMAT_VERSION || !is_array($stories) || $stories === []) {
            throw new RuntimeException("{$path} must contain manifestVersion 1 and a stories array.");
        }

        $seen = [];
        foreach ($stories as $index => $story) {
            if (!is_array($story)) {
                throw new RuntimeException("Manifest story #{$index} must be an object.");
            }

            $id = $story['id'] ?? null;
            if (!is_string($id) || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $id) !== 1) {
                throw new RuntimeException("Manifest story #{$index} has an invalid canonical id.");
            }
            if (isset($seen[$id])) {
                throw new RuntimeException("Manifest contains duplicate story id {$id}.");
            }
            $seen[$id] = true;

            $entryId = $story['entryId'] ?? null;
            $slug = $story['slug'] ?? null;
            if ((!is_int($entryId) || $entryId < 1) && (!is_string($slug) || trim($slug) === '')) {
                throw new RuntimeException("Manifest story {$id} needs entryId or slug.");
            }
            foreach (['entryId', 'sectionId'] as $integerKey) {
                if (array_key_exists($integerKey, $story) && (!is_int($story[$integerKey]) || $story[$integerKey] < 1)) {
                    throw new RuntimeException("Manifest story {$id} has an invalid {$integerKey}.");
                }
            }
            foreach (['entryUid', 'siteHandle', 'fieldHandle', 'sourceUrl', 'language'] as $stringKey) {
                if (array_key_exists($stringKey, $story) && (!is_string($story[$stringKey]) || trim($story[$stringKey]) === '')) {
                    throw new RuntimeException("Manifest story {$id} has an invalid {$stringKey}.");
                }
            }
        }

        return array_values($stories);
    }

    /**
     * Turn the published CKEditor HTML into the canonical paragraph list.
     *
     * This deliberately performs typography cleanup only. It never rewrites
     * spelling or punctuation; explicit <br> elements become spaces according
     * to the production verse-line normalisation.
     * The exact same function is used by export and regression tests.
     *
     * @return string[]
     */
    public static function extractParagraphs(string $html): array
    {
        if (trim($html) === '') {
            throw new RuntimeException('Published story HTML is empty.');
        }

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="story-reading-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            throw new RuntimeException('Published story HTML could not be parsed.');
        }

        $xpath = new DOMXPath($document);
        $root = $xpath->query('//*[@id="story-reading-root"]')->item(0);
        if (!$root instanceof DOMElement) {
            throw new RuntimeException('Published story HTML has no parseable root.');
        }

        $blocks = [];
        // This is the production extraction contract: published CKEditor
        // paragraphs and block quotes, in their DOM order. Do not broaden the
        // selector without migrating existing reading artefacts.
        foreach ($xpath->query('.//p | .//blockquote', $root) as $element) {
            if ($element instanceof DOMElement) {
                $blocks[] = $element;
            }
        }
        if ($blocks === []) {
            throw new RuntimeException('Published story HTML contains no <p> or <blockquote> elements.');
        }

        $paragraphs = [];
        foreach ($blocks as $block) {
            $text = self::nodeText($block);
            $text = self::normaliseTypography($text);
            if ($text !== '') {
                $paragraphs[] = $text;
            }
        }
        if ($paragraphs === []) {
            throw new RuntimeException('Published story HTML contains no non-empty paragraphs.');
        }

        return $paragraphs;
    }

    public static function normaliseTypography(string $text): string
    {
        $text = str_replace(["\u{00AD}", "\u{200B}", "\u{FEFF}"], '', $text);
        $text = str_replace("\u{00A0}", ' ', $text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[\t\f\v ]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s*\n+\s*/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    public static function scaffold(array $source): array
    {
        return [
            '_scaffold' => [
                'status' => 'requires_editorial_annotation',
                'warning' => 'This envelope is not a publishable *.reading.json artefact. Review cast, scenes, speaker resolution and delivery before extracting artifact.',
                'sourceFile' => ($source['story']['id'] ?? 'story') . '.source.json',
            ],
            'artifact' => [
                '$schema' => './story-reading.schema.json',
                'schemaVersion' => self::READING_SCHEMA_VERSION,
                'story' => $source['story'],
                'cms' => $source['cms'],
                'readingPolicy' => [
                    'preserveWording' => true,
                    'preservePunctuation' => true,
                    'directionsAreSpoken' => false,
                    'productionStyle' => 'single_narrator_audiobook',
                    'defaultPace' => 0.9,
                    'fallbackSpeaker' => 'narrator',
                    'noAddedSoundEffects' => true,
                    'noModernisation' => true,
                ],
                'formatArchitecture' => [
                    'role' => 'canonical_story_direction_format',
                    'principle' => 'This JSON is the single editorial source. Provider payloads are derived from it.',
                    'content' => 'Original text and editorially reviewed speaker, scene and delivery direction.',
                    'renderTargets' => [],
                ],
                'cast' => [
                    'narrator' => [
                        'label' => 'Erzähler',
                        'voiceProfile' => 'editorial_review_required',
                        'delivery' => 'Redaktionell festlegen.',
                    ],
                ],
                'scenes' => [],
                'speakerResolution' => [
                    'directSpeech' => 'Redaktionell festlegen.',
                    'unnamedQuotedSpeech' => 'Redaktionell festlegen.',
                    'creatures' => 'Redaktionell festlegen.',
                    'narration' => 'All narrative prose uses narrator.',
                ],
                'originalText' => $source['originalText'],
            ],
        ];
    }

    /**
     * Validate editorial semantics and exact fidelity to a freshly exported
     * Craft source. JSON Schema validation is intentionally a separate AJV
     * step in validate-artifacts.php, so neither check can mask the other.
     *
     * @param array<string, mixed> $manifestStory
     * @param array<string, mixed> $source
     * @param array<string, mixed> $artifact
     */
    public static function validateArtifact(array $manifestStory, array $source, array $artifact, string $filename): void
    {
        $id = (string)($manifestStory['id'] ?? '');
        if (basename($filename) !== "{$id}.reading.json") {
            throw new RuntimeException("Artifact filename must be {$id}.reading.json.");
        }
        if (($artifact['schemaVersion'] ?? null) !== self::READING_SCHEMA_VERSION) {
            throw new RuntimeException("{$id}: schemaVersion must be " . self::READING_SCHEMA_VERSION . '.');
        }
        if (($source['sourceFormatVersion'] ?? null) !== self::FORMAT_VERSION) {
            throw new RuntimeException("{$id}: source snapshot format is not supported.");
        }

        $sourceProtected = [
            'story' => $source['story'] ?? null,
            'cms' => $source['cms'] ?? null,
            'originalText' => $source['originalText'] ?? null,
        ];
        $expectedHash = hash('sha256', self::canonicalJson($sourceProtected));
        if (!hash_equals($expectedHash, (string)($source['integrity']['protectedPayloadSha256'] ?? ''))) {
            throw new RuntimeException("{$id}: source snapshot integrity hash is invalid; export it again from Craft.");
        }
        $publishedHtml = $source['publishedHtml']['value'] ?? null;
        if (!is_string($publishedHtml) || !hash_equals(hash('sha256', $publishedHtml), (string)($source['publishedHtml']['sha256'] ?? ''))) {
            throw new RuntimeException("{$id}: published HTML snapshot integrity hash is invalid; export it again from Craft.");
        }
        if (self::extractParagraphs($publishedHtml) !== ($source['originalText']['paragraphs'] ?? null)) {
            throw new RuntimeException("{$id}: source paragraphs do not match the snapshotted published HTML.");
        }

        foreach (['story', 'cms', 'originalText'] as $protectedKey) {
            if (($artifact[$protectedKey] ?? null) !== ($source[$protectedKey] ?? null)) {
                throw new RuntimeException("{$id}: {$protectedKey} differs from the freshly exported Craft source.");
            }
        }
        if (($artifact['story']['id'] ?? null) !== $id) {
            throw new RuntimeException("{$id}: story.id does not match the manifest.");
        }

        foreach (['entryId', 'entryUid', 'sectionId', 'siteHandle'] as $cmsKey) {
            if (array_key_exists($cmsKey, $manifestStory) && ($artifact['cms'][$cmsKey] ?? null) !== $manifestStory[$cmsKey]) {
                throw new RuntimeException("{$id}: cms.{$cmsKey} does not match the manifest.");
            }
        }
        foreach (['sourceUrl', 'language'] as $storyKey) {
            if (array_key_exists($storyKey, $manifestStory) && ($artifact['story'][$storyKey] ?? null) !== $manifestStory[$storyKey]) {
                throw new RuntimeException("{$id}: story.{$storyKey} does not match the manifest.");
            }
        }
        foreach (['entryId', 'sectionId'] as $cmsInteger) {
            if (!is_int($artifact['cms'][$cmsInteger] ?? null) || $artifact['cms'][$cmsInteger] < 1) {
                throw new RuntimeException("{$id}: cms.{$cmsInteger} must be a positive integer.");
            }
        }
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', (string)($artifact['cms']['entryUid'] ?? '')) !== 1) {
            throw new RuntimeException("{$id}: cms.entryUid must be a lowercase UUID.");
        }

        $paragraphs = $artifact['originalText']['paragraphs'] ?? null;
        if (!is_array($paragraphs) || $paragraphs === []) {
            throw new RuntimeException("{$id}: originalText.paragraphs must not be empty.");
        }
        foreach ($paragraphs as $index => $paragraph) {
            if (!is_string($paragraph) || trim($paragraph) === '') {
                throw new RuntimeException("{$id}: paragraph #{$index} is empty.");
            }
        }

        $cast = $artifact['cast'] ?? null;
        if (!is_array($cast) || $cast === []) {
            throw new RuntimeException("{$id}: cast must not be empty.");
        }
        $fallback = $artifact['readingPolicy']['fallbackSpeaker'] ?? null;
        if (!is_string($fallback) || !array_key_exists($fallback, $cast)) {
            throw new RuntimeException("{$id}: fallbackSpeaker must reference cast.");
        }

        $scenes = $artifact['scenes'] ?? null;
        if (!is_array($scenes) || $scenes === []) {
            throw new RuntimeException("{$id}: finished artefacts need at least one editorial scene.");
        }
        $fullText = implode("\n\n", $paragraphs);
        $seenSceneIds = [];
        $searchOffset = 0;
        foreach ($scenes as $sceneIndex => $scene) {
            if (!is_array($scene)) {
                throw new RuntimeException("{$id}: scene #{$sceneIndex} must be an object.");
            }
            $sceneId = $scene['id'] ?? null;
            if (!is_string($sceneId) || isset($seenSceneIds[$sceneId])) {
                throw new RuntimeException("{$id}: scene ids must be non-empty and unique.");
            }
            $seenSceneIds[$sceneId] = true;
            $anchor = $scene['anchor'] ?? null;
            if (!is_string($anchor) || $anchor === '') {
                throw new RuntimeException("{$id}: {$sceneId} needs a non-empty anchor.");
            }
            $anchorPosition = strpos($fullText, $anchor, $searchOffset);
            if ($anchorPosition === false) {
                throw new RuntimeException("{$id}: {$sceneId} anchor is absent or out of order in originalText.");
            }
            $searchOffset = $anchorPosition + strlen($anchor);

            $speakers = $scene['speakers'] ?? null;
            if (!is_array($speakers) || $speakers === []) {
                throw new RuntimeException("{$id}: {$sceneId} needs at least one speaker.");
            }
            foreach ($speakers as $speaker) {
                if (!is_string($speaker) || !array_key_exists($speaker, $cast)) {
                    throw new RuntimeException("{$id}: {$sceneId} references unknown cast speaker " . json_encode($speaker) . '.');
                }
            }
        }
    }

    /** @return array<string, mixed> */
    public static function readJson(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException("JSON file not found: {$path}");
        }
        try {
            $decoded = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("Invalid JSON in {$path}: {$exception->getMessage()}", 0, $exception);
        }
        if (!is_array($decoded)) {
            throw new RuntimeException("{$path} must contain a JSON object.");
        }

        return $decoded;
    }

    /** @param array<string, mixed> $value */
    public static function canonicalJson(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** @param array<string, mixed> $value */
    public static function writeJson(string $path, array $value): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException("Could not create {$directory}.");
        }
        $json = json_encode($value, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        $temporary = $path . '.tmp-' . getmypid();
        if (file_put_contents($temporary, $json, LOCK_EX) === false || !rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException("Could not write {$path} atomically.");
        }
    }

    private static function nodeText(DOMNode $node): string
    {
        $text = '';
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                $text .= $child->nodeValue;
            } elseif ($child instanceof DOMElement && strtolower($child->tagName) === 'br') {
                $text .= "\n";
            } else {
                $text .= self::nodeText($child);
            }
        }

        return $text;
    }
}
