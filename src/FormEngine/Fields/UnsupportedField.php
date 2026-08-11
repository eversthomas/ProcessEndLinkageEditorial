<?php namespace ProcessWire\BsProcessEditorial\FormEngine\Fields;

/**
 * Platzhalter für PW-Felder ohne Adapter.
 */
class UnsupportedField extends AbstractFieldRenderer {

	public function supports(string $type): bool {
		return $type === 'unsupported';
	}

	public function render(array $field, mixed $value, array $errors = []): string {
		$label = (string) ($field['label'] ?? $field['name'] ?? 'Feld');
		$pwType = (string) ($field['pwType'] ?? $field['type'] ?? 'unbekannt');
		$message = sprintf(
			'Feld „%s“ (Typ %s) wird hier noch nicht unterstützt — bitte im PW-Backend pflegen.',
			$label,
			$pwType
		);

		$html = '<div class="field bpe-field bpe-field--unsupported" data-field="'
			. $this->escape((string) ($field['name'] ?? '')) . '">';
		$html .= '<p class="bpe-field__unsupported" role="status">' . $this->escape($message) . '</p>';
		$html .= '</div>';
		return $html;
	}
}
