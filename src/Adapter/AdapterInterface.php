<?php namespace ProcessWire\ProcessEndLinkageEditorial\Adapter;

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
	 * Schlanke Datensätze (id/title/template/modified/modifiedBy/status, keine Feldwerte) über
	 * mehrere Templates hinweg, sortiert nach modified — für "Zuletzt bearbeitet"-Übersichten.
	 * Eine einzige Query statt pro Template alle Datensätze zu laden und in PHP zu sortieren.
	 *
	 * @param string[] $templates
	 * @return array<int, array{id: string, title: string, template: string, modified: string, modifiedBy: string|null, status: string}>
	 */
	public function recentRecords(array $templates, int $limit): array;

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
	 * $action: 'create' | 'edit' | 'publish' | 'unpublish' — für das Audit-Log, sonst ohne Wirkung.
	 */
	public function saveRecord(string $template, array $data, string $action = 'edit'): array;
}
