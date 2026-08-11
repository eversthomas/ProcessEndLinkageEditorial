# Bereinigung vor Weiterentwicklung — bs-processEditorial

**Zweck dieser Datei:** Verbindlicher, aktueller Arbeitsplan für die Stabilisierungsphase, bevor neue Funktionalität gebaut wird. Diese Datei ist für die Dauer dieser Arbeit die **einzige Quelle für "was ist als Nächstes dran"** — nicht die Offen-Liste in der Root-README.md, die an mehreren Stellen bereits hinter dem tatsächlichen Codestand zurückliegt (siehe Abschnitt "Verhältnis der Projektdateien" unten).

Diese Datei entstand aus einer unabhängigen Code-Analyse (nicht aus den mitgelieferten Projekt-MDs abgeleitet).

---

## Ziel des Moduls (zur Einordnung, nicht Teil der Bereinigung)

bs-processEditorial soll dem Entwickler erlauben, in ProcessWire wie gewohnt Templates/Felder/Seiten anzulegen und über gezielte Backend-Einstellungen (Setup → Redaktion) zu bestimmen, **was** davon Redakteuren in einer eigenen, vom PW-Admin getrennten Oberfläche zugänglich ist und **wie** es dort dargestellt wird (Freigabe, Darstellungsmodus, Navigation, Rollen-Sichtbarkeit, Branding). Es ist explizit **kein** Eins-zu-eins-Spiegel des PW-Backends und **kein** vollautomatischer Zero-Touch-Mechanismus — die Freigabe durch den Entwickler ist bewusster Teil des Konzepts.

Bekannte Lücke, die jetzt als eigener Schritt behoben wird (siehe Schritt 4 unten), nicht mehr nur zurückgestellt: Feldtypen ohne passenden Adapter werden beim Einlesen aktuell lautlos übersprungen (`ProcessWireAdapter::readSchema()`), ohne Hinweis im Setup oder in der Redaktionsansicht.

---

## 0. Entscheidung getroffen: umfassende Feldabdeckung statt Freeze

**Ursprünglich offen, jetzt entschieden (Tom):** Ziel ist keine feste Obergrenze (6 oder 13), sondern schrittweise vollständige Abdeckung der ProcessWire-**Standard**feldtypen (kostenpflichtige ProFields-Module wie RepeaterMatrix/Table/Multiplier ausgenommen — der reguläre Core-Repeater zählt dazu und fehlt aktuell noch als Adapter). Konkreter nächster Bedarf ist noch nicht absehbar, da kein konkretes Folgeprojekt feststeht — neue Feldtypen kommen daher fortlaufend als isolierte, einzelne Adapter-Klassen dazu, sobald Bedarf entsteht. Das entblockiert Schritt 4/5 unten — die Formulargliederung ist von der genauen Feldtypen-Anzahl unabhängig.

**Bereits bestätigt funktionsfähig (Live-Test durch Tom):** alle aktuell implementierten Feldtypen funktionieren grundsätzlich; Bild-Feld liefert dabei nur den Dateinamen als Text statt Vorschau (siehe Schritt 4 unten). Page-Reference ist entgegen ursprünglicher Annahme bereits vollständig implementiert. Repeater ist der bekannteste noch fehlende Core-Feldtyp.

---

## Schritt 0 — Kurzer Codeabgleich vor der ersten Änderung

Bevor irgendetwas geändert wird: kurz prüfen, ob der aktuelle Repo-Stand noch zu den Annahmen dieses Plans passt (diese Analyse basiert auf einem Snapshot, der zwischenzeitlich veraltet sein kann). Konkret bestätigen oder Abweichung melden:
- Existieren `MockAdapter.php`, `MockStore.php`, `SchemaLoader.php`, beide `schema-mock.json`, die `setup_mvp`-Checkbox sowie der Mock-Fallback in `AdapterFactory` unverändert wie beschrieben?
- Rendert `getModuleConfigInputfields()` weiterhin identisch zu `buildConfigFields()` wie in Schritt 3 angenommen?

Nur bei Abweichungen kurz zurückmelden, sonst direkt mit Schritt 1 fortfahren. Kein eigenständiger Umbau in diesem Schritt.

---

## Arbeitsprinzip

