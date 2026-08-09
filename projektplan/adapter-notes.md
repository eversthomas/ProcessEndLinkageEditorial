# Adapter-Implementierungsnotizen — 6 MVP-Feldtypen

Grundlage: `processwire-core/SKILL.md` + `reference/*.md` (ProcessWire 3.0.257 dev), ergänzt um
gezielte Nachrecherche im Core für die hier konkret benötigten Details (Datei-Upload ohne
Admin-JS, Page-Reference-Validierung ohne Autocomplete-Widget). Jede Aussage ist mit Datei
(und wo hilfreich Zeile) belegt. Bezug ausschließlich auf `schema-mock.json` (Template
`einrichtung`) und die 6 MVP-Feldtypen: Text, Textarea, Checkbox, Select, Bild, Page Reference.

Alle Codebeispiele gehen von folgendem Kontext aus: `$field` = das jeweilige `Field`-Objekt
(`$fields->get('name')`), `$page` = das jeweils bearbeitete `Page`-Objekt, output formatting
beim Schreiben immer aus (`$page->of(false)`, siehe `reference/page-api.md`).

---

## 1. Text (`title`)

Core-Fieldtype: `FieldtypeText`, Standard-Inputfield: `InputfieldText`
(`wire/modules/Fieldtype/FieldtypeText.module`, `wire/modules/Inputfield/InputfieldText/InputfieldText.module`).

### 1.1 Lesen (Schema-Generierung)

```php
$field = $fields->get('title');

$schemaField = [
    'name'        => $field->name,
    'type'        => 'text',
    'label'       => $field->getLabel(),                 // Fallback auf $field->name wenn leer
    'required'    => (bool) $field->get('required'),
    'placeholder' => (string) $field->get('placeholder'), // InputfieldText-Attribut, s.u.
    'maxLength'   => (int) ($field->get('maxlength') ?: 2048),
];
```

- `label`: `Field::getLabel()` fällt auf `$field->name` zurück, wenn kein Label gesetzt ist
  (`wire/core/Field.php`, `getText()`/`getLabel()`).
- `required`: dynamische Data-Property, kein natives `Field`-Setting
  (`wire/core/Field.php`, Klassen-Docblock `@property int|bool|null $required`).
- `placeholder` und `maxlength` sind **Inputfield-Attribute**, keine `FieldtypeText`-eigenen
  Settings. Sie werden beim Speichern des Feldes im PW-Admin über
  `InputfieldText::___getConfigInputfields()` in die Field-`data`-Spalte geschrieben und sind
  danach über `$field->get('maxlength')` / `$field->get('placeholder')` lesbar
  (`InputfieldText.module` Zeilen 48–58, 377–379, 449). Default für `maxlength`, falls das Feld
  es nie explizit gesetzt hat: `InputfieldText::defaultMaxlength = 2048`
  (`InputfieldText.module` Zeile 27).

### 1.2 Schreiben (Speichern)

```php
$raw = $input->post('title', 'string'); // oder aus eigenem JSON-Body, unsanitized

$maxLength = (int) ($field->get('maxlength') ?: 2048);
$clean = $sanitizer->text($raw, ['maxLength' => $maxLength]);

if ($field->get('required') && $clean === '') {
    $errors[] = "„{$field->getLabel()}“ ist ein Pflichtfeld.";
} else {
    $page->of(false);
    $page->set('title', $clean);
}
```

- `$sanitizer->text($value, $options)` — Standard-`maxLength` ist **255**, nicht 2048
  (`reference/sanitization-validation.md` §2.1, `wire/core/Sanitizer.php`). Ohne explizite
  Übergabe von `maxLength` würde ein Wert, den PW selbst bei `maxlength=2048` zulassen würde,
  beim Sanitizing auf 255 Zeichen gekappt.
- **Kritisch:** `FieldtypeText::sanitizeValue()` ist ein reines No-op — `return $value;`
  ohne jede Prüfung (`FieldtypeText.module` Zeilen 74–76, direkt verifiziert). Das heißt:
  `$page->set('title', $irgendwas); $page->save();` speichert den Wert **exakt so, wie
  übergeben** — keine Längenbegrenzung, kein Tag-Stripping, keine Typprüfung durch PW selbst.
  Die gesamte Sanitization-Verantwortung liegt beim Adapter.
- `required` wird von `$pages->save()` / `PagesEditor.php` **nicht** geprüft
  (`reference/sanitization-validation.md` §4) — der Adapter muss das selbst vor dem Speichern
  tun.

### 1.3 Stolperfallen

