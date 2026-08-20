<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;
use RuntimeException;

/**
 * m260820_072022_sync_story_typography migration.
 */
class m260820_072022_sync_story_typography extends Migration
{
    private const PAYLOAD_PATH = __DIR__ . '/../resources/story-content/typography-cleanup-2026-08-20.json';

    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $payload = $this->loadPayload();
        $siteHandle = (string)$payload['siteHandle'];
        $fieldHandle = (string)$payload['fieldHandle'];

        foreach ($payload['entries'] as $change) {
            $entryId = (int)$change['entryId'];
            $entry = Entry::find()
                ->id($entryId)
                ->site($siteHandle)
                ->status(null)
                ->drafts(false)
                ->revisions(false)
                ->one();

            if (!$entry instanceof Entry) {
                throw new RuntimeException("Story typography migration: entry {$entryId} was not found.");
            }
            if ($entry->uid !== $change['entryUid']) {
                throw new RuntimeException("Story typography migration: entry {$entryId} UID does not match the reviewed payload.");
            }

            $currentBody = (string)$entry->getFieldValue($fieldHandle);
            $currentHash = hash('sha256', $currentBody);
            if (hash_equals((string)$change['afterSha256'], $currentHash)) {
                echo "    > entry {$entryId} already has the reviewed typography.\n";
                continue;
            }
            if (!hash_equals((string)$change['beforeSha256'], $currentHash)) {
                throw new RuntimeException(
                    "Story typography migration: entry {$entryId} changed since review; refusing to overwrite it."
                );
            }

            $entry->setFieldValue($fieldHandle, (string)$change['bodyHtml']);
            if (!Craft::$app->getElements()->saveElement($entry, false, false, true)) {
                throw new RuntimeException("Story typography migration: entry {$entryId} could not be saved.");
            }

            $savedHash = hash('sha256', (string)$entry->getFieldValue($fieldHandle));
            if (!hash_equals((string)$change['afterSha256'], $savedHash)) {
                throw new RuntimeException("Story typography migration: entry {$entryId} did not retain the reviewed body exactly.");
            }
            echo "    > updated entry {$entryId}.\n";
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m260820_072022_sync_story_typography cannot be reverted.\n";
        return false;
    }

    /**
     * @return array{siteHandle:string,fieldHandle:string,entries:array<int,array<string,mixed>>}
     */
    private function loadPayload(): array
    {
        $json = file_get_contents(self::PAYLOAD_PATH);
        if (!is_string($json)) {
            throw new RuntimeException('Story typography migration payload could not be read.');
        }

        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (
            !is_array($payload)
            || ($payload['version'] ?? null) !== 1
            || !is_string($payload['siteHandle'] ?? null)
            || !is_string($payload['fieldHandle'] ?? null)
            || !is_array($payload['entries'] ?? null)
            || count($payload['entries']) !== 9
        ) {
            throw new RuntimeException('Story typography migration payload has an unexpected structure.');
        }

        foreach ($payload['entries'] as $change) {
            if (
                !is_array($change)
                || !is_int($change['entryId'] ?? null)
                || !is_string($change['entryUid'] ?? null)
                || !is_string($change['beforeSha256'] ?? null)
                || !is_string($change['afterSha256'] ?? null)
                || !is_string($change['bodyHtml'] ?? null)
                || hash('sha256', $change['bodyHtml']) !== $change['afterSha256']
            ) {
                throw new RuntimeException('Story typography migration payload contains an invalid entry.');
            }
        }

        return $payload;
    }
}
