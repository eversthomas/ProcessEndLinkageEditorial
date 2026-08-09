# bs-processEditorial

## Positionierung

Generische Redaktionsoberfläche für ProcessWire aus dem vorhandenen Datenmodell.
Redakteure arbeiten in einer eigenen App mit Login — nicht im PW-Admin.
Entwickler konfigurieren unter **Setup → Redaktion**.

**Übergabe / nächster Chat:** siehe Root-[README.md](../README.md) (Stand, Offen-Liste, Projektdateien).

## Architektur

```
Editorial UI → Form-Engine (Schema) → PW-Adapter → ProcessWire
```

## Tech-Stack

PHP (PW API) · Vanilla JS · CSS ohne Build · Lucide · TinyMCE (PW-Core)

## Stand

Aktueller Stand und offene Punkte: **[README.md](../README.md)**.

Feldtypen u. a.: text, textarea, html, email, url, integer, float, datetime,
checkbox, select, image, file, pageReference.

## Leitplanken

- Kein Build-Step, keine CSS-Frameworks
- UI/Form-Engine nur Schema-Daten, nie PW-Objekte
- Freigabe/Menü/Modus im Setup, nicht hardcodiert in der UI
- `projektplan/adapter-notes.md` nicht ohne Absprache ändern
