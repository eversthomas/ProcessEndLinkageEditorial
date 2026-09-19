<?php namespace ProcessWire\ProcessEndLinkageEditorial\Adapter\Fields;

use ProcessWire\Field;
use ProcessWire\Page;

/**
 * Feld-Adapter: Schema lesen + validieren/schreiben — ohne UI-Kenntnis.
 */
abstract class AbstractFieldAdapter {

	abstract public function supports(Field $field): bool;

	/** Feld-Schema-Array für die Form-Engine */
	abstract public function readSchema(Field $field, Page $page): array;

	/**
	 * @return array{value: mixed, errors: string[]}
	 */
	abstract public function sanitizeAndValidate(Field $field, Page $page, mixed $rawValue): array;

	abstract public function writeValue(Field $field, Page $page, mixed $sanitizedValue): void;

	/** Wert für die Formular-/Listen-UI */
	abstract public function readValue(Field $field, Page $page): mixed;

	protected function baseSchema(Field $field, string $type): array {
		return [
			'name' => $field->name,
			'type' => $type,
			'label' => $field->getLabel(),
			'required' => (bool) $field->get('required'),
		];
	}

	protected function requiredError(Field $field): string {
		return '„' . $field->getLabel() . '“ ist ein Pflichtfeld.';
	}
}
