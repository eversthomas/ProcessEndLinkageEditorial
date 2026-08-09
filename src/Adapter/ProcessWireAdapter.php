<?php namespace ProcessWire\BsProcessEditorial\Adapter;

use ProcessWire\BsProcessEditorial;
use ProcessWire\BsProcessEditorial\Adapter\Fields\AbstractFieldAdapter;
use ProcessWire\BsProcessEditorial\Adapter\Fields\CheckboxFieldAdapter;
use ProcessWire\BsProcessEditorial\Adapter\Fields\ImageFieldAdapter;
use ProcessWire\BsProcessEditorial\Adapter\Fields\PageReferenceFieldAdapter;
use ProcessWire\BsProcessEditorial\Adapter\Fields\SelectFieldAdapter;
use ProcessWire\BsProcessEditorial\Adapter\Fields\TextareaFieldAdapter;
use ProcessWire\BsProcessEditorial\Adapter\Fields\TextFieldAdapter;
use ProcessWire\BsProcessEditorial\Setup\MvpInstaller;
use ProcessWire\Field;
use ProcessWire\Page;
use ProcessWire\WireException;

/**
 * ProcessWire-Adapter: liest/schreibt über die PW-API, liefert nur Schema-Arrays an die UI.
 */
class ProcessWireAdapter implements AdapterInterface {

	protected BsProcessEditorial $module;

	/** @var AbstractFieldAdapter[] */
	protected array $fieldAdapters;

	public function __construct(BsProcessEditorial $module, ?array $fieldAdapters = null) {
		$this->module = $module;
		$this->fieldAdapters = $fieldAdapters ?? [
			new TextareaFieldAdapter(), // vor Text (Textarea erbt von Text)
			new TextFieldAdapter(),
			new CheckboxFieldAdapter(),
			new SelectFieldAdapter(),
			new ImageFieldAdapter(),
			new PageReferenceFieldAdapter(),
		];
	}

	public function supportsTemplate(string $template): bool {
		$tpl = $this->module->wire()->templates->get($template);
		return $tpl && $tpl->id;
	}

	public function readSchema(string $template): array {
		$tpl = $this->requireTemplate($template);
		$contextPage = $this->contextPage($tpl->name);
		$fields = [];
		foreach ($tpl->fields as $field) {
			$adapter = $this->adapterFor($field);
			if (!$adapter) {
				continue; // unbekannte Typen im MVP überspringen
			}
			$fields[] = $adapter->readSchema($field, $contextPage);
		}
		return [
			'template' => $tpl->name,
			'label' => $tpl->get('label') ?: ucfirst($tpl->name),
			'fields' => $fields,
		];
	}

	public function listRecords(string $template): array {
		$this->requireTemplate($template);
		$pages = $this->module->wire()->pages->find("template={$template}, include=all, sort=-modified");
		$records = [];
		foreach ($pages as $page) {
			$records[] = $this->pageToRecord($page);
		}
		return $records;
	}

	public function getRecord(string $template, string $id): ?array {
		$page = $this->module->wire()->pages->get((int) $id);
		if (!$page->id || $page->template->name !== $template) {
			return null;
		}
		return $this->pageToRecord($page);
	}

	public function saveRecord(string $template, array $data): array {
		$tpl = $this->requireTemplate($template);
		$pages = $this->module->wire()->pages;
		$sanitizer = $this->module->wire()->sanitizer;
		$id = isset($data['id']) ? (int) $data['id'] : 0;

		if ($id) {
			$page = $pages->get($id);
			if (!$page->id || $page->template->name !== $template) {
				return ['record' => null, 'errors' => ['_form' => 'Eintrag nicht gefunden.']];
			}
		} else {
			$parent = $pages->get(MvpInstaller::PARENT_PATH);
			if (!$parent->id) {
				return ['record' => null, 'errors' => ['_form' => 'Elternseite /einrichtungen/ fehlt. Bitte Testdatenmodell anlegen.']];
			}
			$page = new Page();
			$page->template = $tpl;
			$page->parent = $parent;
		}

		$page->of(false);

		// 1) Seite anlegen falls neu (für Bild-Upload brauchen wir eine ID)
		$titleRaw = $data['title'] ?? '';
		$titleCheck = $this->adapterFor($tpl->fields->get('title'))
			?->sanitizeAndValidate($tpl->fields->get('title'), $page, $titleRaw);
		if ($titleCheck && $titleCheck['errors']) {
			return ['record' => null, 'errors' => ['title' => $titleCheck['errors'][0]]];
		}
		if (!$page->id) {
			$page->title = $titleCheck['value'] ?? $sanitizer->text((string) $titleRaw);
			$page->name = $sanitizer->pageName($page->title, true);
			if ($page->parent->child("name=" . $sanitizer->selectorValue($page->name))->id) {
				$page->name .= '-' . time();
			}
			$page->save();
			$page->of(false);
		}

		$validated = [];
		$errors = [];

		foreach ($tpl->fields as $field) {
			$adapter = $this->adapterFor($field);
			if (!$adapter) {
				continue;
			}
			$raw = $this->rawForField($field, $data);
			$result = $adapter->sanitizeAndValidate($field, $page, $raw);
			if ($result['errors']) {
				$errors[$field->name] = $result['errors'][0];
			} else {
				$validated[$field->name] = ['adapter' => $adapter, 'value' => $result['value'], 'field' => $field];
			}
		}

		if ($errors) {
			// Neu angelegte leere Seite bei Validierungsfehler wieder entfernen?
			// Behalten — Redakteur kann korrigieren. Optional trash wenn brand new & failed hard.
			return ['record' => null, 'errors' => $errors];
		}

		foreach ($validated as $item) {
			$item['adapter']->writeValue($item['field'], $page, $item['value']);
		}
		$page->save();

		return ['record' => $this->pageToRecord($page), 'errors' => []];
	}

	protected function pageToRecord(Page $page): array {
		$page->of(false);
		$record = [
			'id' => (string) $page->id,
			'created' => date('c', $page->created),
			'modified' => date('c', $page->modified),
		];
		foreach ($page->template->fields as $field) {
			$adapter = $this->adapterFor($field);
			if (!$adapter) {
				continue;
			}
			$record[$field->name] = $adapter->readValue($field, $page);
		}
		return $record;
	}

	protected function rawForField(Field $field, array $data): mixed {
		$name = $field->name;
		$adapter = $this->adapterFor($field);
		if ($adapter instanceof ImageFieldAdapter) {
			return [
				'clear' => !empty($data[$name . '_clear']),
				'keep' => $data[$name . '_existing'] ?? ($data[$name] ?? null),
				'upload' => !empty($_FILES[$name]['name']),
			];
		}
		return $data[$name] ?? null;
	}

	protected function adapterFor(Field $field): ?AbstractFieldAdapter {
		foreach ($this->fieldAdapters as $adapter) {
			if ($adapter->supports($field)) {
				return $adapter;
			}
		}
		return null;
	}

	protected function requireTemplate(string $template) {
		$tpl = $this->module->wire()->templates->get($template);
		if (!$tpl || !$tpl->id) {
			throw new WireException("Template „{$template}“ nicht gefunden.");
		}
		return $tpl;
	}

	protected function contextPage(string $templateName): Page {
		$pages = $this->module->wire()->pages;
		$existing = $pages->get("template={$templateName}, include=all");
		if ($existing->id) {
			return $existing;
		}
		$parent = $pages->get(MvpInstaller::PARENT_PATH);
		$page = new Page();
		$page->template = $templateName;
		$page->parent = $parent->id ? $parent : $pages->get(1);
		return $page;
	}
}
