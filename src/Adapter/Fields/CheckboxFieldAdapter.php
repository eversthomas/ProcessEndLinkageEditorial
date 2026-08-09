<?php namespace ProcessWire\BsProcessEditorial\Adapter\Fields;

use ProcessWire\Field;
use ProcessWire\FieldtypeCheckbox;
use ProcessWire\Page;

class CheckboxFieldAdapter extends AbstractFieldAdapter {

	public function supports(Field $field): bool {
		return $field->type instanceof FieldtypeCheckbox;
	}

	public function readSchema(Field $field, Page $page): array {
		// Default nur UI-Layer — PW hat kein natives Checkbox-Default
		$schema = $this->baseSchema($field, 'checkbox');
		$schema['default'] = true;
		return $schema;
	}

	public function sanitizeAndValidate(Field $field, Page $page, mixed $rawValue): array {
		$bool = $field->wire()->sanitizer->bool($rawValue);
		return ['value' => $bool ? 1 : 0, 'errors' => []];
	}

	public function writeValue(Field $field, Page $page, mixed $sanitizedValue): void {
		$page->set($field->name, $sanitizedValue ? 1 : 0);
	}

	public function readValue(Field $field, Page $page): mixed {
		return (bool) $page->getUnformatted($field->name);
	}
}