- Da `sanitizeValue()` ein No-op ist, ist ein Text-Feld über die reine PW-API **kein**
  XSS-Schutz — wird der Wert später ungefiltert in HTML gerendert (z. B. in einer eigenen
  Editorial-Vorschau), muss der Adapter selbst entity-encodieren (`$sanitizer->entities()`)
  oder beim Rendern Output-Formatting nutzen (`$page->of(true)` aktiviert ggf. konfigurierte
  `textformatters`, hier bei `title` i. d. R. keine).
- `maxlength`/`placeholder` existieren nur, wenn das Feld im PW-Admin mit `InputfieldText`
  konfiguriert wurde. Wurde ein alternatives `inputfieldClass` gesetzt (z. B. eine Custom-
  Inputfield), können diese Properties fehlen — immer mit `?:`-Fallback lesen, nie annehmen,
  dass sie gesetzt sind.

---

## 2. Textarea (`beschreibung`)

Core-Fieldtype: `FieldtypeTextarea` (erbt von `FieldtypeText`), Standard-Inputfield:
`InputfieldTextarea` (`wire/modules/Fieldtype/FieldtypeTextarea.module`,
`wire/modules/Inputfield/InputfieldTextarea.module`).

### 2.1 Lesen (Schema-Generierung)

```php
$field = $fields->get('beschreibung');

$schemaField = [
    'name'        => $field->name,
    'type'        => 'textarea',
    'label'       => $field->getLabel(),
    'required'    => (bool) $field->get('required'),
    'placeholder' => (string) $field->get('placeholder'),
    'maxLength'   => (int) ($field->get('maxlength') ?: InputfieldTextarea::defaultMaxlength),
    'rows'        => (int) ($field->get('rows') ?: 5),
    'contentType' => (int) $field->get('contentType'), // 0=unknown/plain, 1=HTML, 2=HTML+Bilder
];
```

- `rows` und `maxlength` sind `InputfieldTextarea`-Attribute (`InputfieldTextarea.module`
  Zeilen 44–45), analog zu Text.
- `contentType` ist eine **`FieldtypeTextarea`-eigene** Fieldtype-Property (nicht Inputfield),
  Konstanten `contentTypeUnknown` (0), `contentTypeHTML` (1), `contentTypeImageHTML` (2)
  (`reference/fields-and-fieldtypes.md` §"FieldtypeText / FieldtypeTextarea"). Für das MVP
  (reines Plaintext-Textarea gemäß `schema-mock.json`) ist `contentType=0` der relevante Fall —
  bei `contentType>0` behandelt PW den Wert intern als HTML (URL-Abstraktion beim
  Speichern/Laden), was für ein reines Text-Feld unerwünschte Nebenwirkungen hätte.

### 2.2 Schreiben (Speichern)

```php
$raw = $input->post('beschreibung', 'string');
$maxLength = (int) ($field->get('maxlength') ?: 16384);
$clean = $sanitizer->textarea($raw, ['maxLength' => $maxLength]);

$page->of(false);
$page->set('beschreibung', $clean);
```

- `$sanitizer->textarea()` Standard-`maxLength` ist **16384** Zeichen — großzügiger als bei
  `text()`, aber ebenfalls ohne Berücksichtigung des tatsächlich am Feld konfigurierten
  `maxlength`, außer man übergibt es explizit (`reference/sanitization-validation.md` §2.1).
- `FieldtypeTextarea` hat **keine eigene** `sanitizeValue()`-Überschreibung (kein Treffer bei
  gezielter Suche in `FieldtypeTextarea.module`) — es erbt die No-op-Implementierung von
  `FieldtypeText` direkt. Es gilt exakt dasselbe wie bei Text: PW validiert/kürzt hier von
  sich aus nichts.
- Auch hier: `required` nicht durch `save()` erzwungen — Adapter-seitig prüfen.

### 2.3 Stolperfallen

- **`$sanitizer->textarea()` entfernt HTML-Tags** (wie `text()`, nur mehrzeilig) — das ist für
  das MVP-Feld korrekt (reiner Text), aber falsch, sobald `contentType` am Feld auf HTML
  steht. In dem Fall wäre `$sanitizer->purify()` das richtige Werkzeug
  (`reference/sanitization-validation.md` §2.1) — für die aktuellen 6 MVP-Typen aber nicht
  relevant, da `schema-mock.json` kein Rich-Text-Feld vorsieht. Trotzdem beim `readSchema()`
  `contentType` mit auslesen und im Adapter hart auf `0` prüfen/erzwingen, damit später kein
  falsch konfiguriertes Feld unbemerkt durchrutscht.
- Gleicher Placeholder/maxlength-Vorbehalt wie bei Text (Punkt 1.3).

---

## 3. Checkbox (`aktiv`)

Core-Fieldtype: `FieldtypeCheckbox` (`wire/modules/Fieldtype/FieldtypeCheckbox.module`).

### 3.1 Lesen (Schema-Generierung)

