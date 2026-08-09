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

## ⚠️ Vor der nächsten Session zuerst lesen

**Offene Grundsatzentscheidung:** Der heutige Umfang (13 statt der ursprünglich für den MVP geplanten 6 Feldtypen, plus Menü-Builder, Theme-Tokens, Branding, Dashboard) geht deutlich über den ursprünglichen Plan hinaus. Der Menü-Builder ist eine bewusste, sinnvolle Entscheidung (Entwickler soll Navigation/Hierarchie pro Kundenprojekt selbst konfigurieren können — nicht das System pauschal vorgeben). Für die restliche Erweiterung steht noch eine bewusste Entscheidung aus: aktuellen Umfang als "MVP+" einfrieren, oder auf die ursprünglichen 6 Feldtypen zurückschneiden? Erst danach mit Punkt 1 der Offen-Liste weitermachen.

**Konkreter Sicherheitsfund (kein Zukunftsthema, bereits ausgeliefert):** Der SVG-Logo-Upload im Setup (`BsProcessEditorial.module.php:307-355`) prüft nur die Dateiendung, keinen Inhalt, und legt die Datei unter einer öffentlich erreichbaren URL ab — klassisches Stored-XSS-Risiko über SVG. Zugriff ist zwar auf Superuser im Setup begrenzt, aber das ist kein "laufend prüfen"-Punkt mehr, sondern ein konkreter Fix vor allem anderen.

**Zwei kleinere technische Schulden:**
- `src/Ui/Router.php:5,336,536` importiert die konkrete Klasse `ProcessWireAdapter` und prüft direkt darauf (Publish-Block-Anzeige, Quell-Label). Die UI-Schicht sollte laut Architekturregel nur gegen `AdapterInterface` arbeiten — sauberer wäre eine Interface-Methode wie `supportsPublish()` / `sourceLabel()`.
- `src/FormEngine/Fields/AbstractFieldRenderer.php:22` markiert Pflichtfelder nur mit `*` + `title`-Tooltip — auf Touch-Geräten nicht sichtbar. Sollte um sichtbaren Text ergänzt werden.

---

## Umgesetzt (Stand 10.08.2026)

### Kern & Zugang
- Process-Modul, Autoload, Setup-Seite **Setup → Redaktion**
- Frontend unter konfigurierbarem Pfad (`editorial`)
- Login gegen PW-User; Permission `editorial-access`, Rolle `editorial`
- Superuser immer erlaubt; optional Demo-Login (Setup)
- Session getrennt vom Admin-Login
- Grobe Rechte: Rolle → sichtbare Templates

### Setup / Entwickler
- Template-Discovery + Freigabe (`editorial_templates`)
- Darstellungsmodus pro Template: Liste | Einzelseite
- Visueller **Menü-Builder** (Sections / Gruppen / Templates / Icons) + JSON-Fallback — bewusste Entscheidung, damit der Entwickler Navigation/Hierarchie pro Kundenprojekt selbst festlegen kann, statt einer starren Systemvorgabe
- Theme-Tokens (Akzent, Rail, Radius) inkl. Farbpicker
- Kunden-Branding: Firmenname + Logo (siehe Sicherheitsfund oben — Upload-Härtung noch offen)
- Datenquelle: auto / ProcessWire / Mock

### Redaktions-UX
- 4-Spalten-Shell: Icon-Rail (Toggle + Tooltips) | Inhaltsbaum | Header/Main/Footer
- Dashboard (Schnellzugriff, zuletzt bearbeitet)
- Baum: nur Gruppen/Typen + Anzahl — keine Einzelsätze
- Listen + Formulare; Details-Sidebar; Publish (veröffentlicht / Entwurf)
- Vorschau-Link (Frontend-URL), Flash-Feedback

### Feldtypen (Adapter + Form-Engine)
text, textarea, html/TinyMCE, email, url, integer, float, datetime, checkbox, select, image, file, pageReference

Die ursprünglichen 6 (text, textarea, checkbox, select, image, pageReference) sind gegen `adapter-notes.md` recherchiert und abgesichert. Die 7 zusätzlichen (email, url, integer, float, datetime, file, html/TinyMCE) haben kein vergleichbares Recherche-Fundament und sind Teil des zuletzt ungetesteten Commits — vor Weiterbau einmal end-to-end gegen eine echte PW-Instanz testen (siehe "Kurz testen" unten, mit Fokus auf diese 7 Typen).

### Architektur-Dateien (Orientierung)
- `BsProcessEditorial.module.php` — Modul, Setup, Config
- `src/Ui/` — Router, Shell, Dashboard, Liste, Nav
- `src/FormEngine/` — Formular-UI
- `src/Adapter/` — PW- & Mock-Adapter, Feld-Adapter
- `src/Auth/`, `src/Setup/` — Login, Rechte, NavConfig/NavBuilder
- `projektplan/` — Planung & Notizen (siehe unten)

---

## Sicherheit (Ist & später)

Der Redaktionsbereich **ändert echte Inhalte** — Sicherheit ist Pflicht vor Kunden-Go-Live, aber kein Blocker für die UX-Arbeit morgen. Ausnahme: der SVG-Upload-Fund oben, der ist bereits ausgeliefert, kein reiner Roadmap-Punkt mehr.

