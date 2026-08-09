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
│      Editorial UI            │  Navigation, Listen, Formulare
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
- SCSS (ohne Build-Step; CSS wird ausgeliefert)
- Installierbar als ProcessWire-Modul

## Stand (Aug 2026)

**Erledigt (MVP+):**
- Modul + URL-App unter `/editorial/`
- Setup → Redaktion (Freigabe, Datenquelle, Darstellungsmodus)
- PW-Adapter + Mock-Adapter
- 6 Feldtypen: Text, Textarea (Plain), Checkbox, Select, Bild, Page Reference
- Listenansicht + Formular (Erstellen/Bearbeiten)
- Template-Discovery + Freigabe mehrerer Inhaltstypen
- Darstellungsmodus **Datensätze (Liste)** vs. **Einzelseite** (z. B. Home)
- Login gegen echte PW-User (Permission `editorial-access`, Rolle `editorial`)
- Grobe Rechte: Rolle → sichtbare Inhaltstypen
- Optionaler Demo-Login (Setup-Schalter, standardmäßig aus)
- UX-Basics: Nav-Modus-Hinweis, Flash dismiss, Listen-Meta, Empty States

**Als Nächstes:**
1. **Überarbeitung der Redaktions-UX** (Visuelles Design, Mobile, Tonalität — Fokus-Sprint)
2. TinyMCE / HTML-Textarea, wenn in Feldern gesetzt
3. Phase-2-Themen: Suche, Filter, Dashboard, Medienbibliothek

**Später (Roadmap Phase 2–4):**
- Dashboard, Schnellbearbeitung, Responsive Feinschliff
- Widgets, Favoriten, Revisionen, mehrsprachige Oberfläche, Workflow
- Open-Source, Dokumentation, Entwickler-API, Erweiterungssystem

## Feldtypen (aktuell)

| Editorial | ProcessWire |
|-----------|-------------|
| text | FieldtypeText, FieldtypePageTitle |
| textarea | FieldtypeTextarea (Plaintext) |
| checkbox | FieldtypeCheckbox |
| select | FieldtypeOptions |
| image | FieldtypeImage |
| pageReference | FieldtypePage |

Nicht abgedeckt u. a.: TinyMCE/HTML, Datetime, Integer, Email, URL, File, Repeater.

## Leitplanken

- Kein Build-Step, keine CSS-Frameworks — PHP/JS/SCSS
- UI/Form-Engine nur Schema-Daten, nie PW-Objekte
- Freigabe und Darstellungsmodus im PW-Setup, nicht hardcodiert in der UI
- Bei Unsicherheit: dieses Dokument ist die Referenz
