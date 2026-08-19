# Story API pilot

This is the first vertical slice of the Armin & Artur agent-facing reading
service. It exposes public-domain source text freely and protects the canonical
editorial **reading-direction** artefacts with [x402 v2](https://www.x402.org/).
The paid product is not the fairy-tale text: it is the editorial layer that
turns it into a dependable read-aloud production (cast, speaker resolution,
voice profiles, scenes, stage directions, reading policy and renderer guidance).

## Endpoints

| Endpoint | Access | Purpose |
| --- | --- | --- |
| `GET /api/v1/stories.json` | free | Craft entry catalogue with reading availability, schema and payment discovery metadata |
| `GET /api/v1/stories/{story-id}.json` | free | Basic published metadata and complete public-domain `originalText`, explicitly without editorial enrichment |
| `GET /api/v1/story-reading.schema.json` | free | JSON Schema contract for canonical reading artefacts |
| `GET /api/v1/stories/{story-id}/reading.json` | x402 | Canonical editorial reading-direction JSON; it also carries the original text |
| `GET /api/openapi.json` | free | OpenAPI 3.1 contract for every Story API endpoint and x402 response headers |
| `GET /llms.txt` / `GET /llms-full.txt` | free | Concise LLM index and detailed agent workflow |

`config/element-api.php` owns the free catalogue because it is entry-oriented.
The `story-api` Craft module owns schema/artifact delivery and x402 protocol
handling; Element API has no payment middleware layer.

## Current reading artefacts

The original five-item pilot contains:

- `rotkaeppchen` — dialogue-led folk tale
- `maerchen-von-einem-der-auszog-das-fuerchten-zu-lernen` — longer
  multi-character folktale
- `der-suesse-brei` — short, narrator-led tale
- `der-wolf-und-die-sieben-jungen-geisslein` — medium-length animal-dialogue
  tale
- `die-gaensemagd` — longer multi-character tale

The first ten-item batch adds:

- `aschenputtel`
- `haensel-und-gretel`
- `rapunzel`
- `der-froschkoenig-oder-der-eiserne-heinrich`
- `schneewittchen`
- `dornroeschen`
- `tischchen-deck-dich-goldesel-und-knueppel-aus-dem-sack`
- `rumpelstilzchen`
- `die-bremer-stadtmusikanten`
- `frau-holle`

They live under `resources/story-reading/`, outside the public web root. Every
artefact is the vendor-neutral source from which future SSML, ElevenLabs, or
other provider payloads are derived.

Together, the artefacts cover short narration, sustained dialogue, ensemble
casts, verse-like speech and longer scene structures. All retain the published
text; their normalisations are declared inside each artefact. The ten-item
batch is machine-validated and independently cross-reviewed, while its
`providerNotes.editorialStatus` deliberately remains
`machine_validated_pending_editorial_review` until human editorial sign-off.

## Agent discovery and public-data boundary

`/llms.txt` is the short LLM-facing entry point. It links to `/llms-full.txt`,
the OpenAPI contract, the catalogue and the JSON Schema. `/api/openapi.json`
is served by Craft instead of a static webroot copy so its route documentation
remains alongside the API implementation.

For an available story, `GET /api/v1/stories/{id}.json` returns only an
explicit allowlist: identifier, basic story metadata and `originalText`. It
never derives a public response by removing fields from the paid artefact, and
never returns `readingPolicy`, `formatArchitecture`, `cast`, `scenes`,
`speakerResolution` or `providerNotes`.

The first unsigned request to a paid endpoint is also a discovery surface. Its
x402 v2 `402` response contains the Bazaar extension at `extensions.bazaar`.
It declares the dynamic GET route, concrete `id` input and JSON output using a
Draft 2020-12 schema. The resource description classifies it for story and
education discovery; no paid editorial content or recipient address is placed
in Bazaar metadata.

## x402 configuration

The protected endpoint is available only when `STORY_API_X402_ENABLED=true`.
Missing, empty, false, or invalid values return `503`; they never expose the
artefact without payment. A missing recipient or invalid network/asset pair
also returns `503` before an agent can sign an authorization.

### Base Sepolia testnet

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

### Public Base Sepolia sandbox

The first public pilot is a **testnet sandbox**, not a sale for money. Its free
catalogue lets agents discover the complete payment parameters before they
request an artefact. A story with a reading artefact exposes metadata shaped
like this:

```json
{
  "reading": {
    "available": true,
    "storyId": "rotkaeppchen",
    "schemaVersion": "1.2",
    "schemaUrl": "https://arminundartur.de/api/v1/story-reading.schema.json",
    "access": "x402",
    "environment": "testnet",
    "payment": {
      "protocol": "x402",
      "version": 2,
      "scheme": "exact",
      "network": "eip155:84532",
      "asset": "0x036CbD53842c5426634e7929541eC2318f3dCF7e",
      "amount": "10000",
      "currency": "USDC",
      "decimals": 6
    },
    "url": "https://arminundartur.de/api/v1/stories/rotkaeppchen/reading.json"
  }
}
```

`amount` is expressed in atomic token units; with six decimals, `10000` is
0.01 test USDC. Base Sepolia test USDC and test ETH have no monetary value.
The recipient address is intentionally supplied by the standard endpoint
challenge rather than duplicated in the catalogue.

An x402 buyer:

1. reads the free catalogue and follows `reading.url`;
2. receives HTTP `402` and decodes `PAYMENT-REQUIRED`;
3. checks that challenge against the catalogue, then signs the Base Sepolia
   EIP-3009 authorization with test USDC;
4. retries with `PAYMENT-SIGNATURE` and, after settlement, receives the reading
   artefact plus `PAYMENT-RESPONSE`.

This pilot does not enable or advertise a mainnet purchase path. Mainnet uses
the separate configuration and deployment review below.
The human-operated browser page below remains a local `DEV_MODE` diagnostic and
is not part of the public sandbox interface.

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
The adapter includes the explicit `EIP712Domain` type required by MetaMask's
`eth_signTypedData_v4`; it mirrors viem's domain fields (`name`, `version`,
`chainId`, `verifyingContract`) used by the official `ExactEvmScheme`.

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

