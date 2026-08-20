# Auftrag: Typografische Defekte in den Craft-Märchentexten bereinigen

> Agenten-Prompt für die einmalige Quelltext-Bereinigung. Voraussetzung: Story Reading Format 1.3 ist gemerged (Branch-Stand mit `readingPolicy.productionStyle`). Der Lauf gehört auf einen eigenen Git-Branch `fix/story-text-typography`.

## Kontext

Repo: Craft CMS 5, läuft in DDEV. Die Website veröffentlicht 15 gemeinfreie Grimm-Märchen. Der publizierte Craft-Text (Feld-Handle `body`, CKEditor-HTML, Section-ID 1, Site-Handle `default`) ist die alleinige Quelle der Wahrheit („source of truth“) für die kanonischen Vorlese-Artefakte unter `resources/story-reading/*.reading.json` (Story Reading Format 1.3, siehe `resources/story-reading/README.md`).

Die Texte enthalten OCR-/Übertragungsdefekte (falsche Anführungszeichen, gesperrter Druck, Satzzeichenfehler). Diese sollen **an der Quelle in Craft** repariert werden. Danach müssen die Vorlese-Artefakte per Pipeline neu synchronisiert und validiert werden.

Die 15 Entries findest du über die `cms.entryId`/`cms.entryUid`-Blöcke in den 15 `resources/story-reading/*.reading.json` sowie (für zehn davon) in `scripts/story-reading/batch-10.manifest.json`.

## Oberste Regel: Reparieren, nicht modernisieren

Der Contract verlangt `noModernisation` und `read_original_verbatim`. Erlaubt sind ausschließlich Korrekturen **eindeutiger technischer Defekte**. Verboten:

- KEINE Rechtschreibmodernisierung. Historische Formen bleiben exakt erhalten: `hiess`, `liess`, `dass sie bluteten`, `not tun`, `müssig`, `Gemüs‘`, Schweizer `ss` statt `ß` (durchgängige Konvention der Site — nie zu `ß` ändern).
- KEINE Wort-, Grammatik- oder Satzbauänderungen. Kein Glätten, kein Kürzen.
- KEINE Änderungen an HTML-Struktur (`<p>`, `<blockquote>`, `<br>` in Versen).
- Im Zweifel: Finger weg und Fundstelle im Abschlussbericht zur menschlichen Entscheidung listen. Referenz bei Unsicherheit ist die Grimm-KHM-Druckfassung (Ausgabe letzter Hand, 1857) — aber die Site-Fassung kann bewusst leicht abweichen; die Druckfassung dient nur zur Defekt-Verifikation, nie als Anlass für Angleichungen.

## Zu bereinigende Defektklassen

Zielkonvention: deutsche Anführungszeichen `„…“` für direkte Rede, `‚…‘` für verschachtelte Rede, Apostroph `’`, Gedankenstrich `–`.

1. **Falsche/vertauschte Anführungszeichen:** z. B. schließendes `‘` statt `“`; öffnendes `“` statt `„`; englische `"` oder `“` in falscher Richtung; Komma `,` als Pseudo-Anführungszeichen am Redebeginn.
2. **Unbalancierte Anführungszeichen:** fehlende öffnende oder schließende Zeichen; doppelt gesetzte. Jede direkte Rede muss sauber öffnen und schließen.
3. **Leerzeichenfehler um Anführungszeichen:** z. B. `sprach: “ Ich darf` oder `essen “` (Leerzeichen innerhalb, fehlende Satzzeichen davor).
4. **Inkonsistente Apostrophe:** `‘` und `‚` als Apostroph verwendet (`Steig‘ ab`, `hab‘`, `tät‘` vs. `lass’n`) → einheitlich `’`. Der Apostroph selbst bleibt (er markiert historische Elision — nicht ausschreiben!).
5. **Gesperrter Druck:** `e i n e n` → `einen` (bei erkennbarer Betonungsabsicht als `<em>einen</em>` setzen, damit die Hervorhebung im Web erhalten bleibt; die Textextraktion der Pipeline nimmt nur Textinhalte, TTS bleibt sauber).
6. **OCR-Artefakte:** verirrte Bindestriche (`das dich- zugrunde richten`), verirrte Kommas/Punkte (`erst eine Eule dann ein Rabe. zuletzt ein Täubchen`), fälschliche Binnengroßschreibung (`Becherlein Getrunken?`, `jemand Gelegen!`), doppelte Buchstaben durch Scanfehler (`„Jaa, sagte`).
7. **Eindeutig fehlende Satzzeichen an Redegrenzen:** fehlender Punkt/Komma direkt vor schließendem Anführungszeichen (`haben wollt “`). Fehlende Kommas mitten im Erzähltext nur korrigieren, wenn die KHM-Druckfassung den Defekt zweifelsfrei bestätigt (z. B. `Sie hoben es auf suchten, ob`).