- **Ein Schritt pro Cursor-Session.** Nicht mehrere Punkte in einer Session bearbeiten lassen — das hat in der Vergangenheit bereits zu unkontrolliertem Scope-Zuwachs geführt (13 statt 6 Feldtypen in einer einzigen Session).
- Nach jedem Schritt: Tom prüft und bestätigt manuell, bevor der nächste Schritt beauftragt wird.
- Jeder Schritt unten hat ein explizites "Nicht in diesem Schritt" — das ist bewusst, um Cursor keinen Interpretationsspielraum für Zusatzumfang zu geben.

---

## Schritt 1 — Test-/Mock-Inhalte entfernen ✅ erledigt

**Ziel:** Alles, was reiner Test-/Demo-Inhalt ist, vollständig aus dem produktiven Modul entfernen.

Betroffen:
- `src/Adapter/MockAdapter.php`
- `src/Mock/MockStore.php`
- `src/Schema/SchemaLoader.php` (lädt ausschließlich die Mock-Datei — mit Mock-Adapter zusammen entfernen)
- `data/schema-mock.json` **und** `projektplan/schema-mock.json` (identische Duplikate — beide entfernen)
- Option `data_source = mock` sowie der `auto`-Fallback auf Mock in `src/Adapter/AdapterFactory.php` (Datenquelle wird dann ausschließlich `processwire`, kein Fallback mehr)
- Checkbox `setup_mvp` ("Legacy: MVP-Testdatenmodell anlegen") in `BsProcessEditorial.module.php::buildConfigFields()` inkl. der zugehörigen Install-Logik in `___execute()`
- `src/Setup/MvpInstaller.php` (nur von `setup_mvp` aufgerufen, konsequent mit entfernt)
- `data/mock-records.json` (nur vom MockStore genutzt, reiner Testdaten-Rest, konsequent mit entfernt)

Nicht in diesem Schritt: Formularstruktur, Config-Screen-Dopplung, Feldtyp-Entscheidung — nur Entfernen, keine Umbauten.

**Akzeptanzkriterium:** Modul funktioniert weiterhin fehlerfrei mit `data_source = processwire`; keine Restverweise auf Mock/Schema-Mock im Code; Setup-Formular enthält keine Testdaten-Option mehr.

---

## Schritt 2 — Sicherheitsfix: Logo-Upload ✅ erledigt

**Ziel:** SVG-Upload im Branding-Logo (`processBrandLogoUpload()` in `BsProcessEditorial.module.php`) absichern — aktuell nur Endungsprüfung, kein Content-/MIME-Check, Datei landet unter öffentlich erreichbarer URL (Stored-XSS-Risiko).

Nicht in diesem Schritt: sonstige Upload-Logik der Feld-Adapter (läuft bereits sauber über PWs `WireUpload`) — nur der eigene Branding-Logo-Upload ist betroffen.

**Umsetzung (live, Stand nach Fix):** Option (b) — SVG komplett aus erlaubten Formaten entfernt, PNG/JPG/GIF/WebP bekommen Content-Check per `getimagesize()` (Endung muss zu Bildinhalt passen). Legacy-SVGs (bereits hochgeladen, öffentlich erreichbar) werden automatisch beim ersten Request nach Deploy bereinigt (`purgeLegacyBrandLogoSvg()` in `ready()`), Config-Wert wird bei tatsächlichem Legacy-SVG dauerhaft über `saveModuleConfigData()` geleert. Scan läuft nur einmal pro Session ab (Session-Flag `svg_logo_purged_v1`, analog zum bestehenden `nav_cleared_v5`-Muster), nicht bei jedem Request.

**Akzeptanzkriterium:** Hochgeladene SVGs werden entweder inhaltlich validiert/bereinigt oder SVG wird aus den erlaubten Formaten entfernt; bestehende Funktionalität (PNG/JPG/GIF/WebP) bleibt unverändert.

---

## Schritt 3 — Doppelte Config-Oberfläche konsolidieren ✅ erledigt (live verifiziert)

**Befund aus Schritt 0 (Codeabgleich):** `getModuleConfigInputfields()` rendert bereits nicht mehr identisch zu `buildConfigFields()` — der native Modul-Screen zeigt nur noch Hinweistext + Link auf Setup → Redaktion. Von Tom live im Browser gegen das Akzeptanzkriterium geprüft und bestätigt.

