# Story Reading Format 1.3

Story Reading Format is the canonical, provider-neutral editorial source for a
read-aloud production. It combines the unchanged public-domain text with the
information a voice agent or renderer needs to interpret it consistently.

The production style is a single-narrator audiobook: one narrator performs the
entire reading and suggests characters through delivery and register. The
format is deliberately not a radio-drama script — there is no multi-voice
casting, no dialogue staging and no sound design.

It is deliberately not an ElevenLabs request, an SSML document, or a Gemini
payload. Those are output formats derived from this master document:

```text
Craft published text + editorial direction
                    |
                    v
       Story Reading Format 1.3
          /          |          \
       SSML    ElevenLabs     Gemini TTS
```

This prevents provider-specific voice IDs or syntax from becoming the source
of truth. The original wording remains stable while renderers evolve.

## Contract and examples

- Canonical schema URL:
  <https://arminundartur.de/schemas/story-reading-1.3.schema.json>
- API schema response:
  <https://arminundartur.de/api/v1/story-reading.schema.json>
- Repository schema: [`story-reading.schema.json`](story-reading.schema.json)
- Short purchase-flow example:
  [`rotkaeppchen.reading.json`](rotkaeppchen.reading.json)
- Reviewed comprehensive example:
  [`die-gaensemagd.reading.json`](die-gaensemagd.reading.json)
- Compact reviewed example:
  [`der-suesse-brei.reading.json`](der-suesse-brei.reading.json)

The schema uses JSON Schema Draft 2020-12. `schemaVersion` is exactly `1.3`.
Consumers should reject an unsupported major or minor version instead of
silently guessing semantics.

## Top-level structure

| Field | Required | Meaning |
| --- | ---: | --- |
| `$schema` | no | Prefer the canonical absolute schema URL |
| `schemaVersion` | yes | Contract version; currently `1.3` |
| `story` | yes | Public identity, source, language, and verbatim-text policy |
| `cms` | no | Internal Craft synchronization reference, not public identity |
| `readingPolicy` | yes | Global rules that every renderer must honor |
| `formatArchitecture` | yes | Provider-neutral role and output mappings |
| `cast` | yes | Stable speaker IDs, voice profiles, and delivery guidance |
| `scenes` | yes | Ordered scene anchors, participants, and silent direction |
| `speakerResolution` | yes | Rules for direct, unnamed, creature, and narrative speech |
| `providerNotes` | no | Non-authoritative provider/editorial notes |
| `originalText` | yes | Complete source text as ordered paragraphs |

The complete artefact is the paid product. A free story response returns only
the public identity and `originalText`; see [STORY_API.md](../../STORY_API.md).

## Identity and source fidelity

`story.id` is the stable lowercase ASCII API identifier. It is not necessarily
the Unicode Craft slug. `story.sourceUrl` points at the published story and
`story.textPolicy` must be `read_original_verbatim`.

The optional `cms` object helps editors verify that the artefact still belongs
to the same Craft entry:

```json
{
  "system": "craft",
  "siteHandle": "default",
  "entryId": 89,
  "entryUid": "15e40cfe-ee3d-4e41-8a7c-f7224ee434d5",
  "sectionId": 1
}
```

The numeric `sectionId` is intentional. A mutable section handle is not part
of this synchronization contract. The CMS reference is internal metadata and
must not replace `story.id` as the public API identity.

`originalText.paragraphs` contains the complete published text in DOM order.
Only declared typography normalisations may be applied. Spelling,
punctuation, wording, and historical language are preserved—even where the
source looks unusual. Regieanweisungen are never injected into this text.

## Reading policy

The current contract requires renderers to preserve wording and punctuation,
keep directions silent, avoid added sound effects, and avoid modernisation.
`productionStyle` is always `single_narrator_audiobook`: every derived output
uses exactly one narrator voice, and character changes are delivery, never
voice switches. `defaultPace` is a relative vendor-neutral value from `0.5`
through `1.5`. All current artefacts carry the editorial house default `0.84`;
per-story pace calibration is an explicit part of human editorial review, so
the uniform value must not be read as a per-story decision yet.
`fallbackSpeaker` must resolve to a cast member; current artefacts normally use
`narrator`.

The JSON Schema validates the value shapes. The semantic validator additionally
checks that fallback and scene speakers actually exist in `cast`.

## Cast and speaker resolution

Each `cast` key is a stable provider-neutral speaker ID:

```json
{
  "narrator": {
    "label": "Erzähler",
    "voiceProfile": "warm_neutral_male",
    "delivery": "Ruhig, klar und texttreu; Spannung ohne Überzeichnung."
  }
}
```

Cast entries are not a multi-voice casting list. They describe the
characterisations — register, tone, attitude — that the single narrator
applies while reading. `voiceProfile` is a register hint for that colouring,
not a provider voice ID and not a separate voice. `delivery` describes
performance intent in prose. Roles that bundle several figures (a group of
children, a pair of speakers) are suggested as one collective colouring of the
narrator's voice, never as several distinct voices.

`speakerResolution` records how a renderer should resolve direct speech,
unnamed quotations, creatures, and narration. This is important because the
source text itself does not carry machine-readable speaker labels.

