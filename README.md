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
- Visueller **Menü-Builder** (Sections / Gruppen / Templates / Icons) + JSON-Fallback
- Theme-Tokens (Akzent, Rail, Radius) inkl. Farbpicker
- Kunden-Branding: Firmenname + Logo
- Datenquelle: auto / ProcessWire / Mock

### Redaktions-UX
- 4-Spalten-Shell: Icon-Rail (Toggle + Tooltips) | Inhaltsbaum | Header/Main/Footer
- Dashboard (Schnellzugriff, zuletzt bearbeitet)
- Baum: nur Gruppen/Typen + Anzahl — keine Einzelsätze
- Listen + Formulare; Details-Sidebar; Publish (veröffentlicht / Entwurf)
- Vorschau-Link (Frontend-URL), Flash-Feedback

### Feldtypen (Adapter + Form-Engine)
text, textarea, html/TinyMCE, email, url, integer, float, datetime, checkbox, select, image, file, pageReference

### Architektur-Dateien (Orientierung)
- `BsProcessEditorial.module.php` — Modul, Setup, Config
- `src/Ui/` — Router, Shell, Dashboard, Liste, Nav
- `src/FormEngine/` — Formular-UI
- `src/Adapter/` — PW- & Mock-Adapter, Feld-Adapter
- `src/Auth/`, `src/Setup/` — Login, Rechte, NavConfig/NavBuilder
- `projektplan/` — Planung & Notizen (siehe unten)

---

## Sicherheit (Ist & später)

Der Redaktionsbereich **ändert echte Inhalte** — Sicherheit ist Pflicht vor Kunden-Go-Live, aber kein Blocker für die UX-Arbeit morgen.

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
| Login-Rate-Limit / Lockout nach Fehlversuchen | Brute-Force auf `/editorial/login` | hoch vor Go-Live |
| Session-Timeout / „Angemeldet bleiben“-Policy | Offene Redakteurs-PCs | hoch vor Go-Live |
| Feinere Rechte (anlegen / löschen / nur bearbeiten) | Weniger Schaden bei kompromittiertem Account | mittel |
| Audit-Log (wer speicherte/publishte was) | Nachvollziehbarkeit bei Kunden | mittel |
| **2FA (TOTP o. ä.)** | Extra Schutz für Publish-Zugang; oft Kundenanforderung | mittel–hoch je nach Kunde |
| Upload-Härtung (MIME, SVG, Dateigrößen zentral) | Datei-/Bildfelder | laufend prüfen |

**Brauchen wir ein Sicherheitskonzept?** Ja — als kurze Checkliste (Ist/Soll/Go-Live), kein Roman. Liegt sinnvoll **vor erstem Kunden-Produktivbetrieb**, parallel zu Rechte/Löschen.

**Brauchen wir 2FA?** Nicht für den nächsten Dev-Sprint. Für produktive Kundeninstanzen mit öffentlichen oder sensiblen Inhalten: **ja, einplanen** (z. B. TOTP nach erfolgreichem Passwort; optional PW-Module/`LoginRegister`/`TwoFactorAuth`-Ökosystem prüfen, sonst eigenes TOTP im Editorial-Login). 2FA ersetzt nicht Rate-Limit und HTTPS.

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
10. **Weitere Feldtypen** bei Bedarf: Repeater, Combo, …
11. Repo öffentlich / Doku für Dritte (wenn gewünscht)

Einstieg morgen: dieses README lesen, dann `projektplan/PROJECT.md`. Code starten bei Listen-UX (`src/Ui/ListView.php`, `Router.php`) oder Löschen im Adapter.

---

## Projektdateien (`projektplan/`) — Aufräumen?

**Empfehlung: nicht groß aufräumen, nur den Stand halten.**

| Datei | Umgang |
|-------|--------|
| `adapter-notes.md` | **Nicht ändern** (Referenz wie vereinbart) |
| `PROJECT.md` | Aktueller Kurzstand — bei Meilensteinen nachziehen |
| `design/*.md` | UX-Muster behalten; bei Bedarf punktuell anpassen (z. B. Navigation ohne Einzelsätze im Baum) |
| `schema-mock.json` | Mock-Referenz; nur anfassen wenn Mock-Felder erweitert werden |

Löschen/Zusammenlegen der Planungsdateien lohnt erst vor Open-Source oder wenn die Docs klar divergieren. Für den Alltag reicht: **README = Übergabe**, **PROJECT.md = Kompass**, **adapter-notes.md = unberührt**.

---

## Kurz testen

1. Module → BsProcessEditorial installiert / Cache ok  
2. Setup → Redaktion: Templates freigeben, Menü speichern, Branding setzen  
3. User mit Rolle `editorial` oder Superuser → `/editorial/`  
4. Inhaltstyp öffnen, speichern, Publish prüfen  
