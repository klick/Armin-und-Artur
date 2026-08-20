<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/scripts/story-reading/StoryReadingBatch.php';

function assertBatch(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function expectBatchFailure(callable $callback, string $messagePart): void
{
    try {
        $callback();
    } catch (RuntimeException $exception) {
        assertBatch(str_contains($exception->getMessage(), $messagePart), "Unexpected failure: {$exception->getMessage()}");
        return;
    }
    throw new RuntimeException("Expected failure containing: {$messagePart}");
}

$paragraphs = StoryReadingBatch::extractParagraphs(
    '<div>ignored outside the production selector</div>'
    . '<p> Eins&nbsp;  zwei' . "\u{00AD}" . ' &amp; drei' . "\u{200B}" . '</p>'
    . '<blockquote>Vers eins<br>Vers zwei</blockquote>'
    . '<p>Schluss.</p>',
);
assertBatch($paragraphs === ['Eins zwei & drei', 'Vers eins Vers zwei', 'Schluss.'], 'Published HTML extraction/normalisation contract changed');
assertBatch(StoryReadingBatch::TYPOGRAPHY_NORMALISATIONS === [
    'soft_hyphen_removed',
    'zero_width_space_removed',
    'nonbreaking_space_normalised',
    'inline_whitespace_normalised',
    'line_breaks_in_verse_normalised_to_spaces',
], 'Five production normalisation labels changed');

$manifestStory = [
    'id' => 'test-maerchen',
    'entryId' => 123,
    'entryUid' => '12345678-1234-4abc-8def-1234567890ab',
    'sectionId' => 1,
    'siteHandle' => 'default',
    'sourceUrl' => 'https://arminundartur.de/test-maerchen',
    'language' => 'de',
];
$story = [
    'id' => 'test-maerchen',
    'title' => 'Test-Märchen',
    'language' => 'de',
    'sourceUrl' => 'https://arminundartur.de/test-maerchen',
    'sourceOfTruth' => 'published_html',
    'textPolicy' => 'read_original_verbatim',
];
$cms = [
    'system' => 'craft',
    'siteHandle' => 'default',
    'entryId' => 123,
    'entryUid' => '12345678-1234-4abc-8def-1234567890ab',
    'sectionId' => 1,
];
$html = '<p>Es war einmal.</p><p>„Guten Tag“, sagte das Kind.</p>';
$originalText = [
    'format' => 'paragraphs',
    'source' => 'published_html',
    'typographyNormalisation' => StoryReadingBatch::TYPOGRAPHY_NORMALISATIONS,
    'paragraphs' => StoryReadingBatch::extractParagraphs($html),
];
$source = [
    'sourceFormatVersion' => 1,
    'story' => $story,
    'cms' => $cms,
    'publishedHtml' => ['fieldHandle' => 'body', 'sha256' => hash('sha256', $html), 'value' => $html],
    'originalText' => $originalText,
];
$source['integrity'] = ['protectedPayloadSha256' => hash('sha256', StoryReadingBatch::canonicalJson([
    'story' => $story,
    'cms' => $cms,
    'originalText' => $originalText,
]))];

$artifact = [
    '$schema' => './story-reading.schema.json',
    'schemaVersion' => '1.3',
    'story' => $story,
    'cms' => $cms,
    'readingPolicy' => ['fallbackSpeaker' => 'narrator'],
    'cast' => [
        'narrator' => ['label' => 'Erzähler'],
        'child' => ['label' => 'Kind'],
    ],
    'scenes' => [
        ['id' => 's01', 'anchor' => 'Es war einmal.', 'speakers' => ['narrator']],
        ['id' => 's02', 'anchor' => '„Guten Tag“', 'speakers' => ['narrator', 'child']],
    ],
    'originalText' => $originalText,
];
StoryReadingBatch::validateArtifact($manifestStory, $source, $artifact, '/tmp/test-maerchen.reading.json');

$changedText = $artifact;
$changedText['originalText']['paragraphs'][0] = 'Es war einmal!';
expectBatchFailure(
    static fn() => StoryReadingBatch::validateArtifact($manifestStory, $source, $changedText, '/tmp/test-maerchen.reading.json'),
    'originalText differs',
);

$badSpeaker = $artifact;
$badSpeaker['scenes'][1]['speakers'][] = 'wolf';
expectBatchFailure(
    static fn() => StoryReadingBatch::validateArtifact($manifestStory, $source, $badSpeaker, '/tmp/test-maerchen.reading.json'),
    'unknown cast speaker',
);

$badAnchor = $artifact;
$badAnchor['scenes'][1]['anchor'] = 'Text, der nicht vorkommt';
expectBatchFailure(
    static fn() => StoryReadingBatch::validateArtifact($manifestStory, $source, $badAnchor, '/tmp/test-maerchen.reading.json'),
    'anchor is absent',
);

$tamperedSource = $source;
$tamperedSource['publishedHtml']['value'] = '<p>Manipuliert.</p>';
expectBatchFailure(
    static fn() => StoryReadingBatch::validateArtifact($manifestStory, $tamperedSource, $artifact, '/tmp/test-maerchen.reading.json'),
    'published HTML snapshot integrity hash is invalid',
);

expectBatchFailure(
    static fn() => StoryReadingBatch::validateArtifact($manifestStory, $source, $artifact, '/tmp/wrong.reading.json'),
    'filename must be',
);

$temporaryManifest = tempnam(sys_get_temp_dir(), 'story-reading-manifest-');
if ($temporaryManifest === false) {
    throw new RuntimeException('Could not create temporary manifest.');
}
file_put_contents($temporaryManifest, json_encode(['manifestVersion' => 1, 'stories' => [$manifestStory]], JSON_THROW_ON_ERROR));
assertBatch(count(StoryReadingBatch::readManifest($temporaryManifest)) === 1, 'Valid manifest was rejected');
unlink($temporaryManifest);

$scaffold = StoryReadingBatch::scaffold($source);
assertBatch(($scaffold['_scaffold']['status'] ?? null) === 'requires_editorial_annotation', 'Scaffold must be visibly non-publishable');
assertBatch(($scaffold['artifact']['scenes'] ?? null) === [], 'Scaffold must not invent editorial scenes');

echo "Story reading batch extraction and semantic QA checks passed\n";
