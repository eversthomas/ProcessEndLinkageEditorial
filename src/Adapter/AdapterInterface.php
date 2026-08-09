<?php namespace ProcessWire\BsProcessEditorial\Adapter;

/**
 * Vertrag für den späteren ProcessWire-Adapter.
 * UI/Form-Engine kennt nur dieses Schema — nie PW-Objekte.
 */
interface AdapterInterface {

	/** Schema im Format von schema-mock.json */
	public function readSchema(string $template): array;

	/** Alle Datensätze für Listenansicht */
	public function listRecords(string $template): array;

	/** Einzelner Datensatz oder null */
	public function getRecord(string $template, string $id): ?array;

	/**
	 * Speichern. Rückgabe: ['record' => array, 'errors' => string[]]
	 * errors keyed by field name where possible.
	 */
	public function saveRecord(string $template, array $data): array;
}