## Scenes and silent direction

A scene provides:

- a stable sequential ID such as `s01`;
- an exact `anchor` string marking where it begins;
- a short editorial title;
- the cast members participating in that span; and
- a silent `direction` describing pace, mood, contrast, or delivery.

Scenes are not a turn-by-turn transcript. The semantic validator requires
anchors to occur exactly once and in increasing source order, and requires all
speaker references to resolve to the cast. A renderer combines these scene
cues with `speakerResolution` and the original text.

Structured per-line timing, phonemes, emotion curves, and pause objects are not
part of version 1.3. Where needed, they remain prose guidance or are introduced
in a future schema version rather than added ad hoc.

## Provider mappings

`formatArchitecture.role` is
`canonical_story_direction_format`. Its `renderTargets` explain how canonical
fields map to targets such as:

- SSML: prosody, emphasis, pronunciation, and breaks for the one narrator
  voice where supported;
- ElevenLabs: single-voice text-to-speech requests with one narrator
  `voice_id` and optional in-text delivery tags; and
- Gemini TTS: single-speaker prompt and voice configuration with the cast as
  delivery direction.

None of these targets may switch voices per character; the production style is
a single-narrator audiobook.

These mappings are guidance for an adapter. They do not permit an adapter to
rewrite `originalText`. `providerNotes` is deliberately flexible but always
non-authoritative and must never be spoken as story content.

### ElevenLabs fairy-tale reference profile

The current fairy-tale collection carries
`providerNotes.contentProfile: fairy_tale` and an optional-provider preset at
`providerNotes.elevenLabs.fairyTalePreset`. This preset is deliberately not a
global Story Reading Format default: historical prose, essays, legends, or
other future text types must not inherit it automatically.

The approved reference uses `eleven_v3`, stability `0.5`, and the tested
library voice display name `Grandpa - Familiar & Warm`. Its portable selection
intent is an elderly male voice that is warm, familiar, calm, relaxed,
unhurried, slightly gravelly, soft, and friendly. The display name is evidence
of the listening test, not a stable API identity. Deployments resolve and store
the current `voice_id` outside the canonical artefact.

The tested provider prompt begins with
`[very slowly] [warm, grandfatherly storytelling]`. An adapter may derive
short, local dialogue tags from `cast` and `scenes`, but those tags exist only
in the generated ElevenLabs request. They are never inserted into
`originalText`, spoken as directions, or applied to non-fairy-tale artefacts.

The reference test used the first Rotkäppchen paragraph and produced a
67-second reading that received human approval. This is a collection-level
calibration reference, not a promise that every paragraph or voice-library
revision will have the same duration.

In local Craft `DEV_MODE`, `/__story-api/reading-preview` applies this preset
to one selected scene and one configured narrator voice. It is a listening and
editorial QA tool, not part of the public or paid agent API. Provider
credentials stay on the server, and each explicit render click consumes
ElevenLabs credits.

## Pronunciation notes

Observed mispronunciations are recorded as `providerNotes.pronunciations`: a
map from the exact source-text word to a prose pronunciation hint.

```json
{
  "pronunciations": {
    "Kürdchen": "wie im Text erklärt Konrädchen; KÜRD-chen mit hartem K"
  }
}
```

Entries are added only for mispronunciations actually observed in rendered
audio, never speculatively. Like all provider notes they are non-authoritative
and are never spoken. If the collection grows or requires phoneme precision, a
structured field is introduced in a future schema version rather than ad hoc.

## Artefact inventory

There are currently 45 complete schema-valid artefacts:

