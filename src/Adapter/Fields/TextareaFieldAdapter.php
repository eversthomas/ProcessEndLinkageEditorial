<?php namespace ProcessWire\BsProcessEditorial\Adapter\Fields;

use ProcessWire\Field;
use ProcessWire\FieldtypeTextarea;
use ProcessWire\Page;

class TextareaFieldAdapter extends AbstractFieldAdapter {

	public function supports(Field $field): bool {
		return $field->type instanceof FieldtypeTextarea;
	}

	public function readSchema(Field $field, Page $page): array {
		$schema = $this->baseSchema($field, 'textarea');
		$schema['placeholder'] = (string) $field->get('placeholder');
		$schema['maxLength'] = (int) ($field->get('maxlength') ?: 16384);
		$schema['rows'] = (int) ($field->get('rows') ?: 5);
		$schema['contentType'] = (int) $field->get('contentType');
		return $schema;
	}

	public function sanitizeAndValidate(Field $field, Page $page, mixed $rawValue): array {
		$contentType = (int) $field->get('contentType');
		$errors = [];
		if ($contentType > 0) {
			$errors[] = '„' . $field->getLabel() . '“: HTML-Textarea wird im MVP nicht unterstützt.';
			return ['value' => '', 'errors' => $errors];
		}

		$sanitizer = $field->wire()->sanitizer;
		$maxLength = (int) ($field->get('maxlength') ?: 16384);
		$clean = $sanitizer->textarea((string) ($rawValue ?? ''), ['maxLength' => $maxLength]);
		if ($field->get('required') && $clean === '') {
			$errors[] = $this->requiredError($field);
		}
		return ['value' => $clean, 'errors' => $errors];
	}

	public function writeValue(Field $field, Page $page, mixed $sanitizedValue): void {
		$page->set($field->name, $sanitizedValue);
	}

	public function readValue(Field $field, Page $page): mixed {
		return (string) $page->getUnformatted($field->name);
	}
}
