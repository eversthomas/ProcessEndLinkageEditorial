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

## Inhaltsbaum ohne Einzelsätze

Der mittlere Baum zeigt nur Gruppen und Inhaltstypen (mit Anzahl-Badge).
Einzelne Datensätze gehören in die Listenansicht / das Formular in der
Mitte — nicht in die Navigation (skaliert nicht bei vielen Einträgen).

## Dashboard zuerst

Rail-„Übersicht“, Sections und Groups öffnen eine Übersicht mit
Schnellzugriff-Kacheln und „Zuletzt bearbeitet“. Liste und Formular
erst bei Typ- bzw. Datensatz-Klick.

## Anti-Pattern

Kein roher Seiten-/Ordnerbaum 1:1 aus dem Backend (inkl. Systemordner).
Redakteure sollen Aufgaben und Inhaltstypen sehen, nicht das interne
Datenmodell.