| Story ID | Editorial status |
| --- | --- |
| [`allerleirauh`](allerleirauh.reading.json) | machine validated; human review pending |
| [`aschenputtel`](aschenputtel.reading.json) | machine validated; human review pending |
| [`bruederchen-und-schwesterchen`](bruederchen-und-schwesterchen.reading.json) | machine validated; human review pending |
| [`das-kleine-maedchen-mit-den-schwefelhoelzern`](das-kleine-maedchen-mit-den-schwefelhoelzern.reading.json) | machine validated; human review pending |
| [`das-tapfere-schneiderlein`](das-tapfere-schneiderlein.reading.json) | machine validated; human review pending |
| [`daumesdick`](daumesdick.reading.json) | machine validated; human review pending |
| [`des-kaisers-neue-kleider`](des-kaisers-neue-kleider.reading.json) | machine validated; human review pending |
| [`der-hase-und-der-igel`](der-hase-und-der-igel.reading.json) | machine validated; human review pending |
| [`der-alte-sultan`](der-alte-sultan.reading.json) | machine validated; human review pending |
| [`der-froschkoenig-oder-der-eiserne-heinrich`](der-froschkoenig-oder-der-eiserne-heinrich.reading.json) | machine validated; human review pending |
| [`der-gestiefelte-kater`](der-gestiefelte-kater.reading.json) | machine validated; human review pending |
| [`der-goldene-vogel`](der-goldene-vogel.reading.json) | machine validated; human review pending |
| [`der-suesse-brei`](der-suesse-brei.reading.json) | pilot reviewed |
| [`der-teufel-mit-den-drei-goldenen-haaren`](der-teufel-mit-den-drei-goldenen-haaren.reading.json) | machine validated; human review pending |
| [`der-wolf-und-die-sieben-jungen-geisslein`](der-wolf-und-die-sieben-jungen-geisslein.reading.json) | pilot reviewed |
| [`die-bremer-stadtmusikanten`](die-bremer-stadtmusikanten.reading.json) | machine validated; human review pending |
| [`die-drei-maennlein-im-walde`](die-drei-maennlein-im-walde.reading.json) | machine validated; human review pending |
| [`die-kluge-else`](die-kluge-else.reading.json) | machine validated; human review pending |
| [`die-drei-spinnerinnen`](die-drei-spinnerinnen.reading.json) | machine validated; human review pending |
| [`die-gaensemagd`](die-gaensemagd.reading.json) | pilot reviewed |
| [`die-goldene-gans`](die-goldene-gans.reading.json) | machine validated; human review pending |
| [`die-prinzessin-auf-der-erbse`](die-prinzessin-auf-der-erbse.reading.json) | machine validated; human review pending |
| [`die-sieben-raben`](die-sieben-raben.reading.json) | machine validated; human review pending |
| [`die-sterntaler`](die-sterntaler.reading.json) | machine validated; human review pending |
| [`die-zwoelf-brueder`](die-zwoelf-brueder.reading.json) | machine validated; human review pending |
| [`die-wichtelmaenner`](die-wichtelmaenner.reading.json) | machine validated; human review pending |
| [`dornroeschen`](dornroeschen.reading.json) | machine validated; human review pending |
| [`frau-holle`](frau-holle.reading.json) | machine validated; human review pending |
| [`frau-trude`](frau-trude.reading.json) | machine validated; human review pending |
| [`haensel-und-gretel`](haensel-und-gretel.reading.json) | machine validated; human review pending |
| [`hans-im-glueck`](hans-im-glueck.reading.json) | machine validated; human review pending |
| [`jorinde-und-joringel`](jorinde-und-joringel.reading.json) | machine validated; human review pending |
| [`katze-und-maus-in-gesellschaft`](katze-und-maus-in-gesellschaft.reading.json) | machine validated; human review pending |
| [`koenig-drosselbart`](koenig-drosselbart.reading.json) | machine validated; human review pending |
| [`maerchen-von-einem-der-auszog-das-fuerchten-zu-lernen`](maerchen-von-einem-der-auszog-das-fuerchten-zu-lernen.reading.json) | legacy example; editorial review pending |
| [`marienkind`](marienkind.reading.json) | machine validated; human review pending |
| [`rapunzel`](rapunzel.reading.json) | machine validated; human review pending |
| [`rotkaeppchen`](rotkaeppchen.reading.json) | legacy example; editorial review pending |
| [`rumpelstilzchen`](rumpelstilzchen.reading.json) | machine validated; human review pending |
| [`schneewittchen`](schneewittchen.reading.json) | machine validated; human review pending |
| [`schneeweisschen-und-rosenrot`](schneeweisschen-und-rosenrot.reading.json) | machine validated; human review pending |
| [`tischchen-deck-dich-goldesel-und-knueppel-aus-dem-sack`](tischchen-deck-dich-goldesel-und-knueppel-aus-dem-sack.reading.json) | machine validated; human review pending |
| [`strohhalm-kohle-und-bohne`](strohhalm-kohle-und-bohne.reading.json) | machine validated; human review pending |
| [`von-dem-fischer-und-seiner-frau`](von-dem-fischer-und-seiner-frau.reading.json) | machine validated; human review pending |
| [`die-weisse-schlange`](die-weisse-schlange.reading.json) | machine validated; human review pending |

Schema validity and exact source fidelity are technical guarantees. They are
not substitutes for editorial sign-off. Consumers that need reviewed material
should inspect `providerNotes.editorialStatus` when present.

## Authoring and validation lifecycle

1. Export the latest published Craft entry.
2. Extract only `p` and `blockquote` nodes in DOM order.
3. Apply and declare the five permitted typography normalisations.
4. Generate a clearly non-publishable scaffold.
5. Complete cast, scenes, directions, and speaker rules editorially.
6. Validate the JSON with AJV against Draft 2020-12.
7. Run semantic validation against the protected Craft source snapshot.
8. Cross-review voice choices, scene boundaries, and direct-speech coverage.
9. Record an honest editorial status; never infer it from schema validity.

The exact commands and manifest format are in the
[batch-pipeline guide](../../scripts/story-reading/README.md).

## Contract limits in version 1.3

- The JSON Schema permits an empty `scenes` array; the semantic validator does
  not.
- Cross-references and anchor uniqueness/order are semantic checks, not JSON
  Schema checks.
- Scene speakers are participant lists, not exact dialogue segmentation.
- `providerNotes` is intentionally open-ended.
- The two legacy examples now use the canonical absolute `$schema` URL and the
  `legacy_example_pending_editorial_review` editorial status.
- The compact Bazaar discovery example is not a complete reading artefact.