```php
$field = $fields->get('aktiv');

$schemaField = [
    'name'     => $field->name,
    'type'     => 'checkbox',
    'label'    => $field->getLabel(),
    'required' => (bool) $field->get('required'),
    // kein natives PW-Äquivalent zu schema-mock.json "default" -- siehe 3.3
];
```

- `FieldtypeCheckbox` definiert **keine eigenen Config-Properties** (keine
  `getConfigInputfields()`-Überschreibung im Modul, verifiziert per Suche in
  `FieldtypeCheckbox.module`) — außer den generischen (`label`, `required`, `columnWidth` etc.)
  gibt es nichts Feldtyp-Spezifisches zu lesen.

### 3.2 Schreiben (Speichern)

```php
$raw = $input->post->bool('aktiv'); // erkennt "0"/"false" korrekt als false

$page->of(false);
$page->set('aktiv', $raw ? 1 : 0);
```

- `FieldtypeCheckbox::sanitizeValue()` macht **exakt** `return $value ? 1 : 0;`
  (`FieldtypeCheckbox.module` Zeilen 45–47, wörtlich zitiert) — reine PHP-Truthy-Prüfung, keine
  eigene Normalisierung.
- `getBlankValue()` liefert `0` (int), nicht `null` (`FieldtypeCheckbox.module` Zeilen 32–34) —
  ein frisch angelegtes/nicht gesetztes Checkbox-Feld ist also immer `0`, nie `undefined`.

### 3.3 Stolperfallen

- **PHP-Truthy-Falle:** Kommt der Wert aus einem JSON-Body als **String** `"false"` an (typisch
  bei einer entkoppelten JS-Oberfläche, die z. B. `JSON.stringify(false)` sendet und der Adapter
  das per `$input->post('aktiv')` roh oder über `json_decode()` einliest), ist `"false"` in PHP
  ein **truthy** String (`(bool) "false" === true`). Ruft man `$page->set('aktiv', $wert)`
  direkt mit diesem rohen String auf, würde `FieldtypeCheckbox::sanitizeValue()` daraus `1`
  (angehakt) machen — das Gegenteil des gewollten Werts. Deshalb **immer** vorher explizit über
  `$sanitizer->bool()` (erkennt `"0"`/`"false"` korrekt als `false`,
  `reference/sanitization-validation.md` §2.3) oder `$sanitizer->checkbox()` normalisieren,
  bevor der Wert an `set()` geht — sich nicht auf PWs eigene `sanitizeValue()` verlassen.
- **Kein natives `default`:** `schema-mock.json` hat `"default": true` für `aktiv`, aber
  `FieldtypeCheckbox` kennt kein Default-Value-Setting und `getBlankValue()` ist hart auf `0`
  verdrahtet. Der in `schema-mock.json` vorgesehene Default-Wert existiert nur im
  Komponenten-Schema/UI-Layer — der Adapter muss ihn beim „Neu anlegen“-Formular selbst als
  Vorbelegung mitgeben (z. B. hartcodiert oder aus einer eigenen Adapter-Konfiguration, nicht
  aus PW auslesbar).

---

## 4. Select (`kategorie`)

Core-Fieldtype: `FieldtypeOptions` ("Select Options",
`wire/modules/Fieldtype/FieldtypeOptions/FieldtypeOptions.module`, plus
`SelectableOption.php` / `SelectableOptionArray.php` / `SelectableOptionManager.php`).

### 4.1 Lesen (Schema-Generierung)

```php
$field = $fields->get('kategorie');
/** @var FieldtypeOptions $fieldtype */
$fieldtype = $field->type;

$options = [];
foreach ($fieldtype->getOptions($field) as $opt) {
    /** @var SelectableOption $opt */
    $options[] = [
        'value' => $opt->getValue(), // sprach-/output-formatting-bewusster Accessor
        'label' => $opt->getTitle(),
    ];
}

$schemaField = [
    'name'     => $field->name,
    'type'     => 'select',
    'label'    => $field->getLabel(),
    'required' => (bool) $field->get('required'),
    'options'  => $options,
];
```

- `FieldtypeOptions::getOptions($field)` liefert eine `SelectableOptionArray` von
  `SelectableOption`-Objekten, delegiert intern an `$this->manager`
  (`reference/fields-and-fieldtypes.md` §"FieldtypeOptions").
- `getTitle()`/`getValue()` statt direktem `->title`/`->value`-Zugriff verwenden — das sind die
  sprachbewussten, output-formatierten Accessor-Methoden (fallen auf Default-Sprache zurück,
  entity-encodieren bei aktivem Output-Formatting) — direkte Property-Reads liefern
  ggf. Rohdaten ohne diesen Fallback.

### 4.2 Schreiben (Speichern)

