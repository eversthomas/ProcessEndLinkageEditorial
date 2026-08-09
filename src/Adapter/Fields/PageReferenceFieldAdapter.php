<?php namespace ProcessWire\BsProcessEditorial\Adapter\Fields;

use ProcessWire\Field;
use ProcessWire\FieldtypePage;
use ProcessWire\InputfieldPage;
use ProcessWire\Page;

class PageReferenceFieldAdapter extends AbstractFieldAdapter {

	public function supports(Field $field): bool {
		return $field->type instanceof FieldtypePage;
	}

	public function readSchema(Field $field, Page $page): array {
		$inputfield = $field->getInputfield($page);
		$selectable = $inputfield instanceof InputfieldPage
			? $inputfield->getSelectablePages($page)
			: $field->wire()->pages->newPageArray();

		$labelField = $field->get('labelFieldName') ?: 'title';
		$options = [];
		foreach ($selectable as $p) {
			if ($labelField === '.' || $labelField === '') {
				$label = $p->getMarkup((string) $field->get('labelFieldFormat'));
			} else {
				$label = (string) $p->get($labelField);
			}
			$options[] = [
				'id' => $p->id,
				'label' => $label !== '' ? $label : ('#' . $p->id),
			];
		}

		$tplId = (int) $field->get('template_id');
		$tplName = null;
		if ($tplId) {
			$tpl = $field->wire()->templates->get($tplId);
			$tplName = $tpl ? $tpl->name : null;
		}

		$schema = $this->baseSchema($field, 'pageReference');
		$schema['referenceTemplate'] = $tplName;
		$schema['multiple'] = ((int) $field->get('derefAsPage')) === FieldtypePage::derefAsPageArray;
		$schema['options'] = $options;
		return $schema;
	}

	public function sanitizeAndValidate(Field $field, Page $page, mixed $rawValue): array {
		$ids = array_values(array_filter(array_map('intval', (array) $rawValue)));
		$errors = [];

		if ($field->get('required') && !$ids) {
			$errors[] = $this->requiredError($field);
			return ['value' => [], 'errors' => $errors];
		}

		$pages = $field->wire()->pages;
		foreach ($ids as $id) {
			$candidate = $pages->get($id);
			if (!$candidate->id) {
				$errors[] = "Ungültige Seiten-ID {$id}.";
				continue;
			}
			if (!InputfieldPage::isValidPage($candidate, $field, $page)) {
				$reason = $page->get('_isValidPage') ?: 'nicht zulässig';
				$errors[] = "Seite {$id} ist für „{$field->getLabel()}“ nicht zulässig ({$reason}).";
			}
		}

		return ['value' => $ids, 'errors' => $errors];
	}

	public function writeValue(Field $field, Page $page, mixed $sanitizedValue): void {
		$page->set($field->name, $sanitizedValue);
	}

	public function readValue(Field $field, Page $page): mixed {
		$val = $page->getUnformatted($field->name);
		if (!$val) {
			return [];
		}
		if ($val instanceof Page) {
			return $val->id ? [$val->id] : [];
		}
		$ids = [];
		foreach ($val as $p) {
			$ids[] = (int) $p->id;
		}
		return $ids;
	}
}
