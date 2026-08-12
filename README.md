# bs-processEditorial

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

URL-App typisch: `/editorial/` · Modulversion: siehe `BsProcessEditorial.module.php`.

---

## Nach der Bereinigungsphase

Die Stabilisierungsarbeit (Mock/Testdaten entfernen, Logo-Upload absichern, native Modul-Config konsolidieren, Transparenz für fehlende Feld-Adapter, Setup-Formular gliedern) ist abgeschlossen. Verlauf und Akzeptanzkriterien: **`projektplan/refactoring-plan.md`** (Historie, nicht mehr die Aufgabenliste für „was als Nächstes“).

**Feldtypen-Zielrichtung:** keine feste Obergrenze — schrittweise Abdeckung der ProcessWire-**Standard**feldtypen (ProFields wie RepeaterMatrix/Table ausgenommen; der Core-**Repeater** zählt dazu und fehlt noch). Neue Typen kommen als isolierte Adapter-Klassen bei Bedarf.

Einstieg für Weiterentwicklung: dieses README (Offen-Liste unten), bei Architekturfragen `projektplan/PROJECT.md`.

---

## Umgesetzt (Stand 12.08.2026)

### Kern & Zugang
- Process-Modul, Autoload, Setup-Seite **Setup → Redaktion**
- Nativer Modul-Konfigurationsbildschirm: nur Hinweis + Link auf Setup → Redaktion (keine doppelten Einstellungsfelder)
- Frontend unter konfigurierbarem Pfad (`editorial`)
- Login gegen PW-User; Permission `editorial-access`, Rolle `editorial`
- Superuser immer erlaubt; optional Demo-Login (Setup)
- Session getrennt vom Admin-Login
- Grobe Rechte: Rolle → sichtbare Templates

### Setup / Entwickler
- Setup-Formular in **4 Tabs** (ProcessWire `WireTab`):
  1. **Inhalte & Freigabe** — Discovery-Tabelle, Template-Freigabe, Darstellungsmodus, Daten-Art
  2. **Navigation** — visueller Menü-Builder + JSON-Fallback
  3. **Design & Branding** — Firmenname, Logo, Theme-Tokens
  4. **Zugriff & System** — Rollen-Mapping, Demo-Login, URL-Pfad
- Template-Discovery + Freigabe (`editorial_templates`)
- Darstellungsmodus pro Template: Liste | Einzelseite
- **Daten-Art** pro Template: Daten (Default) · Blog · News · Termine · Seiten — steuert die redaktionelle Ansicht; nur „Daten“ hat derzeit die spezialisierte Broadsheet-Oberfläche
- **Ohne Adapter:** Spalte in der Discovery-Tabelle (Feldname + PW-Feldtyp)
- Visueller **Menü-Builder** (Sections / Gruppen / Templates / Icons) + JSON-Fallback
- Theme-Tokens: Akzent, Rail, Text, gedämpfte Textfarbe, Radius, Abstände (kompakt/normal/großzügig) inkl. Farbpicker
- Kunden-Branding: Firmenname + Logo (PNG/JPG/GIF/WebP, Content-Check; **kein SVG**)
- Datenquelle: ausschließlich ProcessWire (kein Mock-Adapter mehr)
- Geister-Templates: leere Defaults; Nav/Routen nur bei existierendem Template

### Redaktions-UX
- 4-Spalten-Shell: Icon-Rail (Toggle) | Inhaltsbaum (einklappbar) | Header/Main/Footer
- **Broadsheet-Design** (Open Sans, Mockup-Komponenten) für Templates mit Daten-Art „Daten“ — Dashboard, Liste, Formular
- Andere Daten-Arten: generische Liste/Formular (unverändert, ohne Fehler)
- Dashboard (Schnellzugriff, zuletzt bearbeitet)
- Baum: Gruppen/Typen + Anzahl
- Listen + Formulare; Details-Sidebar; Publish (veröffentlicht / Entwurf)
- Felder ohne Adapter: Platzhalter im Formular
- Bildfelder: Thumbnail-Vorschau
- Vorschau-Link (Frontend-URL), Flash-Feedback

### Feldtypen (Adapter + Form-Engine)

Aktuell implementiert (fortlaufend erweiterbar, kein festes Kontingent):

text · textarea · html/TinyMCE · email · url · integer · float · datetime · checkbox · select · image · file · pageReference

**Nächster bekannter Kandidat (noch nicht gebaut):** Core-Repeater. Weitere Standard-Feldtypen bei Bedarf als einzelne Adapter-Klassen.