**Bekannte Fundstellen als Startpunkt (verifizieren, nicht blind ersetzen):** Schneewittchen (entryId 89) enthält die meisten der obigen Beispiele. Die Gänsemagd (entryId 161) hat die inkonsistenten Apostrophe. Alle 15 Geschichten systematisch prüfen — automatisierter Scan plus vollständiges Gegenlesen jedes Textes.

Hilfreiche Scan-Heuristiken (Regex über den extrahierten Text je Absatz): ungerade Anzahl `„`/`“` pro Absatz; `‘` ohne passendes `‚`; ` [a-zäöü] [a-zäöü] ` (Sperrsatz); `[a-zäöü]- ` (Bindestrich-Artefakt); `,“ [a-zäöü]` und `[a-zäöü],„`; `[.!?]„` ohne Leerzeichen; Großbuchstabe nach Komma mitten im Satz.

## Workflow

1. **Snapshot vorher:**

   ```bash
   ddev exec php scripts/story-reading/export-sources.php \
     --manifest=scripts/story-reading/batch-10.manifest.json \
     --output-dir=storage/runtime/pre-cleanup-snapshot
   ```

   Für die fünf Stories außerhalb des Manifests: `body`-HTML per Skript/DB dumpen und ebenfalls unter `storage/runtime/pre-cleanup-snapshot/` ablegen.
2. **Defekte sammeln:** Pro Story eine Tabelle Fundstelle → Vorher → Nachher → Defektklasse → Begründung. Diese Tabelle VOR dem Anwenden ausgeben.
3. **Fixes in Craft anwenden:** per PHP-Skript über die Craft-Element-API innerhalb DDEV (Entry laden über entryId, `body` ersetzen, speichern — Craft legt automatisch Revisionen an). Keine direkten DB-UPDATEs auf `content`-Tabellen.
4. **Artefakte synchronisieren:** Re-Export über die Pipeline (`export-sources.php`), dann in jedem betroffenen `resources/story-reading/<id>.reading.json` ausschließlich `originalText.paragraphs` durch den frischen Export ersetzen. Cast, Szenen, Directions, readingPolicy nicht anfassen — mit einer Ausnahme: Wenn ein Fix den Text eines Szenen-`anchor` verändert hat, den Anchor-String exakt nachziehen (jeder Anchor muss genau einmal und in Reihenfolge im Text vorkommen).
5. **Validieren (alles muss grün sein):**

   ```bash
   npx --yes ajv-cli@5 validate --spec=draft2020 --strict=false \
     -s resources/story-reading/story-reading.schema.json \
     -d "resources/story-reading/*.reading.json"

   php scripts/story-reading/validate-artifacts.php \
     --manifest=scripts/story-reading/batch-10.manifest.json \
     --sources-dir=storage/runtime/story-reading-batch/sources \
     --artifacts-dir=resources/story-reading

   php tests/storyapi/test-story-reading-batch.php
   php tests/storyapi/test-repository-documentation.php
   ```

6. **Abschlussbericht:** angewandte Fixes pro Story (Vorher/Nachher), übersprungene Zweifelsfälle mit Begründung, Validierungs-Output.

## Harte Grenzen

- Änderungen nur am `body`-Feld der 15 Story-Entries und an `originalText.paragraphs`/`scenes[].anchor` der Reading-JSONs.
- Schema, readingPolicy, cast, Directions, Doku, Tests: nicht anfassen.
- Kein Deploy, kein Push. Arbeite auf einem neuen Git-Branch (`fix/story-text-typography`), committe Repo-Änderungen dort.
- Wenn die semantische Validierung nach dem Sync fehlschlägt, nicht die Validierung aufweichen — den Fehler an der Quelle beheben.