**Ziel:** Aktuell rendern sowohl der native ProcessWire-Modul-Konfigurationsbildschirm (`getModuleConfigInputfields()`) als auch die eigene Setup-Seite (`___execute()`) identisch dieselbe `buildConfigFields()`-Liste. Der native Screen soll nur noch Hinweistext + Link auf Setup → Redaktion zeigen, keine funktionalen Einstellungen mehr.

Nicht in diesem Schritt: inhaltliche Gliederung der Felder selbst (das ist Schritt 5).

**Akzeptanzkriterium:** Einstellungen lassen sich nur noch über Setup → Redaktion ändern; nativer Modul-Screen zeigt lediglich einen Verweis; keine Funktionalität geht verloren.

---

## Schritt 4 — Transparenz für nicht unterstützte/unvollständige Felder ✅ erledigt

**Ziel:** Statt Felder ohne passenden Adapter lautlos zu überspringen, sollen sie sichtbar als "noch nicht unterstützt" markiert werden — sowohl für den Entwickler (Setup) als auch für den Redakteur (Formular). Zusätzlich: Bild-Feld zeigt aktuell nur den Dateinamen als Text statt einer Vorschau.

Konkret:
- **Setup → Redaktion** (`TemplateDiscovery`/Entdeckte-Inhaltstypen-Tabelle): pro Template anzeigen, welche Felder erkannt, aber ohne passenden Adapter sind (Feldname + PW-Feldtyp).
- **Redaktions-Formular** (`FormRenderer`/`ProcessWireAdapter::readSchema()`): statt `continue` bei fehlendem Adapter einen sichtbaren Platzhalter rendern, z. B. "Feld '{label}' (Typ {typ}) wird hier noch nicht unterstützt — bitte im PW-Backend pflegen".
- **Bild-Vorschau**: `ImageField.php` um eine Thumbnail-Vorschau (`<img>`) statt reinem Dateinamen-Text ergänzen.

Nicht in diesem Schritt: neue Feldtyp-Adapter selbst bauen (z. B. Repeater) — nur sichtbar machen, was fehlt bzw. unvollständig ist.

**Akzeptanzkriterium:** Ein Template mit einem nicht unterstützten Feldtyp zeigt im Setup und im Redaktionsformular einen klaren Hinweis statt eines stillschweigend fehlenden Feldes; Bild-Feld zeigt eine Vorschau.

**Umsetzung (live, verifiziert):** Setup zeigt pro Template eine "Ohne Adapter"-Spalte (Feldname + PW-Feldtyp) über `unsupportedFieldsForTemplate()`. `readSchema()` liefert unsupported Felder als `type: unsupported` (nur Name/Typ als Metadaten, kein Feldwert) statt sie zu überspringen; eigene `UnsupportedField`-Renderklasse zeigt den Platzhalter. `Router.php` überspringt unsupported Felder beim POST vollständig — bestehende Werte bleiben beim Speichern anderer Felder unangetastet. Bild-Vorschau liefert jetzt `{name, url}` mit Thumbnail. Getestet mit temporärem `FieldtypePassword`-Feld (danach entfernt); auf aktuellen Kandidaten-Templates sonst keine unsupported Felder.

---

## Schritt 5 — Setup-Formular strukturieren ✅ erledigt

**Ziel:** Das aktuell lineare, ungegliederte Formular (~20 Felder in einer Spalte) in klare Abschnitte gliedern, z. B.:
1. Inhalte & Freigabe (Templates, Modus pro Typ)
2. Navigation (Menü-Builder, JSON-Fallback)
3. Design/Branding (Farben, Radius, Firmenname, Logo)
4. Zugriff/Rollen (pro Rolle sichtbare Typen)
5. Erweitert (Demo-Login, URL-Pfad)

Voraussetzung: Schritt 1 (Testdaten raus) und Schritt 3 (Dopplung raus) sind abgeschlossen. Schritt 4 (Transparenz) sollte idealerweise vorher laufen, ist aber keine harte Voraussetzung.