```php
$raw = $input->post('kategorie', 'text'); // erwartet den "value"-String, z.B. "treff"

if ($field->get('required') && $raw === '') {
    $errors[] = "„{$field->getLabel()}“ ist ein Pflichtfeld.";
} else {
    $page->of(false);
    $page->set('kategorie', $raw); // FieldtypeOptions::sanitizeValue() löst value->Option auf
}
```

- `FieldtypeOptions::sanitizeValue()` akzeptiert direkt einen **Value-String** (wie er im
  `options`-Array unter `value` steht) und löst ihn intern über
  `$this->manager->getOptions($field, ['value' => $wert])` auf — es funktioniert auch mit der
  numerischen Options-ID oder dem `title`-String, mit Priorität ID → title → value
  (`FieldtypeOptions.module` Zeilen 170–233, direkt gelesen). Für unser Schema (`value` als
  stabiler Identifier) ist die Value-String-Übergabe der richtige Weg.
- **Bemerkenswert positiv:** Im Gegensatz zu den anderen Feldtypen **validiert**
  `sanitizeValue()` hier tatsächlich gegen die real am Feld konfigurierten Optionen — ein
  unbekannter `value`-String liefert eine leere `SelectableOptionArray` zurück
  (`getOptions()` mit `count()===0`), es wird still auf "leer" zurückgesetzt statt eines
  ungültigen Werts gespeichert. Das ist aber ein **stilles** Fallback, kein Fehler — der Adapter
  sollte den `value` selbst gegen die in 4.1 gelesene Optionsliste prüfen, um dem Redakteur
  einen echten Validierungsfehler statt eines klammheimlich geleerten Felds zu zeigen.

### 4.3 Stolperfallen

- Der Feldwert ist **immer** eine `SelectableOptionArray` (auch bei Single-Select!) — für die
  Anzeige/Rückgabe im Schema-Format `->first()` verwenden:
  `$page->get('kategorie')->first()?->getValue()`. Ob das Inputfield Single- oder
  Multi-Select ist, steht in `$field->get('inputfieldClass')`
  (z. B. `InputfieldSelect` = single, `InputfieldCheckboxes`/`InputfieldSelectMultiple` =
  multi) — dieser Wert entscheidet, ob der Adapter beim Lesen `.first()` nimmt oder das ganze
  Array als Liste ausgibt.
- `required` wird — wie bei allen Feldtypen — nicht von `$page->save()` erzwungen, nur
  optional über `initValue` (Default-Vorbelegung bei leerem Pflichtfeld,
  `reference/fields-and-fieldtypes.md`) unterstützt PW selbst etwas Ähnliches, aber das greift
  nur beim Anlegen einer neuen Seite über `PagesEditor::save()` → `setupNew()`, nicht als
  Validierung beim Speichern eines bestehenden leeren Werts.

---

## 5. Bild (`titelbild`)

Core-Fieldtype: `FieldtypeImage` (erbt von `FieldtypeFile`,
`wire/modules/Fieldtype/FieldtypeFile/FieldtypeFile.module`,
`wire/modules/Fieldtype/FieldtypeImage/FieldtypeImage.module`), Upload-Mechanik:
`WireUpload` (`wire/core/WireUpload.php`), Speicherort: `PagefilesManager`
(`wire/core/PagefilesManager.php`).

### 5.1 Lesen (Schema-Generierung)

```php
$field = $fields->get('titelbild');

$extensions = trim((string) $field->get('extensions')) ?: 'gif jpg jpeg png'; // Default lt. FieldtypeImage
$accept = array_map(function ($ext) {
    $map = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
            'gif' => 'image/gif', 'webp' => 'image/webp'];
    return $map[$ext] ?? "image/$ext";
}, explode(' ', $extensions));

$schemaField = [
    'name'     => $field->name,
    'type'     => 'image',
    'label'    => $field->getLabel(),
    'required' => (bool) $field->get('required'),
    'maxFiles' => (int) $field->get('maxFiles'), // 0 = unbegrenzt, 1 = Einzelbild
    'accept'   => array_values(array_unique($accept)),
];
```

- `extensions` ist ein **space-separated String**, keine Array/CSV-Notation, z. B.
  `"gif jpg jpeg png"` (`reference/fields-and-fieldtypes.md` §"FieldtypeFile"). PW liefert
  keine MIME-Type-Liste — die Umrechnung Extension → `accept`-MIME-Type (wie im
  `schema-mock.json`-Format gefordert) muss der Adapter selbst vornehmen; es gibt keine
  Core-API dafür.
- `getInputfield()` von `FieldtypeFile` gibt eine Warnung aus, wenn `extensions` am Feld noch
  nie gesetzt wurde ("nicht bereit", `reference/fields-and-fieldtypes.md`) — praktisch heißt
  das: ein frisch angelegtes Bildfeld ohne im PW-Admin konfigurierte Extensions sollte der
  Adapter im Schema als "nicht konfiguriert" statt mit einer leeren `accept`-Liste behandeln.

