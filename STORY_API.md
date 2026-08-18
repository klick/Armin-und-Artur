# Story API pilot

This is the first vertical slice of the Armin & Artur agent-facing reading
service. It exposes a free discovery layer and protects the complete canonical
reading artefacts with [x402 v2](https://www.x402.org/).

## Endpoints

| Endpoint | Access | Purpose |
| --- | --- | --- |
| `GET /api/v1/stories.json` | free | Craft entry catalogue with an `reading.available` flag |
| `GET /api/v1/story-reading.schema.json` | free | JSON Schema contract for canonical reading artefacts |
| `GET /api/v1/stories/{story-id}/reading.json` | x402 | Full canonical story text, cast and reading direction |

`config/element-api.php` owns the free catalogue because it is entry-oriented.
The `story-api` Craft module owns schema/artifact delivery and x402 protocol
handling; Element API has no payment middleware layer.

## Current pilot artefacts

- `rotkaeppchen`
- `maerchen-von-einem-der-auszog-das-fuerchten-zu-lernen`

They live under `resources/story-reading/`, outside the public web root. Every
artefact is the vendor-neutral source from which future SSML, ElevenLabs, or
other provider payloads are derived.

## x402 testnet configuration

The paid endpoint defaults to **enabled**, but returns `503` until a real
recipient wallet is configured. This prevents accidental public release and
never substitutes a made-up wallet address.

For a local Base Sepolia test, add only to an untracked local environment file:

```dotenv
STORY_API_X402_ENABLED=true
STORY_API_X402_NETWORK=eip155:84532
STORY_API_X402_ASSET=0x036CbD53842c5426634e7929541eC2318f3dCF7e
STORY_API_X402_PAY_TO=0xYOUR_DEDICATED_TEST_WALLET
STORY_API_X402_PRICE_ATOMIC=10000
STORY_API_X402_FACILITATOR_URL=https://x402.org/facilitator
```

`10000` is `USDC` atomic units, i.e. `$0.01` with six decimals. Do not use a
personal or production wallet for the test.

Without `PAYMENT-SIGNATURE`, the endpoint returns HTTP `402` with the same
base64-encoded payment requirements in the `PAYMENT-REQUIRED` header. With a
signature, Craft sends the v2 payload to the configured facilitator's
`/verify`, followed by `/settle`. Successful settlement returns the artefact
and a `PAYMENT-RESPONSE` header. Craft never holds a private payment key.

The implementation follows the x402 v2 HTTP contract directly because the
official server middleware packages currently target JavaScript and Python,
not Craft/PHP. Verification and settlement remain delegated to the official
facilitator endpoint.

## Local checks

```bash
# JSON Schema contract
npx --yes ajv-cli@5 validate --spec=draft2020 \
  -s resources/story-reading/story-reading.schema.json \
  -d 'resources/story-reading/*.reading.json'

# Craft syntax and routes (once DDEV/Docker is running)
ddev exec php craft help
curl -i https://arminundartur.ddev.site/api/v1/stories.json
curl -i https://arminundartur.ddev.site/api/v1/story-reading.schema.json
curl -i https://arminundartur.ddev.site/api/v1/stories/rotkaeppchen/reading.json
```

For the last call, configure `STORY_API_X402_PAY_TO` first. A correct unsigned
test response is `402`, JSON content type, and a decodable `PAYMENT-REQUIRED`
header. An end-to-end transfer still needs a dedicated Base Sepolia wallet with
test USDC and an x402-capable client.

## Batch production after the pilot

Do not generate the whole catalogue before this pilot has been exercised by a
real agent. After that, create artefacts in small batches (20–30): extract the
published original, generate editorial directions, validate against the
contract, and sample-review before marking the reading artefact available.