Nicht in diesem Schritt: visuelles Redesign der Redaktions-Oberfläche selbst (das ist ein separates, späteres Thema) — hier geht es ausschließlich um den Entwickler-Settings-Screen unter Setup → Redaktion.

**Akzeptanzkriterium:** Formular ist in benannte Abschnitte/Fieldsets gegliedert; keine funktionale Änderung an den Feldern selbst.

**Umsetzung (live):** `InputfieldFieldset` (alle Abschnitte aufgeklappt) — Speichern läuft weiter über `form->getAll()`, das rekursiv in Fieldsets liest. Fünf Abschnitte: Inhalte & Freigabe (`editorial_templates`, `mode__*`), Navigation (`editorial_nav_builder`, `editorial_nav`), Design/Branding (`brand_name`, `brand_logo_ui`, `theme_accent`, `theme_rail_bg`, `theme_radius`), Zugriff/Rollen (`role__*`, Abschnitt nur bei vorhandenen Rollen), Erweitert (`allow_demo_login`, `login_user`, `login_pass`, `base_path`). "Ohne Adapter"-Hinweise aus Schritt 4 bleiben als eigene Tabelle oberhalb des Formulars. Gegen die ursprüngliche Feldliste geprüft: alle 14 Felder/Feldmuster vollständig zugeordnet, `data_source`/`setup_mvp` korrekt nicht mehr vorhanden. Save/Reload live getestet für `brand_name`/`base_path`; `mode__*`/`role__*` (dynamisch benannt) noch nicht explizit gegengetestet, aber laut Fieldset-Mechanik unkritisch.

---

## Bereinigungsphase abgeschlossen

Schritt 1–5 sind erledigt. Letzter offener Punkt: **README.md wieder auf den echten Stand bringen** (aktuell noch teilweise veraltet, siehe Tabelle unten) — erst danach zurück in reguläre Weiterentwicklung.

---

## Später, nicht Teil dieser Bereinigungsphase

- Aufteilung der "Gottklassen" `src/Ui/Router.php` (679 Zeilen) und `BsProcessEditorial.module.php` (824 Zeilen) in kleinere, verantwortungsklare Einheiten
- Grundlegende Tests für die Adapter-Schicht
- Strukturelles Redesign der Redaktions-Oberfläche (aktuell nur Farbe/Radius per Theme-Tokens austauschbar, Layout ist als PHP-generiertes HTML fest verdrahtet) — **Zielbild von Tom (Stand jetzt, noch nicht final):** Design entsteht separat als HTML-Mockup (mit Claude Design, Export als "Project HTML"/.zip, abgelegt unter `projektplan/design/mockup/`), wird später per Cursor als komplettes Struktur-Update integriert (kein Live-Umbau über Settings). Backend soll dabei nur Farben, Schriftfarben und Abstände beeinflussbar halten — konkretisiert: Erweiterung der bestehenden Theme-Token (`theme_accent`, `theme_rail_bg`, `theme_radius`) um Textfarbe/gedämpfte Textfarbe sowie eine grobe Abstands-Auswahl (kompakt/normal/großzügig), Struktur selbst bewusst kein Setting.

  **Mockup-Analyse (Stand jetzt):** Export enthält ein vollständiges, dokumentiertes CSS-Komponentensystem ("Broadsheet", `styles.css` mit `.btn`/`.tag`/`.field`/`.card`/`.nav`/`.table`/`.dialog` — echtes portables CSS, kein Build nötig, passt zu "kein Framework"-Prinzip). Die `.dc.html`-Dateien selbst nutzen Claude Designs eigene Laufzeit (`DCLogic`, `support.js`, `image-slot.js`, `{{ }}`-Platzhalter) — wird bei der Integration verworfen, nur Struktur/Markup-Muster + `styles.css`-Tokens werden übernommen. Ebenfalls zu verwerfen: `_ds_bundle.js`/`_ds_manifest.json`/`_adherence_oxlintrc.json` (Claude Designs eigene Tooling-Metadaten) sowie der CMYK-Trennfarben-Hover-Effekt (`.cmyk`, mausverfolgende SVG-Filter-Animation) — dekorativ, für Admin-Tool nicht passend. In `Component.collections()` (im `.dc.html`) steckt bereits ein brauchbares Platzhalter-Datenschema pro Listenansicht (title/newLabel/col4/total/rows mit name/cat/status/info/updated) als Vorlage fürs Mapping auf echte PW-Adapter-Daten. Mockup unterscheidet bereits sechs Ansichten (dashboard/list/edit/blogEdit/tree/media/calendar) — deckt sich mit der zurückgestellten "Daten-Art pro Template"-Idee unten.

  **Entscheidung getroffen:** Schrift wird selbst gehostet (nicht per Google-Fonts-`@import`, DSGVO-relevant), einheitlich **Open Sans** statt der ursprünglichen Serifenschrift des Mockups — funktional, gut lesbar, eine Familie für Überschrift+Text (Regular 400, Italic 400, SemiBold 600, Bold 700). Radius von Broadsheet (1/2/4px) und Modernist (0/0/0) auf einheitlich 4/6/8px angehoben; datendichte Stellen (`.table`, `.card`, Dashboard-Zeilen, Kachelgrid) bekommen wieder sichtbare Ränder/Hintergründe statt reiner Weißraum-Trennung. **Modernist-Variante komplett verworfen** — nur die Broadsheet-Variante (`Redaktionsoberflaeche_dc.html` + zugehörige `styles.css`) wird weitergeführt.

