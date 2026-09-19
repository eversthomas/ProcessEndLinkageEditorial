# ProcessEndLinkageEditorial

**English summary:** A generic, non-technical **editorial interface** for ProcessWire — its own front end with login, completely separate from the PW admin. Editors manage approved content types (lists, forms, publish/draft) without ever seeing templates, fields, or the ProcessWire admin itself; developers configure what's exposed, the navigation menu, and branding under **Setup → Redaktion**. Highlights: role-based visibility with fine-grained create/edit/publish permissions, repeater field support with an accordion UI, transactional saving with automatic rollback on validation failure, hardened login (rate limiting, session rotation, idle timeout), and an audit log. Built for and documented in German (its target audience — associations, small/medium businesses — is German-speaking), but the code itself is straightforward PHP 8 / ProcessWire 3.x with no build step. The rest of this README is in German.

---

Generische **Redaktionsoberfläche** für ProcessWire — eigenes Frontend mit Login, getrennt vom PW-Admin.

**Zweck:** Redakteure pflegen freigegebene Inhaltstypen (Listen, Formulare, Publish), ohne Templates, Felder oder den Admin zu sehen. Entwickler konfigurieren Freigabe, Menü und Branding unter **Setup → Redaktion**.

**Nicht:** alternatives Admin-Theme oder PW-Ersatz.

| Schicht | Rolle |
|---------|--------|
| Editorial UI | Shell, Dashboard, Listen, Formulare |
| Form-Engine | rendert nur Schema-Arrays |
| PW-Adapter | Templates/Fields/Pages ↔ Schema |
| ProcessWire | Datenmodell & Speicherung |

Stack: PHP 8+ / PW 3.x · Vanilla JS · CSS ohne Build · Lucide (MIT) · TinyMCE aus PW-Core.

URL-App typisch: `/editorial/` · Modulversion: siehe `ProcessEndLinkageEditorial.module.php`.

---

## Nach der Bereinigungsphase

Die Stabilisierungsarbeit (Mock/Testdaten entfernen, Logo-Upload absichern, native Modul-Config konsolidieren, Transparenz für fehlende Feld-Adapter, Setup-Formular gliedern) ist abgeschlossen. Verlauf und Akzeptanzkriterien: **`projektplan/refactoring-plan.md`** (Historie, nicht mehr die Aufgabenliste für „was als Nächstes“).

**Feldtypen-Zielrichtung:** keine feste Obergrenze — schrittweise Abdeckung der ProcessWire-**Standard**feldtypen (ProFields wie RepeaterMatrix/Table ausgenommen). Der Core-**Repeater** ist umgesetzt (siehe unten). Neue Typen kommen als isolierte Adapter-Klassen bei Bedarf.

Einstieg für Weiterentwicklung: dieses README (Offen-Liste unten), bei Architekturfragen `projektplan/PROJECT.md`.

---

## Umgesetzt (Stand 19.09.2026)

### Kern & Zugang
- Process-Modul, Autoload, Setup-Seite **Setup → Redaktion**
- Nativer Modul-Konfigurationsbildschirm: nur Hinweis + Link auf Setup → Redaktion (keine doppelten Einstellungsfelder)
- Frontend unter konfigurierbarem Pfad (`editorial`)
- Login gegen PW-User; Permission `editorial-access`, Rolle `editorial`
- Superuser immer erlaubt; optional Demo-Login (Setup)
- Session getrennt vom Admin-Login
- Grobe Rechte: Rolle → sichtbare Templates, **fail-closed** (unklare/fehlende Zuordnung → kein Zugriff, nicht mehr „dann eben alles“)

### Setup / Entwickler
- Setup-Formular in **3 Tabs** (ProcessWire `WireTab`):
  1. **Inhalte & Navigation** — Discovery/Freigabe und visueller Menü-Builder in einer Fläche (Freigabe ergibt sich aus der Baumstruktur, keine separate Auswahl mehr)
  2. **Design & Branding** — Firmenname, Logo, Theme-Tokens (inkl. Hauptmenü-Farben)
  3. **Zugriff & System** — Rollen-Mapping, Demo-Login, URL-Pfad, Sitzungs-Timeout