## Base mainnet activation

Base mainnet uses real, dollar-backed Circle USDC. It is deliberately protected
by two independent switches and a fixed initial price cap:

```dotenv
STORY_API_X402_ENABLED=true
STORY_API_X402_MAINNET_ENABLED=true
STORY_API_X402_NETWORK=eip155:8453
STORY_API_X402_ASSET=0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913
STORY_API_X402_PAY_TO=0xYOUR_CONFIRMED_RECEIVER
STORY_API_X402_PRICE_ATOMIC=10000
STORY_API_X402_FACILITATOR_URL=https://api.cdp.coinbase.com/platform/v2/x402
CDP_API_KEY_ID=YOUR_SECRET_API_KEY_ID
CDP_API_KEY_SECRET=BASE64_ED25519_SECRET
```

For the first mainnet pilot, `10000` atomic units (0.01 USDC) is the only
accepted price. Raising it requires a reviewed code change. Mainnet also
requires the exact Circle Base-USDC contract and the authenticated CDP
facilitator; the anonymous x402.org facilitator is rejected.

Create a dedicated CDP **Secret API Key** using the recommended Ed25519
algorithm. Store the ID and secret only in the server environment, never in Git,
browser JavaScript, logs, or chat. The PHP service creates a fresh request-bound
JWT for each `/verify` and `/settle` call; tokens expire after 120 seconds. A CDP
wallet secret is not required because the facilitator settles the buyer's
signed EIP-3009 authorization and sends USDC directly to `payTo`.

The production key is IP-restricted to Hetzner's fixed IPv4 address. Because
the host also has IPv6 connectivity, CDP facilitator requests deliberately use
IPv4; otherwise Coinbase rejects the valid key with HTTP 401 before payment
verification.

The browser page remains a local `DEV_MODE` diagnostic. It accepts only the
hard-coded Base Sepolia and Base mainnet profiles. In mainnet mode it shows a
red real-money warning, the receiver, network, asset, and exact 0.01-USDC
amount, and still requires a separate explicit human wallet signature.

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

Create artefacts in reviewable batches: extract the current published Craft
source, add editorial directions, validate against the contract and exact
source snapshot, then cross-review before making them available. The first
ten-item batch and its manifest are the reference implementation.

See `scripts/story-reading/README.md` for the reproducible DDEV export and
validation commands. The generated scaffold is intentionally not publishable:
cast, scenes, speaker resolution and delivery always require editorial work.
