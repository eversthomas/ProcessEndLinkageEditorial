# Navigation

## Flache Liste ist der Normalfall

Für Datensatz-Sammlungen (Einrichtungen, Ansprechpartner, Produkte,
Mitglieder, ...) ist die Standardnavigation: Klick auf den Inhaltstyp →
Tabelle aller Einträge. Kein verschachtelter Baum, keine Ordnerstruktur,
kein "wo ist das im System einsortiert".

Anti-Pattern: ein roher Seiten-/Ordnerbaum, der 1:1 die interne
Datenstruktur des Backends zeigt (inkl. technischer/Systemordner). Das
zwingt Redakteur:innen, das Datenmodell zu verstehen, um überhaupt etwas
zu finden.

## Wann Hierarchie legitim ist

Nur wenn die Hierarchie selbst redaktionell relevant ist — typischerweise
bei Frontend-Seitenstruktur, die die echte Navigation der Website
widerspiegelt (wo landet dieser Beitrag im Menü). Auch dann: gefiltert
auf redaktionell relevante Ebenen, keine Systemordner oder technischen
Zwischenknoten anzeigen.

## Globale Navigation

Top-Level-Einträge sollten Aufgaben/Inhaltstypen benennen, nicht
Systemkonzepte. "Struktur", "Schema", "Templates" gehören nicht in die
Redakteur-Navigation — das sind Entwickler-Begriffe. Gut: "Inhalte",
"Medien", "Einstellungen" (eigenes Profil), plus dynamisch generierte
Einträge pro Inhaltstyp.

## Duplikate/Rauschen vermeiden

Bei automatisch generierten Listen (aus dem Datenmodell abgeleitet)
darauf achten, dass keine technischen Duplikate (z. B. mehrere Einträge
mit identischem Titel aus unterschiedlichen internen Gründen) ungefiltert
auftauchen — das verwirrt mehr, als es hilft.