**Offen/ungeklärt:** Nach der Cursor-Umsetzung sieht das Mockup laut Tom optisch noch genauso aus wie vorher — Schrift-/Radius-Änderung nicht sichtbar wirksam. Wahrscheinlichste Ursachen (noch nicht verifiziert): Browser-Cache (harter Reload nötig), oder das `<link>`-Tag im `.dc.html` verweist weiterhin auf den alten Pfad `_ds/broadsheet-.../styles.css` statt auf die tatsächlich bearbeitete `styles.css` — exakt dieselbe Fehlerklasse wie das anfangs fehlende Stylesheet. Vor weiteren Design-Schritten muss das geklärt werden.

  Vor der Integration muss geklärt werden, welche Mockup-Stellen durch echte Daten ersetzt werden (Platzhalter-Mapping) — Grundlage dafür ist bereits in `collections()` vorhanden, s. o.. Mögliche spätere Ergänzung, noch nicht entschieden: Entwickler ordnen Templates zusätzlich einer Daten-Art zu (Listen, Daten, Seiten, Termine, Beiträge), um unterschiedliche Darstellungsvarianten zu ermöglichen — bewusst zurückgestellt.

---

## Verhältnis der Projektdateien (zur Orientierung, auch für Cursor)

| Datei | Rolle | Aktualität |
|---|---|---|
| **Diese Datei** (`refactoring-plan.md`) | Verbindlicher Arbeitsplan für die aktuelle Bereinigungsphase — maßgeblich für "was jetzt dran ist" | Aktuell, aus unabhängiger Code-Analyse |
| `README.md` (Root) | Projektüberblick + Offen-Liste "Vor der nächsten Session" | **Teilweise veraltet** — nennt z. B. weder die doppelte Config-Oberfläche noch die `setup_mvp`-Checkbox, die beide noch im Code sind. Wird erst **nach Abschluss dieser Bereinigungsphase** aktualisiert, nicht vorher als Stand-Referenz nutzen. |
| `projektplan/PROJECT.md` | Stabile Architektur-/Stack-Übersicht, verweist für Status auf README.md | Stabil, unkritisch |
| `projektplan/adapter-notes.md` | Referenzdokumentation der Adapter-Schicht (610 Zeilen) | Nur bei Bedarf konsultieren; laut eigener Anmerkung "nicht ohne Absprache ändern" — in dieser Phase nicht anfassen |
| `projektplan/design/*.md` (forms.md, navigation.md, dashboards.md, list-views.md, workflow-states.md) | UX-Leitlinien für die **redaktionelle Oberfläche** (Ansicht der Redakteure) | Betreffen **nicht** den Entwickler-Settings-Screen (Setup → Redaktion), der in Schritt 3/5 bearbeitet wird — bei diesen Schritten nicht als Vorgabe heranziehen, sonst Vermischung zweier verschiedener UI-Ebenen |
| `projektplan/schema-mock.json`, `data/schema-mock.json` | Test-/Mock-Schema | Wird in Schritt 1 vollständig entfernt |