- Fester Footer im Setup-Bildschirm: Autorenschaft (Tom Evers | endlinkage.de) + Versionsnummer inkl. Release-Datum (aus `release-info.json`, siehe unten)
- Template-Discovery + Freigabe (`editorial_templates`, abgeleitet aus der Navigationsstruktur)
- Darstellungsmodus pro Template: Liste | Einzelseite
- **Daten-Art** pro Template: Daten (Default) · Blog · News · Termine · Seiten — steuert die redaktionelle Ansicht; nur „Daten“ hat derzeit die spezialisierte Broadsheet-Oberfläche
- **Ohne Adapter:** Spalte in der Discovery-Tabelle (Feldname + PW-Feldtyp)
- Visueller **Menü-Builder** (Sections / Gruppen / Templates / Icons) + JSON-Fallback
- Theme-Tokens: Akzent, Rail, Text, gedämpfte Textfarbe, Radius, Abstände (kompakt/normal/großzügig) inkl. Farbpicker
- Kunden-Branding: Firmenname + Logo (PNG/JPG/GIF/WebP, Content-Check; **kein SVG**)
- Datenquelle: ausschließlich ProcessWire (kein Mock-Adapter mehr)
- Geister-Templates: leere Defaults; Nav/Routen nur bei existierendem Template

