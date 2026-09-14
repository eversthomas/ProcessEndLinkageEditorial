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

	/** Schema als Array (template + fields) für die Form-Engine */
	public function readSchema(string $template): array;

	/** Alle Datensätze für Listenansicht (ggf. gedeckelt, siehe ProcessWireAdapter::LIST_LIMIT) */
	public function listRecords(string $template): array;

	/**
	 * Reine Anzahl, ohne Datensätze zu laden — für Zähler in Nav/Dashboard.
	 * $statusFilter: null = alle, 'published' = nur veröffentlicht, 'unpublished' = nur Entwürfe.
	 */
	public function countRecords(string $template, ?string $statusFilter = null): int;

	/** Einzelner Datensatz oder null */
	public function getRecord(string $template, string $id): ?array;

	/**
	 * Speichern. Rückgabe: ['record' => array|null, 'errors' => array]
	 * errors keyed by field name where possible.
	 */
	public function saveRecord(string $template, array $data): array;
}
