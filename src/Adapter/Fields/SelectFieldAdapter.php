<?php namespace ProcessWire\BsProcessEditorial\Adapter\Fields;

use ProcessWire\Field;
use ProcessWire\FieldtypeOptions;
use ProcessWire\Page;

class SelectFieldAdapter extends AbstractFieldAdapter {

	public function supports(Field $field): bool {
		return $field->type instanceof FieldtypeOptions;
	}

	public function readSchema(Field $field, Page $page): array {
		/** @var FieldtypeOptions $fieldtype */
		$fieldtype = $field->type;
		$options = [];
		foreach ($fieldtype->getOptions($field) as $opt) {
			$options[] = [
				'value' => $opt->getValue(),
				'label' => $opt->getTitle(),
			];
		}
		$schema = $this->baseSchema($field, 'select');
		$schema['options'] = $options;
		return $schema;
	}

	public function sanitizeAndValidate(Field $field, Page $page, mixed $rawValue): array {
		$raw = trim((string) ($rawValue ?? ''));
		$errors = [];
		if ($field->get('required') && $raw === '') {
			$errors[] = $this->requiredError($field);
			return ['value' => '', 'errors' => $errors];
		}
		if ($raw === '') {
			return ['value' => '', 'errors' => []];
		}

		/** @var FieldtypeOptions $fieldtype */
		$fieldtype = $field->type;
		$allowed = [];
		foreach ($fieldtype->getOptions($field) as $opt) {
			$allowed[] = $opt->getValue();
		}
		if (!in_array($raw, $allowed, true)) {
			$errors[] = 'Ungültige Auswahl für „' . $field->getLabel() . '“.';
		}
		return ['value' => $raw, 'errors' => $errors];
	}

	public function writeValue(Field $field, Page $page, mixed $sanitizedValue): void {
		$page->set($field->name, $sanitizedValue);
	}

	public function readValue(Field $field, Page $page): mixed {
		$val = $page->get($field->name);
		if (!$val || !method_exists($val, 'first')) {
			return '';
		}
		$first = $val->first();
		return $first ? $first->getValue() : '';
	}
}