### 5.2 Schreiben (Speichern)

Ohne Admin-JS/Ajax-Uploader muss der Adapter den rohen `$_FILES`-Upload selbst über die
Core-Klasse `WireUpload` verarbeiten (das ist dieselbe Klasse, die `InputfieldFile` intern
nutzt — sie ist bewusst UI-unabhängig):

```php
$field = $fields->get('titelbild');
$page->of(false);

$upload = new WireUpload('titelbild');  // Name muss zum <input type="file" name="titelbild"> passen
$upload->setMaxFiles((int) $field->get('maxFiles') ?: 1);
$extensions = explode(' ', trim((string) $field->get('extensions')) ?: 'gif jpg jpeg png');
$upload->setValidExtensions($extensions);
$upload->setOverwrite(false);
$upload->setDestinationPath($page->filesManager()->path()); // Seiten-eigenes Dateiverzeichnis

$filenames = $upload->execute(); // legt Dateien direkt im Zielverzeichnis ab, validiert Ext./Größe

if (count($upload->getErrors())) {
    $errors = $upload->getErrors();
} else {
    $images = $page->getUnformatted('titelbild'); // immer Pagefiles/Pageimages-Collection
    if ((int) $field->get('maxFiles') === 1) {
        $images->deleteAll(); // altes Bild ersetzen (löscht Datei von Platte + aus Collection)
    }
    foreach ($filenames as $filename) {
        $images->add($page->filesManager()->path() . $filename);
    }
    $page->save('titelbild');
}
```

- `WireUpload::__construct($name)` liest aus `$_FILES[$name]` (`getPhpFiles()`,
  `WireUpload.php` Zeilen 164–171, 227–260, direkt gelesen). Der `name`-Parameter muss exakt
  dem `name`-Attribut des HTML-`<input type="file">` entsprechen.
- `setValidExtensions()`/`setMaxFiles()`/`setDestinationPath()` sind reguläre öffentliche
  Setter (`WireUpload.php` Zeilen 741–803) — genau hier, **nicht** in
  `FieldtypeFile::sanitizeValue()`, werden Extensions und Dateianzahl geprüft.
- `Pagefiles::add($item)` akzeptiert einen **Dateipfad (String)** zu einer bereits auf der
  Platte liegenden Datei (kopiert/übernimmt sie dann in die Seiten-eigene Struktur) oder ein
  bestehendes `Pagefile`-Objekt — **nicht** ein rohes `$_FILES`-Array
  (`wire/core/Pagefiles.php` Zeilen 338–370, direkt gelesen). Der Weg ist also immer:
  1. `WireUpload` verschiebt die hochgeladene Datei validiert ins Zielverzeichnis,
  2. `Pagefiles::add($vollständigerPfad)` übernimmt sie ins Feld.
- `$page->filesManager()->path()` liefert das Seiten-eigene Dateiverzeichnis
  (`wire/core/PagefilesManager.php` Zeile 380) — das korrekte `setDestinationPath()`-Ziel.

### 5.3 Stolperfallen

- **`extensions`/`maxFiles` werden von `FieldtypeFile::sanitizeValue()` NICHT geprüft** —
  verifiziert: `sanitizeValue()` ruft nur `$pagefiles->add($file)` für jeden übergebenen Wert
  auf, ohne jede Extension- oder Anzahl-Kontrolle (`FieldtypeFile.module` Zeilen 800–808,
  wörtlich gelesen). Würde der Adapter `Pagefiles::add()` direkt mit einem beliebigen,
  ungeprüften Dateipfad aufrufen (z. B. weil er den Upload selbst ohne `WireUpload`
  handhabt), speichert PW auch Dateien mit unerlaubter Endung anstandslos. Die Validierung
  **muss** über `WireUpload::setValidExtensions()`/`setMaxFiles()` erfolgen — das ist keine
  Option, sondern die einzige Stelle, an der PW das für Datei-Uploads überhaupt prüft.
- **Kein Admin-JS heißt: kein automatisches Thumbnail/Resize-UI, kein Drag&Drop, kein
  Fortschrittsbalken** — das MVP braucht dafür nichts von PW, ein simples
  `<input type="file">`-Formular reicht für `WireUpload::execute()`. Für Bildvorschau/Cropping
  im UI-Layer wäre serverseitig `Pageimage::size($w, $h, $options)` nutzbar
  (`reference/fields-and-fieldtypes.md` §"FieldtypeImage"), aber das ist **außerhalb** des
  hier verlangten Scopes (Medienverwaltung ist laut `PROJECT.md` explizit nicht MVP).
