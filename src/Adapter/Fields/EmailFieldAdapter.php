<?php namespace ProcessWire\ProcessEndLinkageEditorial\Adapter\Fields;

use ProcessWire\Field;
use ProcessWire\FieldtypeEmail;
use ProcessWire\Page;

class EmailFieldAdapter extends AbstractFieldAdapter {

	public function supports(Field $field): bool {
		return $field->type instanceof FieldtypeEmail;
	}

	public function readSchema(Field $field, Page $page): array {
		$schema = $this->baseSchema($field, 'email');
		$schema['placeholder'] = (string) $field->get('placeholder');
		$schema['maxLength'] = (int) ($field->get('maxlength') ?: 250);
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
		$clean = $sanitizer->email($raw);
		if ($clean === '') {
			$errors[] = '„' . $field->getLabel() . '“: ungültige E-Mail-Adresse.';
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
