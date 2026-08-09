---
name: admin-editorial-ux
description: UX-/UI-Muster für datengetriebene Redaktions- und Admin-Oberflächen (Navigation, Listenansichten, Formulare, Dashboards, Workflow-Status) — unabhängig von konkretem Datenmodell, Tech-Stack oder CMS.
---

# Admin & Editorial UX — Muster-Referenz

Dieser Skill sammelt wiederverwendbare UX-Muster für Werkzeuge, in denen
Nicht-Entwickler:innen strukturierte Inhalte pflegen — unabhängig davon,
welches System im Hintergrund die Daten hält (ProcessWire, WordPress,
eigene DB, ...). Er trifft keine Aussage über konkrete Feldtypen oder
Entitäten eines bestimmten Projekts.

## Grundprinzip

Zielgruppe ist immer die Person, die Inhalte pflegt — nicht die Person,
die das Datenmodell entworfen hat. Jedes Muster hier optimiert für:
verstehen ohne Erklärung, wenig Klicks für die häufigste Aufgabe, keine
Systemkonzepte (Schema, Struktur, IDs) sichtbar machen, die für die
tägliche Arbeit irrelevant sind.

## Referenzen

- `reference/navigation.md` — wann eine flache Liste reicht und wann
  Hierarchie legitim ist; Vermeidung von Entwickler-Konzepten in der Nav
- `reference/list-views.md` — Tabellen, Filter, Schnellaktionen,
  Pagination für Datensatz-Listen
- `reference/forms.md` — Feldgruppierung, Inline-Validierung, Autosave,
  Trennung Inhalt vs. Workflow-Kontrolle
- `reference/dashboards.md` — Startansicht: zuletzt bearbeitet,
  Schnellzugriff, Widgets
- `reference/workflow-states.md` — Entwurf/Prüfung/Freigabe-Muster,
  Statusanzeigen

## Wann dieser Skill NICHT reicht

Für alles, was sich auf ein konkretes Datenmodell oder eine konkrete
API bezieht (z. B. "wie lese ich ein ProcessWire-Select-Feld aus"),
gehört das Wissen in einen separaten, systemspezifischen Skill
(z. B. `processwire-core`), nicht hierher.
