<?php namespace ProcessWire\ProcessEndLinkageEditorial\Adapter\Fields;

use ProcessWire\Field;
use ProcessWire\FieldtypeEmail;
use ProcessWire\FieldtypeText;
use ProcessWire\FieldtypeTextarea;
use ProcessWire\FieldtypeURL;
use ProcessWire\Page;

class TextFieldAdapter extends AbstractFieldAdapter {

	public function supports(Field $field): bool {
		$type = $field->type;
		if ($type instanceof FieldtypeTextarea) {
			return false;
		}
		if ($type instanceof FieldtypeEmail || $type instanceof FieldtypeURL) {
			return false;
		}
		return $type instanceof FieldtypeText
			|| $type->className() === 'FieldtypePageTitle';
	}

	public function readSchema(Field $field, Page $page): array {
		$schema = $this->baseSchema($field, 'text');
		$schema['placeholder'] = (string) $field->get('placeholder');
		$schema['maxLength'] = (int) ($field->get('maxlength') ?: 2048);
		return $schema;
	}

	public function sanitizeAndValidate(Field $field, Page $page, mixed $rawValue): array {
		$sanitizer = $field->wire()->sanitizer;
		$maxLength = (int) ($field->get('maxlength') ?: 2048);
		$clean = $sanitizer->text((string) ($rawValue ?? ''), ['maxLength' => $maxLength]);
		$errors = [];
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
