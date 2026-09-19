<?php namespace ProcessWire\ProcessEndLinkageEditorial\Adapter\Fields;

use ProcessWire\Field;
use ProcessWire\FieldtypeURL;
use ProcessWire\Page;

class UrlFieldAdapter extends AbstractFieldAdapter {

	public function supports(Field $field): bool {
		return $field->type instanceof FieldtypeURL;
	}

	public function readSchema(Field $field, Page $page): array {
		$schema = $this->baseSchema($field, 'url');
		$schema['placeholder'] = (string) ($field->get('placeholder') ?: 'https://');
		$schema['maxLength'] = (int) ($field->get('maxlength') ?: 1024);
		return $schema;
	}

	public function sanitizeAndValidate(Field $field, Page $page, mixed $rawValue): array {
		$sanitizer = $field->wire()->sanitizer;
		$raw = trim((string) ($rawValue ?? ''));
		$errors = [];
		if ($raw === '') {
			if ($field->get('required')) {
				$errors[] = $this->requiredError($field);
			}
			return ['value' => '', 'errors' => $errors];
		}
		$clean = $sanitizer->url($raw);
		if ($clean === '') {
			$errors[] = '„' . $field->getLabel() . '“: ungültige URL.';
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
