<?php namespace ProcessWire\BsProcessEditorial\Adapter\Fields;

use ProcessWire\Field;
use ProcessWire\FieldtypeFloat;
use ProcessWire\FieldtypeInteger;
use ProcessWire\Page;

class NumberFieldAdapter extends AbstractFieldAdapter {

	public function supports(Field $field): bool {
		$type = $field->type;
		return $type instanceof FieldtypeInteger || $type instanceof FieldtypeFloat;
	}

	public function readSchema(Field $field, Page $page): array {
		$isFloat = $field->type instanceof FieldtypeFloat;
		$schema = $this->baseSchema($field, $isFloat ? 'float' : 'integer');
		$schema['step'] = $isFloat ? 'any' : '1';
		$min = $field->get('min');
		$max = $field->get('max');
		if ($min !== null && $min !== '') {
			$schema['min'] = $min;
		}
		if ($max !== null && $max !== '') {
			$schema['max'] = $max;
		}
		return $schema;
	}

	public function sanitizeAndValidate(Field $field, Page $page, mixed $rawValue): array {
		$sanitizer = $field->wire()->sanitizer;
		$raw = trim((string) ($rawValue ?? ''));
		$errors = [];
		$isFloat = $field->type instanceof FieldtypeFloat;

		if ($raw === '') {
			if ($field->get('required')) {
				$errors[] = $this->requiredError($field);
			}
			return ['value' => null, 'errors' => $errors];
		}

		if ($isFloat) {
			if (!is_numeric($raw)) {
				$errors[] = '„' . $field->getLabel() . '“: bitte eine Zahl eingeben.';
				return ['value' => null, 'errors' => $errors];
			}
			$value = $sanitizer->float($raw);
		} else {
			if (!preg_match('/^-?\d+$/', $raw)) {
				$errors[] = '„' . $field->getLabel() . '“: bitte eine ganze Zahl eingeben.';
				return ['value' => null, 'errors' => $errors];
			}
			$value = $sanitizer->int($raw);
		}

		$min = $field->get('min');
		$max = $field->get('max');
		if ($min !== null && $min !== '' && $value < (float) $min) {
			$errors[] = '„' . $field->getLabel() . '“: Wert zu klein.';
		}
		if ($max !== null && $max !== '' && $value > (float) $max) {
			$errors[] = '„' . $field->getLabel() . '“: Wert zu groß.';
		}

		return ['value' => $value, 'errors' => $errors];
	}

	public function writeValue(Field $field, Page $page, mixed $sanitizedValue): void {
		$page->set($field->name, $sanitizedValue);
	}

	public function readValue(Field $field, Page $page): mixed {
		$value = $page->getUnformatted($field->name);
		if ($value === null || $value === '') {
			return null;
		}
		return $field->type instanceof FieldtypeFloat ? (float) $value : (int) $value;
	}
}