### Redaktions-UX
- App-Shell: Kopfzeile (Marke, Titel, primäre Aktion) + Navigationsfläche + Hauptbereich — Desktop persistent links, Mobile als Off-Canvas-Overlay (Hamburger in der Kopfzeile)
- **Broadsheet-Design** (Open Sans, Mockup-Komponenten) für Templates mit Daten-Art „Daten“ — Dashboard, Liste, Formular
- Andere Daten-Arten: generische Liste/Formular (unverändert, ohne Fehler)
- Dashboard: Kacheln pro Inhaltstyp (inkl. Schnellanlage) + „Zuletzt bearbeitet“ (max. 5)
- Navigationsfläche: Dashboard + Sections/Gruppen/Templates in einer Baumstruktur, aktiver Pfad klappt automatisch auf
- Listen + Formulare; Details-Sidebar; Publish (veröffentlicht / Entwurf)
- Zähler (Dashboard/Menü/Status) laufen über eine reine DB-Zählung, nicht mehr über volles Laden aller Datensätze; Listen selbst sind auf 500 Einträge gedeckelt (echte Pagination folgt mit „Suche/Filter/Sortierung", siehe Offen-Liste)
- Repeater-Felder: Items als Accordion (zugeklappt, einzeln aufklappbar), Hinzufügen/Entfernen ohne Server-Roundtrip
- Felder ohne Adapter: Platzhalter im Formular
- Bildfelder: Thumbnail-Vorschau; Mehrfachbilder (`maxFiles != 1`) mit Einzel-Entfernen
- Vorschau-Link (Frontend-URL), Flash-Feedback

### Feldtypen (Adapter + Form-Engine)

Aktuell implementiert (fortlaufend erweiterbar, kein festes Kontingent):

text · textarea · html/TinyMCE · email · url · integer · float · datetime · checkbox · select · image · file · pageReference · **repeater**

**Repeater (Core):** Items unterstützen aktuell text, textarea/html, url, image als Item-Felder (weitere Item-Feldtypen bei Bedarf als Erweiterung des bestehenden Adapters). Kein Reorder/Sortieren, keine verschachtelten Repeater. Weitere Standard-Feldtypen außerhalb von Repeater-Items bei Bedarf als einzelne Adapter-Klassen.

### Architektur-Dateien (Orientierung)
- `ProcessEndLinkageEditorial.module.php` — Modul, Setup, Config
- `src/Ui/` — Router, Shell, Dashboard, Liste, Nav
- `src/FormEngine/` — Formular-UI (inkl. `UnsupportedField`)
- `src/Adapter/` — ProcessWire-Adapter und Feld-Adapter
- `src/Auth/`, `src/Setup/` — Login, Rechte, NavConfig/NavBuilder, TemplateDiscovery
- `projektplan/` — Planung & Notizen (siehe unten)

---

## Sicherheit (Ist & später)

Der Redaktionsbereich **ändert echte Inhalte** — Sicherheit ist Pflicht vor Kunden-Go-Live.

### Bereits vorhanden
- Login nur mit gültigem PW-User + Passwort (`$session->authenticate`)
- Zugang nur mit Permission `editorial-access` (oder Superuser)
- CSRF-Token auf Login und Formularen
- Editorial-Session getrennt vom Admin-Login (kein `$session->login()`)
- **Login-Rate-Limit**: wachsende Wartezeit je Fehlversuch (eigene, schlanke Umsetzung über `WireCache`, kein neues DB-Schema)
- **Session-Rotation**: neue Session-ID bei jedem erfolgreichen Login (Schutz vor Session-Fixation)
- **Sitzungs-Timeout**: konfigurierbare Idle-Timeout-Dauer (Setup → Zugriff & System), Standard 60 Minuten, 0 = aus
- Demo-Login standardmäßig aus
- Sichtbarkeit der Inhaltstypen über Rollen-Mapping filterbar, **fail-closed** bei unklarer/fehlender Zuordnung
- System-Templates (`admin`, `user`, `role`, `permission` u. a.) serverseitig gesperrt — auch wenn sie sich ins Navigations-JSON einschleichen würden
- Branding-Logo: kein SVG, Content-Check (`getimagesize`) für Rasterformate, Legacy-SVG-Purge
- **Transaktionales Speichern**: neue Seiten, neue Repeater-Items und neu hochgeladene Dateien werden bei einem Validierungsfehler im selben Formular aktiv wieder entfernt (Rollback über `ProcessWireAdapter::rollback()`) — kein Datenrest bei fehlgeschlagenem Speichern
- **Feinere Rechte**: Anlegen/Bearbeiten/Veröffentlichen einzeln je Rolle + Inhaltstyp einstellbar (`role_template_actions`, Setup → Zugriff & System); ohne explizite Einstellung weiterhin voller Zugriff (kein Bruch bestehender Konfiguration)
- **Audit-Log**: jede Speicherung/Veröffentlichung wird mit Benutzer, Inhaltstyp, ID und Aktion in einen eigenen PW-Log-Kanal (`bpe-audit`, sichtbar unter Setup → Logs) geschrieben

### Betrieb (nicht Modul-Code, aber nötig)
- **HTTPS** für `/editorial/`
- Starke Passwörter / PW-User-Hygiene
- Demo-Login produktiv nie aktivieren
- Server-/Hosting-Härtung wie für die restliche Site

### Später im Modul / Konzept (Roadmap)
| Thema | Warum | Priorität |
|-------|--------|-----------|
| Löschen / Papierkorb für Datensätze | Existiert im Modul noch gar nicht (weder Route noch Adapter-Methode) | hoch, siehe Offen-Liste |
| Eigenes Editorial-Rollensystem (unabhängig von PW-Rollen) | Aktuell dienen PW-Rollen weiterhin als Gruppierungs-Schlüssel für `role_templates`/`role_template_actions`; sauberere Trennung wäre möglich, aber mehr Aufwand | zurückgestellt |
| **2FA (TOTP o. ä.)** | Extra Schutz für Publish-Zugang; oft Kundenanforderung | mittel–hoch je nach Kunde |
| Weitere Upload-Härtung an Feld-Adaptern (falls nötig über WireUpload hinaus) | Logo-Pfad ist abgesichert; Bild/Datei laufen über PW `WireUpload` | niedrig–mittel |

*(Login-Rate-Limit, Sitzungs-Timeout seit 14.09.2026, Transaktionales Speichern/Feinere Rechte/Audit-Log seit 19.09.2026 umgesetzt, siehe „Bereits vorhanden" oben.)*

**Brauchen wir ein Sicherheitskonzept?** Ja — als kurze Checkliste (Ist/Soll/Go-Live), kein Roman. Liegt sinnvoll **vor erstem Kunden-Produktivbetrieb**, parallel zu Rechte/Löschen.

**Brauchen wir 2FA?** Nicht für den nächsten Dev-Sprint. Für produktive Kundeninstanzen mit öffentlichen oder sensiblen Inhalten: **ja, einplanen** (z. B. TOTP nach erfolgreichem Passwort; optional PW-Module/`TwoFactorAuth`-Ökosystem prüfen). 2FA ersetzt nicht Rate-Limit und HTTPS.

---

## Offen — Reihenfolge für den nächsten Chat

1. **Listen: Suche / Filter / Sortierung**
2. **Löschen / Papierkorb** für Datensätze
3. **Medienbibliothek** (statt nur Upload am Feld)
4. **Autosave** + robusteres Speichern-Feedback
5. **Zeitplanung** veröffentlichen (aktuell Stub)
6. **Sicherheit Go-Live:** kurze Checkliste (Ist/Soll) — Rate-Limit, Session-Timeout, Transaktionales Speichern, Feinere Rechte und Audit-Log sind erledigt
7. **2FA** (TOTP) für Editorial-Login — vor/mit Kunden-Produktiv
8. **Workflow** jenseits Publish/Entwurf
9. **Daten-Arten** Blog/News/Termine/Seiten — eigene Ansichten (Design steht als Mockup bereit)
10. **Weitere Feldtypen** bei Bedarf (auch als Repeater-Item-Feld, z. B. pageReference/select in Items)
11. Aufteilung großer Klassen (`Router`, Modul-Datei) / Adapter-Tests, dabei auch PHPUnit-Grundgerüst (aktuell keine automatisierten Tests)
12. Screenshots/Kurzbeschreibung für den Eintrag im offiziellen PW-Modulverzeichnis vorbereiten, dann einreichen

---

## Projektdateien (`projektplan/`)

| Datei | Umgang |
|-------|--------|
| `refactoring-plan.md` | Abgeschlossene Bereinigungsphase (Schritt 1–5) — als Historie behalten |
| `adapter-notes.md` | **Nicht ändern** ohne Absprache (Referenz der Adapter-Schicht) |
| `PROJECT.md` | Architektur-/Stack-Kompass — bei Meilensteinen nachziehen |
| `design/*.md` | UX-Leitlinien für die **redaktionelle** Oberfläche (nicht Setup → Redaktion) |

Löschen/Zusammenlegen der Planungsdateien lohnt erst vor Open-Source oder wenn die Docs klar divergieren. Für den Alltag: **README = Übergabe**, **PROJECT.md = Kompass**, **refactoring-plan.md = Bereinigungs-Historie**, **adapter-notes.md = unberührt**.

---

## Kurz testen

1. Module → ProcessEndLinkageEditorial installiert / Cache ok  
2. Setup → Redaktion: Tabs durchgehen (Inhalte & Navigation · Design & Branding · Zugriff & System), speichern, Branding setzen  
3. User mit Rolle `editorial` oder Superuser → `/editorial/`  
4. Template mit Daten-Art „Daten“: Broadsheet-Design prüfen; anderes Template (z. B. Blog): generische Ansicht  
5. Navigationsfläche auf Mobile über den Hamburger öffnen/schließen; Publish und Bildfeld-Vorschau prüfen  
6. Repeater-Feld anlegen: Item hinzufügen/entfernen/bearbeiten inkl. Bild-Upload prüfen  
7. Optional: nicht unterstützter Feldtyp → Hinweis in Discovery-Tabelle und Formular-Platzhalter  

### Deploy-Hinweis (Linux / Subdomain)

- Modulordner-Name darf von `bs-processEditorial` abweichen — Asset-URLs kommen aus dem realen Pfad.
- Nach fehlgeschlagener Erstinstallation: neu installieren **oder** unter Access die Permission `editorial-access` und Rolle `editorial` prüfen/anlegen.
- CSS prüfen: View-Source der Login-Seite → Link muss auf den **tatsächlichen** Modulordner zeigen.

### Releases (GitHub Actions)

- `.github/workflows/release.yml` erstellt bei jedem Push auf `master` automatisch Tag `vX` + GitHub-Release, sobald die `version` in der `.module.php`-Hauptdatei (per Glob gefunden, aktuell `ProcessEndLinkageEditorial.module.php`) erhöht wurde (kein Tag `vX` vorhanden → neuer Release).
- Der Workflow schreibt dabei `release-info.json` (Version + UTC-Datum) zurück ins Repo — diese Datei liefert dem Setup-Footer im Modul das tatsächliche Release-Datum. Ohne passenden Tag zeigt der Footer „unveröffentlicht".
- Manuell auslösen: GitHub → Actions → „Release" → „Run workflow".
