# Navigation

## Entwickler-Menühierarchie

Die Redaktionsnavigation ist **nicht** der ProcessWire-Seitenbaum.
Unter **Setup → Redaktion** definiert der Entwickler eine Menühierarchie
(`editorial_nav` JSON):

- `dashboard` — globale Übersicht (Rail)
- `section` — Rail-Modul mit Kindern im mittleren Baum
- `group` — frei benannter Ordner
- `template` — Bindung an ein freigegebenes Template

Icons: Lucide-Namen (kuratiertes Subset, siehe Setup-Beschreibung).

## Hybrid-Baum

Unter einem Listen-Inhaltstyp erscheinen einzelne Datensätze nur, wenn
höchstens 50 Einträge vorhanden sind. Sonst öffnet der Typ die Liste /
das Dashboard in der Mitte.

## Dashboard zuerst

Rail-„Übersicht“, Sections und Groups öffnen eine Übersicht mit
Schnellzugriff-Kacheln und „Zuletzt bearbeitet“. Liste und Formular
erst bei Typ- bzw. Datensatz-Klick.

## Anti-Pattern

Kein roher Seiten-/Ordnerbaum 1:1 aus dem Backend (inkl. Systemordner).
Redakteure sollen Aufgaben und Inhaltstypen sehen, nicht das interne
Datenmodell.