- Bei `maxFiles === 1` liefert PW **trotzdem intern immer eine Collection**
  (`Pagefiles`/`Pageimages`), auch wenn die *formatierte* Ausgabe (`$page->of(true)`) bei
  `maxFiles=1` automatisch auf ein einzelnes `Pageimage`-Objekt "entpackt" wird
  (`outputFormatAuto`, `reference/fields-and-fieldtypes.md`). Beim **Schreiben** immer mit
  `getUnformatted()` arbeiten und die Collection explizit leeren (`deleteAll()`), sonst
  akkumulieren sich bei wiederholtem Speichern mehrere Bilder trotz `maxFiles=1`.
- `deleteAll()` löscht die Datei **physisch von der Platte** (nicht nur aus der Collection) —
  bei einem "Bild ersetzen"-Formular ist das gewollt, bei einem versehentlichen Aufruf aber
  nicht rückgängig zu machen (kein Papierkorb für Dateien, anders als bei Seiten).

---

## 6. Page Reference (`ansprechpartner`)

Core-Fieldtype: `FieldtypePage` (`wire/modules/Fieldtype/FieldtypePage.module`), Constraints
und Validierung liegen im zugehörigen `InputfieldPage`
(`wire/modules/Inputfield/InputfieldPage/InputfieldPage.module`).

### 6.1 Lesen (Schema-Generierung)

```php
$field = $fields->get('ansprechpartner');

/** @var InputfieldPage $inputfield */
$inputfield = $field->getInputfield($page); // liefert konfiguriertes InputfieldPage (parent_id/template_id etc. bereits gesetzt)
$selectable = $inputfield->getSelectablePages($page); // PageArray, respektiert parent_id/template_id/findPagesSelector

$labelField = $field->get('labelFieldName') ?: 'title';
$options = [];
foreach ($selectable as $p) {
    $options[] = ['id' => $p->id, 'label' => (string) $p->get($labelField)];
}

$schemaField = [
    'name'              => $field->name,
    'type'              => 'pageReference',
    'label'             => $field->getLabel(),
    'required'          => (bool) $field->get('required'),
    'referenceTemplate' => ($tplId = (int) $field->get('template_id'))
        ? $templates->get($tplId)->name
        : null,
    'multiple'          => $field->get('derefAsPage') == FieldtypePage::derefAsPageArray, // 0 = PageArray = "multiple"
    'options'           => $options,
];
```

