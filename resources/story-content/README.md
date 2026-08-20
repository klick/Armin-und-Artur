# Versioned story-content corrections

This directory contains reviewed Craft content payloads that must be deployed
alongside code. They are separate from the paid reading artefacts in
`resources/story-reading/`.

`typography-cleanup-2026-08-20.json` synchronizes nine public `body` fields
with the source text used by Story Reading Format 1.3. The corresponding Craft
content migration is
`migrations/m260820_072022_sync_story_typography.php`.

The migration is deliberately fail-closed:

- entry ID and UID must match the reviewed Craft entry;
- the current body must match the recorded production `beforeSha256`, or
  already match `afterSha256`;
- any unrelated intervening edit aborts the migration instead of being
  overwritten; and
- the saved body is verified against `afterSha256`.

The payload contains public fairy-tale HTML, not credentials. Do not edit a
released payload or rename its migration. A later correction requires a new
payload and migration.
