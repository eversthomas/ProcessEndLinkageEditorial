<?php namespace ProcessWire\ProcessEndLinkageEditorial\Adapter\Fields;

use ProcessWire\Field;
use ProcessWire\FieldtypeOptions;
use ProcessWire\InputfieldHasArrayValue;
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
		$schema['multiple'] = $this->isMultiple($field, $page);
		$schema['options'] = $options;
		return $schema;
	}

	public function sanitizeAndValidate(Field $field, Page $page, mixed $rawValue): array {
		$errors = [];
		/** @var FieldtypeOptions $fieldtype */
		$fieldtype = $field->type;
		$allowed = [];
		foreach ($fieldtype->getOptions($field) as $opt) {
			$allowed[] = $opt->getValue();
		}

		if ($this->isMultiple($field, $page)) {
			$raw = array_values(array_filter(
				array_map('strval', (array) $rawValue),
				fn(string $v) => $v !== ''
			));
			if ($field->get('required') && !$raw) {
				$errors[] = $this->requiredError($field);
				return ['value' => [], 'errors' => $errors];
			}
			foreach ($raw as $v) {
				if (!in_array($v, $allowed, true)) {
					$errors[] = 'Ungültige Auswahl für „' . $field->getLabel() . '“.';
					break;
				}
			}
			return ['value' => $raw, 'errors' => $errors];
		}

		$raw = trim((string) ($rawValue ?? ''));
		if ($field->get('required') && $raw === '') {
			$errors[] = $this->requiredError($field);
			return ['value' => '', 'errors' => $errors];
		}
		if ($raw === '') {
			return ['value' => '', 'errors' => []];
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
		$multiple = $this->isMultiple($field, $page);
		$val = $page->get($field->name);
		if (!$val || !method_exists($val, 'first')) {
			return $multiple ? [] : '';
		}
		if ($multiple) {
			$out = [];
			foreach ($val as $opt) {
				$out[] = $opt->getValue();
			}
			return $out;
		}
		$first = $val->first();
		return $first ? $first->getValue() : '';
	}

	/**
	 * Erkennung per Interface statt Klassennamen-Liste — verifiziert gegen
	 * wire/modules/Inputfield/InputfieldSelect.module: "Subclasses that select multiple
	 * values should implement the InputfieldHasArrayValue interface." Deckt u. a.
	 * InputfieldSelectMultiple, InputfieldCheckboxes, InputfieldAsmSelect ab.
	 */
	protected function isMultiple(Field $field, Page $page): bool {
		return $field->getInputfield($page) instanceof InputfieldHasArrayValue;
	}
}
