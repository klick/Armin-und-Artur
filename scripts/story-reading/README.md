# Story-reading batch pipeline

The pipeline deliberately separates public-domain source export from editorial
annotation. It does not generate cast, scenes or directions with AI and it
never presents a scaffold as a sellable reading artefact.

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
listed in each `originalText.typographyNormalisation`. No spelling or
punctuation is rewritten.

Files below `scaffolds/` are envelopes with an explicit
`requires_editorial_annotation` status. An editor must review and complete
cast, scenes, speakers and delivery, then save only the nested `artifact` as
`resources/story-reading/<id>.reading.json`.

## 2. Validate finished artefacts

```bash
php scripts/story-reading/validate-artifacts.php \
  --manifest=scripts/story-reading/batch-10.manifest.json \
  --sources-dir=storage/runtime/story-reading-batch/sources \
  --artifacts-dir=resources/story-reading
```

The default validator runs AJV against the canonical Draft 2020-12 schema and
then checks filename/story identity, schema version 1.2, Craft ID/UID/section,
non-empty paragraphs, source hashes, exact source-text fidelity, scene order
and anchors, fallback/cast references, and all scene speaker references.

`--semantic-only` exists for the isolated PHP regression test. It must not be
used to approve publishable artefacts because it skips JSON Schema validation.

## 3. Regression test

```bash
php tests/storyapi/test-story-reading-batch.php
```

Only `scripts/story-reading/` is intentionally tracked; unrelated local files
below `scripts/` remain ignored.
