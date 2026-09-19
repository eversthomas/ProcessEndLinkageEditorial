<?php namespace ProcessWire\ProcessEndLinkageEditorial\Adapter\Fields;

use ProcessWire\Field;
use ProcessWire\FieldtypeDatetime;
use ProcessWire\Page;

class DatetimeFieldAdapter extends AbstractFieldAdapter {

	public function supports(Field $field): bool {
		return $field->type instanceof FieldtypeDatetime;
	}

	public function readSchema(Field $field, Page $page): array {
		$timeFormat = trim((string) $field->get('timeInputFormat'));
		$schema = $this->baseSchema($field, 'datetime');
		$schema['includeTime'] = $timeFormat !== '';
		$schema['panel'] = 'main';
		return $schema;
	}

	public function sanitizeAndValidate(Field $field, Page $page, mixed $rawValue): array {
		$raw = trim((string) ($rawValue ?? ''));
		$errors = [];
		$includeTime = trim((string) $field->get('timeInputFormat')) !== '';

		if ($raw === '') {
			if ($field->get('required')) {
				$errors[] = $this->requiredError($field);
			}
			return ['value' => '', 'errors' => $errors];
		}

		// datetime-local: 2026-09-13T14:30  |  date: 2026-09-13
		$raw = str_replace('T', ' ', $raw);
		$ts = strtotime($raw);
		if ($ts === false) {
			$errors[] = '„' . $field->getLabel() . '“: ungültiges Datum.';
			return ['value' => '', 'errors' => $errors];
		}
		if (!$includeTime) {
			$ts = strtotime(date('Y-m-d', $ts) . ' 00:00:00');
		}
		return ['value' => $ts, 'errors' => $errors];
	}

	public function writeValue(Field $field, Page $page, mixed $sanitizedValue): void {
		$page->set($field->name, $sanitizedValue === '' || $sanitizedValue === null ? '' : (int) $sanitizedValue);
	}

	public function readValue(Field $field, Page $page): mixed {
		$ts = (int) $page->getUnformatted($field->name);
		if ($ts <= 0) {
			return '';
		}
		$includeTime = trim((string) $field->get('timeInputFormat')) !== '';
		return $includeTime ? date('Y-m-d\TH:i', $ts) : date('Y-m-d', $ts);
	}
}