### Architektur-Dateien (Orientierung)
- `BsProcessEditorial.module.php` — Modul, Setup, Config
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
- Demo-Login standardmäßig aus
- Sichtbarkeit der Inhaltstypen über Rollen-Mapping filterbar
- Branding-Logo: kein SVG, Content-Check (`getimagesize`) für Rasterformate, Legacy-SVG-Purge

### Betrieb (nicht Modul-Code, aber nötig)
- **HTTPS** für `/editorial/`
- Starke Passwörter / PW-User-Hygiene
- Demo-Login produktiv nie aktivieren
- Server-/Hosting-Härtung wie für die restliche Site

### Später im Modul / Konzept (Roadmap)
| Thema | Warum | Priorität |
|-------|--------|-----------|
| Login-Rate-Limit / Lockout nach Fehlversuchen | Brute-Force auf `/editorial/login` | hoch vor Go-Live |
| Session-Timeout / „Angemeldet bleiben“-Policy | Offene Redakteurs-PCs | hoch vor Go-Live |
| Feinere Rechte (anlegen / löschen / nur bearbeiten) | Weniger Schaden bei kompromittiertem Account | mittel |
| Audit-Log (wer speicherte/publishte was) | Nachvollziehbarkeit bei Kunden | mittel |
| **2FA (TOTP o. ä.)** | Extra Schutz für Publish-Zugang; oft Kundenanforderung | mittel–hoch je nach Kunde |
| Weitere Upload-Härtung an Feld-Adaptern (falls nötig über WireUpload hinaus) | Logo-Pfad ist abgesichert; Bild/Datei laufen über PW `WireUpload` | niedrig–mittel |

**Brauchen wir ein Sicherheitskonzept?** Ja — als kurze Checkliste (Ist/Soll/Go-Live), kein Roman. Liegt sinnvoll **vor erstem Kunden-Produktivbetrieb**, parallel zu Rechte/Löschen.

**Brauchen wir 2FA?** Nicht für den nächsten Dev-Sprint. Für produktive Kundeninstanzen mit öffentlichen oder sensiblen Inhalten: **ja, einplanen** (z. B. TOTP nach erfolgreichem Passwort; optional PW-Module/`TwoFactorAuth`-Ökosystem prüfen). 2FA ersetzt nicht Rate-Limit und HTTPS.

---

## Offen — Reihenfolge für den nächsten Chat

1. **Listen: Suche / Filter / Sortierung**
2. **Löschen / Papierkorb** für Datensätze
3. **Medienbibliothek** (statt nur Upload am Feld)
4. **Autosave** + robusteres Speichern-Feedback
5. **Zeitplanung** veröffentlichen (aktuell Stub)
6. **Rechte feiner** (anlegen/löschen vs. nur bearbeiten)
7. **Sicherheit Go-Live:** Rate-Limit/Lockout, Session-Timeout, kurze Security-Checkliste
8. **2FA** (TOTP) für Editorial-Login — vor/mit Kunden-Produktiv
9. **Workflow** jenseits Publish/Entwurf
10. **Daten-Arten** Blog/News/Termine/Seiten — eigene Ansichten (Design steht als Mockup bereit)
11. **Weitere Feldtypen** bei Bedarf — zuerst typischerweise **Repeater** (Core)
12. Aufteilung großer Klassen (`Router`, Modul-Datei) / Adapter-Tests
13. Repo öffentlich / Doku für Dritte (wenn gewünscht)

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

1. Module → BsProcessEditorial installiert / Cache ok  
2. Setup → Redaktion: Tabs durchgehen (Inhalte · Navigation · Design · System), speichern, Branding setzen  
3. User mit Rolle `editorial` oder Superuser → `/editorial/`  
4. Template mit Daten-Art „Daten“: Broadsheet-Design prüfen; anderes Template (z. B. Blog): generische Ansicht  
5. Inhalte-Spalte ein-/ausklappen; Publish und Bildfeld-Vorschau prüfen  
6. Optional: nicht unterstützter Feldtyp → Hinweis in Discovery-Tabelle und Formular-Platzhalter  

### Deploy-Hinweis (Linux / Subdomain)

- Modulordner-Name darf von `bs-processEditorial` abweichen — Asset-URLs kommen aus dem realen Pfad.
- Nach fehlgeschlagener Erstinstallation: neu installieren **oder** unter Access die Permission `editorial-access` und Rolle `editorial` prüfen/anlegen.
- CSS prüfen: View-Source der Login-Seite → Link muss auf den **tatsächlichen** Modulordner zeigen.
