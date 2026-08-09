<?php namespace ProcessWire\BsProcessEditorial\Adapter;

/**
 * Vertrag zwischen Editorial-UI und Datenquelle.
 * UI kennt nur Schema-/Record-Arrays — nie PW-Objekte.
 */
interface AdapterInterface {

	/**
	 * Redaktionelle Inhaltstypen für Navigation.
	 * @return array<int, array{name: string, label: string, mode: string}>
	 */
	public function listContentTypes(): array;

	/** Ob dieser Adapter den Inhaltstyp bedienen kann */
	public function supportsTemplate(string $template): bool;

	/** Schema im Format von schema-mock.json */
	public function readSchema(string $template): array;

	/** Alle Datensätze für Listenansicht */
	public function listRecords(string $template): array;

	/** Einzelner Datensatz oder null */
	public function getRecord(string $template, string $id): ?array;

	/**
	 * Speichern. Rückgabe: ['record' => array|null, 'errors' => array]
	 * errors keyed by field name where possible.
	 */
	public function saveRecord(string $template, array $data): array;
}
