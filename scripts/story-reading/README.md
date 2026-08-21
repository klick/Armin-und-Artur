# Story-reading batch pipeline

The pipeline deliberately separates public-domain source export from editorial
annotation. It does not generate cast, scenes or directions with AI and it
never presents a scaffold as a sellable reading artefact.

The canonical contract and field semantics are documented in
[`resources/story-reading/README.md`](../../resources/story-reading/README.md). Finished
artefacts live under `resources/story-reading/`; generated source snapshots and
scaffolds live under ignored `storage/runtime/`.

## Pipeline contract

```text
manifest -> published Craft entry -> protected source snapshot
                                      + non-publishable scaffold
                                                     |
                                                     v
                                      editorial annotation and review
                                                     |
                                                     v
                                      schema + semantic validation
                                                     |
                                                     v
                                      canonical reading artefact
```

The manifest pins `entryId`, `entryUid`, `sectionId`, site, slug, and canonical
story ID. It prevents a similarly titled or moved entry from being exported by
accident.

## 1. Export fresh Craft sources

Run the exporter inside DDEV so it reads the current live, non-draft Craft
entries. The output lives below ignored `storage/runtime/` and contains both a
source snapshot and a clearly marked editorial scaffold.

```bash
ddev exec php scripts/story-reading/export-sources.php \
  --manifest=scripts/story-reading/batch-10.manifest.json \
  --output-dir=storage/runtime/story-reading-batch
```

The extraction contract is intentionally narrow and stable: `body` field HTML,
only `p|blockquote` nodes in DOM order, followed by the five normalisations
listed in each `originalText.typographyNormalisation`:

- `soft_hyphen_removed`;
- `zero_width_space_removed`;
- `nonbreaking_space_normalised`;
- `inline_whitespace_normalised`; and
- `line_breaks_in_verse_normalised_to_spaces`.

No spelling, punctuation, grammar, or historical wording is rewritten.

Files below `scaffolds/` are envelopes with an explicit
`requires_editorial_annotation` status. An editor must review and complete
cast, scenes, speakers and delivery, then save only the nested `artifact` as
`resources/story-reading/<id>.reading.json`.

Do not publish a scaffold. Its status explicitly says
`requires_editorial_annotation`, and it is missing the judgment that is the
paid product.

Scaffolds remain genre-neutral. When an editor publishes an artefact from the
current fairy-tale collection, add `providerNotes.contentProfile: fairy_tale`
and the approved `providerNotes.elevenLabs.fairyTalePreset` documented in the
[format guide](../../resources/story-reading/README.md). Do not add that preset
to historical prose or other non-fairy-tale material. The semantic validator
enforces both the approved preset values and this scope boundary.

## 2. Validate finished artefacts

```bash
php scripts/story-reading/validate-artifacts.php \
  --manifest=scripts/story-reading/batch-10.manifest.json \
  --sources-dir=storage/runtime/story-reading-batch/sources \
  --artifacts-dir=resources/story-reading
```

The default validator runs AJV against the canonical Draft 2020-12 schema and
then checks filename/story identity, schema version 1.3, Craft ID/UID/section,
non-empty paragraphs, source hashes, exact source-text fidelity, scene order
and anchors, fallback/cast references, and all scene speaker references.

`--semantic-only` exists for the isolated PHP regression test. It must not be
used to approve publishable artefacts because it skips JSON Schema validation.

## 3. Regression test

```bash
php tests/storyapi/test-story-reading-batch.php
```

The fourth through seventh ten-item collections are pinned in
`batch-10-2026-08-20-3.manifest.json` through
`batch-10-2026-08-20-6.manifest.json`. Their generated sources and scaffolds
use the matching `storage/runtime/story-reading-batch-2026-08-20-N/`
directories; all remain ignored and must be regenerated from Craft when the
published source changes. Earlier batch manifests remain tracked as
reproducible provenance for their artefacts.

Only `scripts/story-reading/` is intentionally tracked; unrelated local files
below `scripts/` remain ignored.

## Editorial release states

Schema validity and source fidelity do not imply editorial approval. Use an
honest `providerNotes.editorialStatus` where present:

- `requires_editorial_annotation`: generated scaffold, never publishable;
- `machine_validated_pending_editorial_review`: technically valid and
  source-exact, but still awaiting human direction review;
- `legacy_example_pending_editorial_review`: pre-batch example artefact,
  migrated to the current contract but never editorially signed off; and
- `pilot_reviewed`: reviewed pilot artefact.

Before sign-off, cross-review the cast, every direct-speech role, scene
boundaries, anchor placement, the register colouring of the single narrator,
and whether the direction can be followed without changing or speaking
anything outside the original text. Two checks require listening to a rendered
reading, not just reading the JSON:

- Pace calibration: `readingPolicy.defaultPace` is currently the editorial
  house default `0.84` in every artefact. Review whether it fits this specific
  story and record a per-story value as a deliberate decision.
- Pronunciation: record every mispronunciation actually observed in rendered
  audio as a `providerNotes.pronunciations` entry (see the
  [format guide](../../resources/story-reading/README.md)); never add
  speculative entries.
