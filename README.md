# Armin & Artur

Armin & Artur is a Craft CMS 5 site for public-domain German fairy tales and
an agent-facing Story API.

The public-domain story text is not the product. It can be read on the website
and retrieved through the API for free. The paid product is the editorial
**reading-direction JSON**: a provider-neutral source document containing the
cast, voice profiles, scene boundaries, silent stage directions, speaker
resolution rules, reading policy, and renderer guidance needed for a
high-quality read-aloud production.

## Current state

- Craft CMS serves the website and all API routes.
- The complete original text is available through the free API.
- 25 complete reading artefacts conform to Story Reading Format 1.3.
- Paid artefacts use x402 v2 with an exact per-request USDC payment.
- Production currently advertises Base mainnet and a pilot price of 0.01 USDC.
- A fresh `402` challenge and the catalogue are authoritative for payment
  parameters; no recipient address is published in discovery documents.
- Provider-specific SSML, ElevenLabs, or Gemini payloads are derived from the
  canonical JSON. They are not maintained as competing source documents.

Editorial status is intentionally explicit: three artefacts are marked
`pilot_reviewed`, twenty are machine-validated and awaiting human editorial
sign-off, and two earlier examples are marked as legacy examples awaiting
editorial review.

## Data and payment boundary

| Resource | Access | Contains |
| --- | --- | --- |
| Story catalogue | free | Craft metadata, availability, schema and payment discovery |
| Story source | free | Basic metadata and the complete public-domain original text |
| JSON Schema | free | Story Reading Format 1.3 contract |
| Reading artefact | x402 | Original text plus the paid editorial reading layer |

The free source response is built from an explicit allowlist. It never exposes
`readingPolicy`, `formatArchitecture`, `cast`, `scenes`, `speakerResolution`,
or `providerNotes`.

## Public endpoints

| Endpoint | Purpose |
| --- | --- |
| `GET /api/v1/stories.json` | Free story catalogue and reading availability |
| `GET /api/v1/stories/{storyId}.json` | Free complete original text |
| `GET /api/v1/story-reading.schema.json` | JSON Schema response |
| `GET /schemas/story-reading-1.3.schema.json` | Canonical schema URL used by `$id` |
| `GET /api/v1/stories/{storyId}/reading.json` | Paid canonical reading artefact |
| `GET /api/openapi.json` | OpenAPI 3.1.1 contract |
| `GET /llms.txt` and `/llms-full.txt` | Agent discovery and purchase guide |

## Documentation

- [Story Reading Format](resources/story-reading/README.md) — schema, field
  semantics, examples, artefact inventory, and editorial guarantees.
- [Story API and x402](STORY_API.md) — public/paid boundary, discovery,
  purchase flow, responses, configuration, and local tests.
- [Batch pipeline](scripts/story-reading/README.md) — reproducible Craft
  source export, annotation workflow, and semantic validation.
- [Production deployment](DEPLOYMENT.md) — GitHub Actions to Hetzner.
- [Craft 5 upgrade notes](CRAFT5_UPGRADE.md) — historical upgrade details and
  reproducible migration notes.

The short public agent index lives in [`web/llms.txt`](web/llms.txt); its
expanded operational guide is [`web/llms-full.txt`](web/llms-full.txt).

## Repository layout

| Path | Responsibility |
| --- | --- |
| `modules/storyapi/` | Story API, schema delivery, x402 verification and settlement |
| `config/element-api.php` | Free Craft catalogue |
| `resources/story-reading/` | Non-public schema and canonical reading artefacts |
| `resources/story-content/` | Guarded, versioned Craft content corrections |
| `migrations/` | One-time Craft content migrations applied by `craft up` |
| `scripts/story-reading/` | Batch exporter and semantic validator |
| `tests/storyapi/` | Contract, payment, browser-client, and batch regression tests |
| `web/` | Public web root and agent-discovery files |
| `.github/workflows/` | Production deployment workflow |

## Local development

The project uses DDEV. Local secrets belong in untracked environment files and
must never be committed.

```bash
ddev start
ddev exec php craft up --interactive=0
ddev exec php craft project-config/apply --interactive=0
```

Useful checks:

```bash
php tests/storyapi/test-x402-response.php
php tests/storyapi/test-controller-compatibility.php
php tests/storyapi/test-public-discovery-contract.php
php tests/storyapi/test-story-reading-batch.php
php tests/storyapi/test-repository-documentation.php

npx --yes ajv-cli@5 validate --spec=draft2020 \
  -s resources/story-reading/story-reading.schema.json \
  -d 'resources/story-reading/*.reading.json'
```

See [Story API and x402](STORY_API.md) for the opt-in Base Sepolia browser
test. It is available only in Craft `DEV_MODE` and never signs automatically.

## Delivery flow

Normal changes move through a feature branch, `develop`, and a reviewed pull
request to `main`. A merge to `main` starts the production GitHub Action. The
action connects to Hetzner, fast-forwards the existing server checkout, installs
the locked Composer dependencies, runs Craft migrations, and clears caches.

There is no automatic merge and no automatic rollback. See
[DEPLOYMENT.md](DEPLOYMENT.md) for the exact safety behavior.
