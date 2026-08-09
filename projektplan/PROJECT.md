# bs-processData

## Positionierung

bs-processData ist eine generische Redaktionsoberfläche für ProcessWire, die sich
automatisch aus dem vorhandenen Datenmodell aufbaut und Redakteuren ausschließlich
die Funktionen zeigt, die sie für ihre tägliche Arbeit benötigen.

Es ist **kein alternatives Admin-Theme** und **kein** Ersatz für den
ProcessWire-Adminbereich. Entwickler arbeiten weiterhin ganz normal im
ProcessWire-Backend (Templates, Felder, Beziehungen, Seiten). Redakteure sehen
diesen Bereich nie — sie bekommen eine eigene, separate Frontend-Anwendung mit
eigenem Login.

## Architektur (4 Schichten)

```
┌─────────────────────────────┐
│      Editorial UI            │  Navigation, Listen, Formulare
└──────────────┬───────────────┘
┌──────────────▼───────────────┐
│     UI / Form-Engine          │  Komponenten (TextInput, ImagePicker,
│                                │  ReferencePicker, ...) — kennen PW nicht,
│                                │  bekommen nur { type, required, label, ... }
└──────────────┬───────────────┘
┌──────────────▼───────────────┐
│    ProcessWire-Adapter        │  liest Templates/Fields/Pages über PW-API,
│                                │  übersetzt in das Komponenten-Schema
└──────────────┬───────────────┘
┌──────────────▼───────────────┐
│        ProcessWire            │  Datenmodell & Speicherung (unverändert)
└────────────────────────────────┘
```

Die UI-Schicht kennt ProcessWire nicht — nur den Adapter. Der Adapter kennt nur
die öffentliche ProcessWire-API.

## Tech-Stack

- PHP (ProcessWire API)
- Vanilla JavaScript, kein Framework
- SCSS
- Wird von Anfang an als ProcessWire-Modul entwickelt (installierbar in jeder
  PW-Installation)

## MVP-Scope (aktuelle Phase)

**Enthalten:**
- Genau 1 Template als Testfall
- Genau 6 Feldtypen: Text, Textarea, Checkbox, Select, Bild, Page Reference
- Listenansicht (einfach) + Formular (Erstellen/Bearbeiten)
- Eigenes Login für Redakteure, getrennt vom PW-Admin-Login
- Fokus der nächsten Wochen: **Benutzererlebnis**, mit Mock-Daten entwickelt
  (siehe `schema-mock.json`), bevor der echte PW-Adapter final steht

**Explizit NICHT im MVP** (bewusst auf spätere Phasen verschoben):
- Dashboard, Suche, Filter, Mehrfachaktionen
- Medienverwaltung (über einfaches Bildfeld hinaus)
- Rechteverwaltung feldgranular (v1: nur grob Rolle → sichtbare Templates)
- Mehrsprachige Redaktionsoberfläche
- Revisionen
- Workflow (Entwurf → Prüfung → Freigabe → Veröffentlichen) — relevant für
  AWO-Kontext, aber Phase 2/3, nicht Phase 1
- Erweiterungssystem / Plugin-Architektur für zusätzliche Feldtypen — erst wenn
  ein konkreter zweiter Bedarf existiert

## Roadmap (Übersicht)

1. **MVP**: Modulstruktur, eigene Administration, Formulargenerator (6 Feldtypen),
   Rechte grob übernehmen
2. Dashboard, Suche, Filter, Schnellbearbeitung, Medienintegration, Responsive Design
3. Widgets, Favoriten, Revisionen, mehrsprachige Oberfläche, Workflow
4. Open-Source-Veröffentlichung, Dokumentation, Entwickler-API, Erweiterungssystem

## Leitplanken für die Entwicklung mit Cursor

- Kein Build-Step, keine CSS-Frameworks — reines PHP/JS/SCSS
- Komponenten der UI/Form-Engine bekommen nur Schema-Daten, nie PW-Objekte direkt
- Bei Unsicherheit: MVP-Scope oben ist die Referenz — nicht erweitern ohne
  bewusste Entscheidung
