<?php namespace ProcessWire\BsProcessEditorial\Adapter;

use ProcessWire\BsProcessEditorial\Mock\MockStore;
use ProcessWire\BsProcessEditorial\Schema\SchemaLoader;

/**
 * Mock-Adapter: bedient die UI aus schema-mock.json + MockStore.
 * Später 1:1 durch den echten PW-Adapter ersetzbar.
 */
class MockAdapter implements AdapterInterface {

	protected SchemaLoader $schemaLoader;
	protected MockStore $store;

	public function __construct(?SchemaLoader $schemaLoader = null, ?MockStore $store = null) {
		$this->schemaLoader = $schemaLoader ?? new SchemaLoader();
		$this->store = $store ?? new MockStore();
	}

	public function readSchema(string $template): array {
		$schema = $this->schemaLoader->load();
		if ($schema['template'] !== $template) {
			throw new \InvalidArgumentException("Unbekanntes Template: {$template}");
		}
		return $schema;
	}

	public function listRecords(string $template): array {
		$this->assertTemplate($template);
		return $this->store->all();
	}

	public function getRecord(string $template, string $id): ?array {
		$this->assertTemplate($template);
		return $this->store->get($id);
	}

	public function saveRecord(string $template, array $data): array {
		$schema = $this->readSchema($template);
		$errors = [];
		$clean = ['id' => $data['id'] ?? null];

		foreach ($schema['fields'] as $field) {
			$name = $field['name'];
			$type = $field['type'];
			$raw = $data[$name] ?? null;
			$value = $this->normalizeValue($type, $raw, $field);

			if (!empty($field['required']) && $this->isEmpty($type, $value)) {
				$errors[$name] = '„' . ($field['label'] ?? $name) . '“ ist ein Pflichtfeld.';
				continue;
			}

			if ($type === 'select' && $value !== null && $value !== '') {
				$allowed = array_column($field['options'] ?? [], 'value');
				if (!in_array($value, $allowed, true)) {
					$errors[$name] = 'Ungültige Auswahl.';
					continue;
				}
			}

			if ($type === 'pageReference' && is_array($value)) {
				$allowedIds = array_map('strval', array_column($field['options'] ?? [], 'id'));
				foreach ($value as $id) {
					if (!in_array((string) $id, $allowedIds, true)) {
						$errors[$name] = 'Ungültige Referenz.';
						break;
					}
				}
			}

			$clean[$name] = $value;
		}

		if ($errors) {
			return ['record' => null, 'errors' => $errors];
		}

		$record = $this->store->save($clean);
		return ['record' => $record, 'errors' => []];
	}

	protected function assertTemplate(string $template): void {
		$schema = $this->schemaLoader->load();
		if ($schema['template'] !== $template) {
			throw new \InvalidArgumentException("Unbekanntes Template: {$template}");
		}
	}

	protected function normalizeValue(string $type, mixed $raw, array $field): mixed {
		return match ($type) {
			'checkbox' => filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
			'pageReference' => array_values(array_filter(array_map('intval', (array) $raw))),
			'image' => is_string($raw) && $raw !== '' ? $raw : null,
			'text', 'textarea', 'select' => is_string($raw) ? trim($raw) : (string) ($raw ?? ''),
			default => $raw,
		};
	}

	protected function isEmpty(string $type, mixed $value): bool {
		return match ($type) {
			'checkbox' => false,
			'pageReference' => empty($value),
			'image' => $value === null || $value === '',
			default => $value === null || $value === '',
		};
	}
}