### Bereits vorhanden
- Login nur mit gültigem PW-User + Passwort (`$session->authenticate`)
- Zugang nur mit Permission `editorial-access` (oder Superuser)
- CSRF-Token auf Login und Formularen
- Editorial-Session getrennt vom Admin-Login (kein `$session->login()`)
- Demo-Login standardmäßig aus
- Sichtbarkeit der Inhaltstypen über Rollen-Mapping filterbar

### Betrieb (nicht Modul-Code, aber nötig)
- **HTTPS** für `/editorial/`
- Starke Passwörter / PW-User-Hygiene
- Demo-Login produktiv nie aktivieren
- Server-/Hosting-Härtung wie für die restliche Site

### Später im Modul / Konzept (Roadmap)
| Thema | Warum | Priorität |
|-------|--------|-----------|
| **Upload-Härtung (MIME, SVG-Inhaltsprüfung, Dateigrößen zentral)** | Logo-Upload bereits betroffen (siehe Fund oben), auch Datei-/Bildfelder | **hoch — konkret, nicht nur präventiv** |
| Login-Rate-Limit / Lockout nach Fehlversuchen | Brute-Force auf `/editorial/login` | hoch vor Go-Live |
| Session-Timeout / „Angemeldet bleiben“-Policy | Offene Redakteurs-PCs | hoch vor Go-Live |
| Feinere Rechte (anlegen / löschen / nur bearbeiten) | Weniger Schaden bei kompromittiertem Account | mittel |
| Audit-Log (wer speicherte/publishte was) | Nachvollziehbarkeit bei Kunden | mittel |
| **2FA (TOTP o. ä.)** | Extra Schutz für Publish-Zugang; oft Kundenanforderung | mittel–hoch je nach Kunde |

**Brauchen wir ein Sicherheitskonzept?** Ja — als kurze Checkliste (Ist/Soll/Go-Live), kein Roman. Liegt sinnvoll **vor erstem Kunden-Produktivbetrieb**, parallel zu Rechte/Löschen.

**Brauchen wir 2FA?** Nicht für den nächsten Dev-Sprint. Für produktive Kundeninstanzen mit öffentlichen oder sensiblen Inhalten: **ja, einplanen** (z. B. TOTP nach erfolgreichem Passwort; optional PW-Module/`LoginRegister`/`TwoFactorAuth`-Ökosystem prüfen, sonst eigenes TOTP im Editorial-Login). 2FA ersetzt nicht Rate-Limit und HTTPS.

---

## Offen — Reihenfolge für den nächsten Chat

0. **Entscheidung treffen:** aktuellen Funktionsumfang als "MVP+" einfrieren oder zurückschneiden — siehe Hinweis ganz oben
1. SVG-Upload-Härtung (siehe Sicherheitsfund oben)
2. Router.php: Adapter-Kopplung durch Interface-Methode ersetzen
3. Pflichtfeld-Kennzeichnung um sichtbaren Text ergänzen
4. Die 7 zusätzlichen Feldtypen end-to-end gegen echte PW-Instanz testen
5. **Listen: Suche / Filter / Sortierung**
6. **Löschen / Papierkorb** für Datensätze
7. **Medienbibliothek** (statt nur Upload am Feld)
8. **Autosave** + robusteres Speichern-Feedback
9. **Zeitplanung** veröffentlichen (aktuell Stub)
10. **Rechte feiner** (anlegen/löschen vs. nur bearbeiten)
11. **Sicherheit Go-Live:** Rate-Limit/Lockout, Session-Timeout, kurze Security-Checkliste
12. **2FA** (TOTP) für Editorial-Login — vor/mit Kunden-Produktiv
13. **Workflow** jenseits Publish/Entwurf
14. **Weitere Feldtypen** bei Bedarf: Repeater, Combo, …
15. Repo öffentlich / Doku für Dritte (wenn gewünscht)

Einstieg morgen: dieses README lesen, dann `projektplan/PROJECT.md`. Code starten bei Punkt 1 (SVG-Härtung) oder — falls die MVP+-Entscheidung zuerst ansteht — mit dem Gespräch darüber.

---

## Projektdateien (`projektplan/`) — Aufräumen?

**Empfehlung: nicht groß aufräumen, nur den Stand halten.**

| Datei | Umgang |
|-------|--------|
| `adapter-notes.md` | **Nicht ändern** (Referenz wie vereinbart) |
| `PROJECT.md` | Aktueller Kurzstand — bei Meilensteinen nachziehen |
| `design/*.md` (admin-editorial-ux-Skill) | **Generisch halten** — nicht mehr projektspezifisch überschreiben. Konkrete Umsetzungsdetails (z. B. Menü-Builder/`editorial_nav`-JSON) gehören in eine eigene Datei, z. B. `nav-builder-implementation.md`, nicht in die generische Referenz |
| `schema-mock.json` | Mock-Referenz; nur anfassen wenn Mock-Felder erweitert werden |

Löschen/Zusammenlegen der Planungsdateien lohnt erst vor Open-Source oder wenn die Docs klar divergieren. Für den Alltag reicht: **README = Übergabe**, **PROJECT.md = Kompass**, **adapter-notes.md = unberührt**.

---

## Kurz testen

1. Module → BsProcessEditorial installiert / Cache ok
2. Setup → Redaktion: Templates freigeben, Menü speichern, Branding setzen
3. User mit Rolle `editorial` oder Superuser → `/editorial/`
4. Inhaltstyp öffnen, speichern, Publish prüfen
5. **Neu:** die 7 zusätzlichen Feldtypen (email, url, integer, float, datetime, file, html) einzeln gegen ein echtes PW-Feld testen — bisher ungetestet