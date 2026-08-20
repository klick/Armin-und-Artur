<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/scripts/story-reading/StoryReadingBatch.php';

function expectTypographyMigration(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$payloadPath = $root . '/resources/story-content/typography-cleanup-2026-08-20.json';
$payload = json_decode((string)file_get_contents($payloadPath), true, 512, JSON_THROW_ON_ERROR);
expectTypographyMigration(($payload['version'] ?? null) === 1, 'Typography payload version must remain 1.');
expectTypographyMigration(($payload['siteHandle'] ?? null) === 'default', 'Typography payload must target the default site.');
expectTypographyMigration(($payload['fieldHandle'] ?? null) === 'body', 'Typography payload must target only the body field.');
expectTypographyMigration(count($payload['entries'] ?? []) === 9, 'Typography payload must contain exactly nine reviewed entries.');

$seenIds = [];
foreach ($payload['entries'] as $change) {
    $entryId = (int)$change['entryId'];
    expectTypographyMigration(!isset($seenIds[$entryId]), "Duplicate typography payload entry {$entryId}.");
    $seenIds[$entryId] = true;
    expectTypographyMigration(
        hash('sha256', (string)$change['bodyHtml']) === $change['afterSha256'],
        "Typography payload body hash mismatch for entry {$entryId}."
    );
    expectTypographyMigration(
        $change['beforeSha256'] !== $change['afterSha256'],
        "Typography payload entry {$entryId} must describe a real change."
    );

    $matches = glob($root . '/resources/story-reading/*.reading.json') ?: [];
    $artifact = null;
    foreach ($matches as $artifactPath) {
        $candidate = json_decode((string)file_get_contents($artifactPath), true, 512, JSON_THROW_ON_ERROR);
        if (($candidate['cms']['entryId'] ?? null) === $entryId) {
            $artifact = $candidate;
            break;
        }
    }
    expectTypographyMigration(is_array($artifact), "No reading artefact found for entry {$entryId}.");
    expectTypographyMigration(
        ($artifact['cms']['entryUid'] ?? null) === $change['entryUid'],
        "Craft UID mismatch for typography payload entry {$entryId}."
    );
    expectTypographyMigration(
        StoryReadingBatch::extractParagraphs((string)$change['bodyHtml']) === $artifact['originalText']['paragraphs'],
        "Typography payload for entry {$entryId} must match its reading artefact exactly."
    );
}

$migration = (string)file_get_contents($root . '/migrations/m260820_072022_sync_story_typography.php');
foreach (['beforeSha256', 'afterSha256', 'hash_equals', 'saveElement', 'refusing to overwrite'] as $guard) {
    expectTypographyMigration(str_contains($migration, $guard), "Migration must retain guard: {$guard}.");
}

echo "Story typography migration payload checks passed\n";
