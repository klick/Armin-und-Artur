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

- `rotkaeppchen` — dialogue-led folk tale
- `maerchen-von-einem-der-auszog-das-fuerchten-zu-lernen` — longer
  multi-character folktale
- `der-suesse-brei` — short, narrator-led tale
- `der-wolf-und-die-sieben-jungen-geisslein` — medium-length animal-dialogue
  tale
- `die-gaensemagd` — longer multi-character tale

They live under `resources/story-reading/`, outside the public web root. Every
artefact is the vendor-neutral source from which future SSML, ElevenLabs, or
other provider payloads are derived.

The three additional pilot items deliberately cover different editorial shapes:
short narration, sustained dialogue between animal characters, and a larger
cast with several scene changes. All retain the published text; their
normalisations are declared inside each artefact.

## x402 testnet configuration

The paid endpoint defaults to **enabled**, but returns `503` until a real
recipient wallet is configured. This prevents accidental public release and
never substitutes a made-up wallet address.
Only an explicit `STORY_API_X402_ENABLED=false` disables the gate; missing,
empty, or invalid values fail closed.

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

## Local browser payment test

In Craft `DEV_MODE` only, a human-operated Base Sepolia test page is available
at `GET /__story-api/x402-browser-test`. It is not registered outside dev mode;
the controller repeats the same check. It is deliberately not linked from the
site.

The page has three explicit steps: load the unsigned `402` challenge, connect
and select an injected MetaMask account, then click a final button to sign and
retry the request. It never asks for, receives, or stores a private key or seed
phrase. Before enabling that final click it checks the challenge against the
local configuration and fixed pilot limits:

- Base Sepolia (`eip155:84532`)
- official Base Sepolia USDC (`0x036CbD53842c5426634e7929541eC2318f3dCF7e`)
- exactly `10000` atomic units (0.01 test USDC)
- the configured recipient address and a maximum 60-second authorization

The browser code uses the official `@x402/core/http` codecs for the protocol
headers. Current `@x402/evm` exposes an `address` + `signTypedData` signer
interface but no injected EIP-1193/MetaMask adapter, so this local page uses a
small transparent adapter for the standard EIP-3009
`transferWithAuthorization` typed-data request. It does not send a transaction
itself: the signed authorization is sent once to the same-origin Craft endpoint,
which delegates verification and settlement to the configured facilitator.

Build and test the local page with:

```bash
npm install
npm run build:story-api-browser-test
npm run test:story-api-browser
```

For the manual test, ensure the untracked local DDEV environment has a valid
dedicated Base Sepolia recipient wallet, and the selected MetaMask account has
test USDC and enough test ETH for the facilitator settlement path. Do not use a
production wallet. The final MetaMask signing and payment are intentionally
not exercised by automated tests.

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
