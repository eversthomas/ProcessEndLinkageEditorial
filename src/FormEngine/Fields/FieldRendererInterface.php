<?php namespace ProcessWire\ProcessEndLinkageEditorial\FormEngine\Fields;

/**
 * Feld-Renderer erhalten nur Schema-Daten — keine PW-Objekte.
 */
interface FieldRendererInterface {

	public function supports(string $type): bool;

	/**
	 * @param array $field Schema-Feld
	 * @param mixed $value Aktueller Wert
	 * @param array $errors Fehler je Feldname
	 */
	public function render(array $field, mixed $value, array $errors = []): string;
}
