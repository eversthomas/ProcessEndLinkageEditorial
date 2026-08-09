<?php namespace ProcessWire\BsProcessEditorial\Setup;

use ProcessWire\BsProcessEditorial;
use ProcessWire\Field;
use ProcessWire\Fieldgroup;
use ProcessWire\Page;
use ProcessWire\Template;
use ProcessWire\WireException;

/**
 * Legt das MVP-Testdatenmodell an (Template einrichtung + Felder + Elternseite).
 */
class MvpInstaller {

	public const TEMPLATE = 'einrichtung';
	public const PARENT_PATH = '/einrichtungen/';
	public const CONTACT_TEMPLATE = 'ansprechpartner';

	protected BsProcessEditorial $module;

	public function __construct(BsProcessEditorial $module) {
		$this->module = $module;
	}

	public function isInstalled(): bool {
		$templates = $this->module->wire()->templates;
		$pages = $this->module->wire()->pages;
		return (bool) $templates->get(self::TEMPLATE)
			&& $pages->get(self::PARENT_PATH)->id;
	}

	/**
	 * @return string[] Statusmeldungen
	 */
	public function install(): array {
		$messages = [];
		$wire = $this->module->wire();

		$this->ensureField('beschreibung', 'FieldtypeTextarea', 'Beschreibung', [
			'rows' => 6,
			'placeholder' => 'Kurzbeschreibung der Einrichtung ...',
		], $messages);

		$this->ensureField('aktiv', 'FieldtypeCheckbox', 'Aktiv / öffentlich sichtbar', [], $messages);

		$kategorie = $this->ensureField('kategorie', 'FieldtypeOptions', 'Kategorie', [
			'required' => 1,
		], $messages);
		if ($kategorie) {
			/** @var \ProcessWire\FieldtypeOptions $type */
			$type = $kategorie->type;
			$type->manager->setOptionsString($kategorie, implode("\n", [
				'treff|AWO-Treff',
				'beratung|Beratungsstelle',
				'kita|Kita',
				'wohnen|Wohnanlage',
			]));
			$kategorie->save();
			$messages[] = 'Kategorie-Optionen gesetzt.';
		}

		$this->ensureField('titelbild', 'FieldtypeImage', 'Titelbild', [
			'maxFiles' => 1,
			'extensions' => 'jpg jpeg png webp',
		], $messages);

		$contactTpl = $wire->templates->get(self::CONTACT_TEMPLATE);
		$contactParent = $wire->pages->get('/ansprechpartner/');
		$refSettings = [
			'derefAsPage' => 0, // PageArray = multiple
			'inputfieldClass' => 'InputfieldSelectMultiple',
			'labelFieldName' => 'title',
		];
		if ($contactTpl && $contactTpl->id) {
			$refSettings['template_id'] = $contactTpl->id;
		}
		if ($contactParent && $contactParent->id) {
			$refSettings['parent_id'] = $contactParent->id;
		}
		$this->ensureField('ansprechpartner', 'FieldtypePage', 'Ansprechpartner', $refSettings, $messages);

		$this->ensureTemplate(self::TEMPLATE, [
			'title',
			'beschreibung',
			'aktiv',
			'kategorie',
			'titelbild',
			'ansprechpartner',
		], 'Einrichtung', $messages);

		$this->ensureParentPage($messages);
		$this->ensureSamplePages($messages);

		return $messages;
	}

	protected function ensureField(string $name, string $type, string $label, array $data, array &$messages): ?Field {
		$fields = $this->module->wire()->fields;
		$modules = $this->module->wire()->modules;
		$field = $fields->get($name);
		if ($field && $field->id) {
			$messages[] = "Feld „{$name}“ existiert bereits.";
			return $field;
		}

		$field = new Field();
		$field->type = $modules->get($type);
		$field->name = $name;
		$field->label = $label;
		foreach ($data as $key => $value) {
			$field->set($key, $value);
		}
		$field->save();
		$messages[] = "Feld „{$name}“ angelegt.";
		return $field;
	}

	protected function ensureTemplate(string $name, array $fieldNames, string $label, array &$messages): void {
		$templates = $this->module->wire()->templates;
		$fields = $this->module->wire()->fields;
		$existing = $templates->get($name);
		if ($existing && $existing->id) {
			$messages[] = "Template „{$name}“ existiert bereits.";
			return;
		}

		$fg = new Fieldgroup();
		$fg->name = $name;
		foreach ($fieldNames as $fieldName) {
			$field = $fields->get($fieldName);
			if (!$field) {
				throw new WireException("Feld fehlt für Template: {$fieldName}");
			}
			$fg->add($field);
		}
		$fg->save();

		$tpl = new Template();
		$tpl->name = $name;
		$tpl->label = $label;
		$tpl->fieldgroup = $fg;
		$tpl->set('noParents', -1); // darf unter erlaubten Eltern liegen
		$tpl->save();
		$messages[] = "Template „{$name}“ angelegt.";
	}

	protected function ensureParentPage(array &$messages): void {
		$pages = $this->module->wire()->pages;
		$parent = $pages->get(self::PARENT_PATH);
		if ($parent->id) {
			$messages[] = 'Elternseite /einrichtungen/ existiert bereits.';
			return;
		}

		$home = $pages->get(1);
		$basic = $this->module->wire()->templates->get('basic-page');
		if (!$basic) {
			throw new WireException('Template basic-page fehlt — Elternseite kann nicht angelegt werden.');
		}

		$page = new Page();
		$page->template = $basic;
		$page->parent = $home;
		$page->name = 'einrichtungen';
		$page->title = 'Einrichtungen';
		$page->addStatus(Page::statusHidden);
		$page->save();
		$messages[] = 'Elternseite /einrichtungen/ angelegt.';
	}

	protected function ensureSamplePages(array &$messages): void {
		$pages = $this->module->wire()->pages;
		$parent = $pages->get(self::PARENT_PATH);
		$tpl = $this->module->wire()->templates->get(self::TEMPLATE);
		if (!$parent->id || !$tpl) {
			return;
		}
		if ($pages->count("template=" . self::TEMPLATE) > 0) {
			$messages[] = 'Beispiel-Einrichtungen existieren bereits.';
			return;
		}

		$contacts = $pages->find('template=' . self::CONTACT_TEMPLATE . ', limit=2');
		$samples = [
			[
				'title' => 'AWO-Treff Kamp-Lintfort',
				'beschreibung' => 'Begegnungsstätte mit Café und Kursangebot.',
				'aktiv' => 1,
				'kategorie' => 'treff',
				'ansprechpartner' => $contacts->count() ? [$contacts->first()->id] : [],
			],
			[
				'title' => 'Beratungsstelle Moers',
				'beschreibung' => 'Sozialberatung nach Terminvereinbarung.',
				'aktiv' => 1,
				'kategorie' => 'beratung',
				'ansprechpartner' => $contacts->count() > 1 ? [$contacts->eq(1)->id] : [],
			],
		];

		foreach ($samples as $data) {
			$page = new Page();
			$page->of(false);
			$page->template = $tpl;
			$page->parent = $parent;
			$page->title = $data['title'];
			$page->name = $this->module->wire()->sanitizer->pageName($data['title'], true);
			$page->save();
			$page->of(false);
			$page->set('beschreibung', $data['beschreibung']);
			$page->set('aktiv', $data['aktiv']);
			$page->set('kategorie', $data['kategorie']);
			if ($data['ansprechpartner']) {
				$page->set('ansprechpartner', $data['ansprechpartner']);
			}
			$page->save();
		}
		$messages[] = 'Beispiel-Einrichtungen angelegt.';
	}
}
