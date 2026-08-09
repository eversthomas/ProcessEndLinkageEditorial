# bs-processEditorial

## Positionierung

bs-processEditorial ist eine generische Redaktionsoberfläche für ProcessWire, die sich
aus dem vorhandenen Datenmodell aufbaut und Redakteuren ausschließlich die Funktionen
zeigt, die sie für ihre tägliche Arbeit benötigen.

Es ist **kein alternatives Admin-Theme** und **kein** Ersatz für den
ProcessWire-Adminbereich. Entwickler arbeiten weiterhin im ProcessWire-Backend
(Templates, Felder, Beziehungen, Seiten) und konfigurieren die Redaktion unter
**Setup → Redaktion**. Redakteure sehen den PW-Admin nie — sie bekommen eine eigene
Frontend-Anwendung mit eigenem Login.

## Architektur (4 Schichten)

```
┌─────────────────────────────┐
│      Editorial UI            │  Shell, Dashboard, Listen, Formulare
└──────────────┬───────────────┘
┌──────────────▼───────────────┐
│     UI / Form-Engine          │  Komponenten — kennen PW nicht,
│                                │  bekommen nur Schema-Daten
└──────────────┬───────────────┘
┌──────────────▼───────────────┐
│    ProcessWire-Adapter        │  Templates/Fields/Pages → Schema
└──────────────┬───────────────┘
┌──────────────▼───────────────┐
│        ProcessWire            │  Datenmodell & Speicherung
└────────────────────────────────┘
```

## Tech-Stack

- PHP (ProcessWire API)
- Vanilla JavaScript, kein Framework
- CSS ohne Build-Step; Lucide-Icons (MIT) inline
- TinyMCE aus PW-Core (`InputfieldTinyMCE`), wenn HTML-Felder vorhanden
- Installierbar als ProcessWire-Modul

## Stand (Aug 2026)

**Erledigt (MVP+ / UX-Shell):**
- Modul + URL-App unter `/editorial/`
- Setup → Redaktion: Freigabe, Modi, **Menühierarchie (JSON)**, Theme-Tokens
- 4-Spalten-Shell: Icon-Rail (Toggle + Tooltips) | Inhaltsbaum | Header/Main/Footer
- Dashboard-Übersichten; Baum nur Gruppen/Typen (Anzahl-Badge, keine Einzelsätze)
- PW-Adapter + Mock-Adapter
- Feldtypen: Text, Textarea (Plain + HTML/TinyMCE), Checkbox, Select, Bild, Page Reference
- Publish-MVP (veröffentlicht / Entwurf)
- Login gegen PW-User (`editorial-access` / Rolle `editorial`)
- Grobe Rechte: Rolle → sichtbare Inhaltstypen

**Als Nächstes:**
1. Medienbibliothek, Zeitplanung, Autosave
2. Visueller Menü-Builder (statt reinem JSON)
3. Phase-2: Suche, Filter, Workflow-Stufen

## Feldtypen (aktuell)

| Editorial | ProcessWire |
|-----------|-------------|
| text | FieldtypeText, FieldtypePageTitle |
| textarea | FieldtypeTextarea (Plaintext) |
| html | FieldtypeTextarea (HTML / TinyMCE / CKEditor) |
| checkbox | FieldtypeCheckbox |
| select | FieldtypeOptions |
| image | FieldtypeImage |
| pageReference | FieldtypePage |

Nicht abgedeckt u. a.: Datetime, Integer, Email, URL, File, Repeater.

## Leitplanken

- Kein Build-Step, keine CSS-Frameworks — PHP/JS/CSS
- UI/Form-Engine nur Schema-Daten, nie PW-Objekte
- Freigabe, Menü und Darstellungsmodus im PW-Setup, nicht hardcodiert in der UI
- Bei Unsicherheit: dieses Dokument ist die Referenz
