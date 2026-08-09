<?php namespace ProcessWire\BsProcessEditorial\Schema;

/**
 * Lädt das Komponenten-Schema (MVP: schema-mock.json).
 * Vertrag zwischen Form-Engine und Adapter.
 */
class SchemaLoader {

	protected string $path;

	public function __construct(?string $path = null) {
		$this->path = $path ?? dirname(__DIR__, 2) . '/data/schema-mock.json';
	}

	public function load(): array {
		if (!is_file($this->path)) {
			throw new \RuntimeException("Schema-Datei nicht gefunden: {$this->path}");
		}
		$json = file_get_contents($this->path);
		$data = json_decode($json, true);
		if (!is_array($data) || empty($data['template']) || empty($data['fields'])) {
			throw new \RuntimeException('Ungültiges Schema-Format.');
		}
		return $data;
	}

	public function getField(array $schema, string $name): ?array {
		foreach ($schema['fields'] as $field) {
			if (($field['name'] ?? '') === $name) {
				return $field;
			}
		}
		return null;
	}
}