- `Field::getInputfield($page)` liefert ein bereits mit den Field-Settings befülltes
  `Inputfield`-Objekt (`reference/fields-and-fieldtypes.md` §"Getting the Inputfield for a
  field") — für `FieldtypePage` ist das eine `InputfieldPage`-Instanz mit `parent_id`,
  `template_id`, `findPagesSelector` etc. bereits übernommen.
- `InputfieldPage::___getSelectablePages(Page $page, $filterSelector = '')` ist eine
  **öffentliche, hookbare** Methode, die genau die laut Feldkonfiguration erlaubten Zielseiten
  als `PageArray` zurückgibt — inklusive Auswertung von `parent_id`, `template_id` **und**
  `findPagesSelector`/`findPagesSelect` (`InputfieldPage.module` Zeilen 359ff., direkt
  gelesen). Das ist exakt die Liste, die `schema-mock.json`s `options`-Array befüllen soll —
  der Adapter muss die Selector-Logik **nicht** selbst nachbauen.
- `derefAsPage` bestimmt Single- vs. Multi-Reference:
  `FieldtypePage::derefAsPageArray` (0, Default) = immer `PageArray` = unser `multiple: true`;
  `derefAsPageOrFalse` (1) / `derefAsPageOrNullPage` (2) = Single-Page-Modus
  (`reference/fields-and-fieldtypes.md` §"FieldtypePage").

### 6.2 Schreiben (Speichern)

```php
$field = $fields->get('ansprechpartner');
$ids = array_map('intval', (array) $input->post('ansprechpartner')); // z.B. [1023, 1024]

$errors = [];
foreach ($ids as $id) {
    $candidate = $pages->get($id);
    if (!$candidate->id) {
        $errors[] = "Ungültige Seiten-ID $id.";
        continue;
    }
    if (!InputfieldPage::isValidPage($candidate, $field, $page)) {
        $reason = $page->get('_isValidPage'); // von isValidPage() befüllt
        $errors[] = "Seite $id ist für „{$field->getLabel()}“ nicht zulässig ($reason).";
    }
}

if (empty($errors)) {
    $page->of(false);
    $page->set('ansprechpartner', $ids); // FieldtypePage::sanitizeValue() akzeptiert Array von IDs direkt
    $page->save('ansprechpartner');
}
```

- `FieldtypePage::sanitizeValue()` akzeptiert eine `Page`, eine `PageArray`, eine einzelne
  Int-ID, einen komma-/pipe-separierten String **oder ein Array von Int-IDs** direkt — die
  IDs werden intern über `$pages->getById()`/`$pages->get()` zu echten `Page`-Objekten
  aufgelöst (`FieldtypePage.module` Zeilen 704–860, `sanitizeValuePage()` /
  `sanitizeValuePageArray()`, direkt gelesen). Man muss also **nicht** selbst
  `$pages->get($id)`-Objekte laden und übergeben — ein reines Int-Array reicht.
- `InputfieldPage::isValidPage(Page $page, $field, ?Page $editPage = null): bool` ist eine
  **öffentliche, statische** Methode, die exakt die Prüfung durchführt, die sonst beim
  Verarbeiten des Admin-Formulars automatisch läuft: zirkuläre Referenz, `findPagesSelector`,
  `parent_id`, `template_id`/`template_ids` (`InputfieldPage.module` Zeilen 235ff., direkt
  gelesen). Bei `false` wird der Grund in `$editPage->_isValidPage` abgelegt (setQuietly, nur
  wenn `$editPage` übergeben wurde). Für den entkoppelten Adapter ist das der zentrale
  Baustein, um serverseitig **ohne** das Admin-UI dieselbe Konsistenzprüfung durchzuführen wie
  PW selbst.

### 6.3 Stolperfallen

- **`sanitizeValue()` prüft `parent_id`/`template_id` NICHT** — verifiziert direkt in
  `sanitizeValuePageArray()`/`sanitizeValuePage()`: einzige eingebaute Prüfung ist die
  Vermeidung einer zirkulären Selbstreferenz (`$v->id == $page->id`), keine Prüfung gegen
  `template_id`/`parent_id`/`findPagesSelector` (`FieldtypePage.module` Zeilen 773–860,
  direkt gelesen). Ruft der Adapter `$page->set('ansprechpartner', $ids); $page->save();`
  **ohne** vorherigen `InputfieldPage::isValidPage()`-Check auf, akzeptiert PW anstandslos
  jede beliebige existierende Page-ID als Referenz — auch eine, die laut Feldkonfiguration gar
  nicht zulässig wäre (z. B. eine Seite mit falschem Template). Diese Prüfung ist also
  zwingend **Adapter-Pflicht**, nicht optional.
- **Ohne Autocomplete-Widget** muss die komplette Optionsliste (`getSelectablePages()`) beim
  Laden des Formulars vollständig an die UI-Schicht übergeben werden (wie in `schema-mock.json`
  mit den festen `options: [{id, label}]`) — es gibt in PW keinen eingebauten "leichten"
  Server-Endpoint zur inkrementellen Suche außerhalb des `InputfieldPageAutocomplete`-JS.
  Bei großen Zielmengen (viele mögliche Ansprechpartner) muss der Adapter selbst eine
  zusätzliche Filterung/Suche implementieren (`getSelectablePages($page, $filterSelector)`
  akzeptiert seit 3.0.245 einen zusätzlichen Selector-String zum serverseitigen Filtern, s.
  Methodensignatur oben) — für den MVP-Umfang mit wenigen Ansprechpartnern ist eine
  vollständige Liste wie im Mock aber ausreichend.
- `labelFieldName === '.'` bedeutet laut Doku "benutze stattdessen `labelFieldFormat`"
  (`$page->getMarkup()`-Formatstring) statt eines einzelnen Feldnamens
  (`reference/fields-and-fieldtypes.md` §"FieldtypePage") — der Adapter-Code oben
  (`$p->get($labelField)`) deckt nur den einfachen Fall ab; ist `labelFieldName` `.` oder leer,
  muss stattdessen `$p->getMarkup($field->get('labelFieldFormat'))` verwendet werden, sonst
  entstehen leere/falsche Labels für Felder, die im Admin mit Format-String statt einfachem
  Feldnamen konfiguriert wurden.
- `template_id` (Singular) und `template_ids` (Array, Plural) existieren parallel —
  `Field::get('template_id')` liefert bei mehreren erlaubten Templates nur die **erste** ID
  (`InputfieldPage::getSetting()`, Zeilen 340–349: `template_id` fällt auf `reset($templateIDs)`
  zurück, wenn leer) — für das Schema-Feld `referenceTemplate` (Singular laut
  `schema-mock.json`) ist das im MVP okay, solange nur genau ein Ziel-Template konfiguriert
  ist; bei mehreren würde der Adapter sonst stillschweigend nur das erste sehen.

---

## Gemeinsame Muster

Über alle 6 Feldtypen hinweg zeigen sich drei wiederkehrende, jeweils **verifizierte** Muster,
die sich direkt in eine gemeinsame Adapter-Basisklasse übersetzen lassen:

### 1. PWs eigene Persistenzschicht validiert kaum etwas

Der `Fieldtype::sanitizeValue()`/`$page->save()`-Pfad ist über alle untersuchten Feldtypen
hinweg überraschend dünn:

| Feldtyp | Was `sanitizeValue()`/`save()` NICHT prüft |
|---|---|
| Text/Textarea | gar nichts (reines No-op, Wert wird 1:1 übernommen) |
| Checkbox | keine Normalisierung von String-„false“/„0“ (reine PHP-Truthy-Prüfung) |
| Select | *(Ausnahme, s. u.)* |
| Bild | `extensions`, `maxFiles` — nur `WireUpload` prüft das, nicht `Pagefiles::add()` |
| Page Reference | `parent_id`, `template_id`, `findPagesSelector` |
| Alle Typen | `required` — wird ausschließlich in `InputfieldWrapper::___processInput()` geprüft (`reference/sanitization-validation.md` §4), also nie auf dem reinen API-Pfad |

Einzige Ausnahme: `FieldtypeOptions::sanitizeValue()` validiert tatsächlich gegen die real am
Feld hinterlegten Optionen (still, per leerem Rückgabewert statt Exception).

**Konsequenz für die Architektur:** Da bs-processData die komplette
`Inputfield`/`InputfieldWrapper`-Formularschicht bewusst umgeht (kein Admin-JS, eigene
UI-Komponenten), muss der Adapter selbst die Rolle übernehmen, die in Standard-PW
`InputfieldWrapper::processInput()` übernimmt. Das ist kein Nice-to-have, sondern zwingend
notwendig, um überhaupt PW-äquivalente Datenintegrität zu erreichen.

### 2. PW liefert bereits fertige Bausteine für genau diese Validierung — nur nicht gebündelt

Für die nicht-triviale Validierung existieren bereits nutzbare Core-Methoden, die der Adapter
aufrufen statt selbst nachbauen sollte:

- `InputfieldPage::isValidPage($candidate, $field, $editPage)` — statisch, öffentlich, deckt
  Page-Reference-Constraints ab.
- `InputfieldPage::getSelectablePages($page)` — liefert die erlaubte Zielmenge für die
  Options-Liste im Schema.
- `FieldtypeOptions::getOptions($field)` — liefert die gültige Optionsliste.
- `Field::getInputfield($page)` — liefert ein Feld-konfiguriertes Inputfield-Objekt, aus dem
  sich die typ-spezifischen Config-Properties (`maxlength`, `rows`, `parent_id`, …) einheitlich
  auslesen lassen, ohne für jeden Feldtyp eine andere Quelle suchen zu müssen.

### 3. Vorschlag: gemeinsame Adapter-Basisklasse

```php
abstract class ProcessDataFieldAdapter {

    abstract public function readSchema(Field $field, Page $page): array;
    // liest Label/required/typ-spezifische Config, gibt schema-mock.json-kompatibles Array zurück

    abstract public function sanitizeAndValidate(Field $field, Page $page, $rawValue): array;
    // Rückgabe: ['value' => $sanitizedValue, 'errors' => string[]]
    // hier läuft ALLES, was PW selbst NICHT auf dem API-Pfad prüft (required, Extensions,
    // Page-Reference-Constraints, Options-Zugehörigkeit, Checkbox-Typkonvertierung)

    abstract public function writeValue(Field $field, Page $page, $sanitizedValue): void;
    // $page->set()/$page->images->add() usw. -- läuft NUR wenn sanitizeAndValidate() fehlerfrei war
}
```

Speicher-Ablauf im Formular-Handler (generisch für alle 6 Typen, template-agnostisch):

1. Für jedes Feld im Template: `sanitizeAndValidate()` aufrufen, Fehler sammeln.
2. Nur wenn **alle** Felder fehlerfrei sind: für jedes Feld `writeValue()` aufrufen (bei Bild
   ggf. vorher schon in `sanitizeAndValidate()` per `WireUpload` real hochladen/prüfen, da
   Datei-Uploads nicht "zurückgehalten" werden können wie ein einfacher Wert).
3. Einmal `$page->save()` am Ende (kein Grund, pro Feld einzeln zu speichern — mehrere
   `$page->save('feldname')`-Aufrufe sind laut `reference/page-api.md` möglich, aber ein
   gesammelter Save reduziert unnötige DB-Writes und hält alle Änderungen atomar innerhalb
   eines PW-Save-Hooks-Zyklus).

Diese Struktur deckt alle 6 MVP-Feldtypen mit derselben Kontrollflusslogik ab — die einzigen
Unterschiede liegen in den drei abstrakten Methoden pro Feldtyp, nicht im Ablauf selbst.
