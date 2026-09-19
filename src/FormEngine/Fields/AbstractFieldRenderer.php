<?php namespace ProcessWire\ProcessEndLinkageEditorial\FormEngine\Fields;

abstract class AbstractFieldRenderer implements FieldRendererInterface {

	protected function escape(mixed $value): string {
		return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
	}

	protected function fieldId(array $field): string {
		return 'field-' . preg_replace('/[^a-z0-9_-]/i', '', $field['name']);
	}

	protected function wrapperStart(array $field, array $errors): string {
		$id = $this->fieldId($field);
		$name = $field['name'];
		$hasError = !empty($errors[$name]);
		$required = !empty($field['required']);
		$classes = 'field bpe-field' . ($hasError ? ' bpe-field--error' : '');
		$html = '<div class="' . $classes . '" data-field="' . $this->escape($name) . '">';
		$html .= '<label class="bpe-field__label" for="' . $this->escape($id) . '">';
		$html .= $this->escape($field['label'] ?? $name);
		if ($required) {
			$html .= ' <span class="bpe-field__required" title="Pflichtfeld">*</span>';
		}
		$html .= '</label>';
		return $html;
	}

	/**
	 * Baut einen "Geschwister"-Feldnamen für Hilfsfelder wie "_clear"/"_existing" — nötig, weil
	 * einfaches Anhängen des Suffixes bei geklammerten Repeater-Item-Namen (z. B. "rep[0][bild]")
	 * ungültiges Bracket-Syntax ergäbe ("rep[0][bild]_clear" statt "rep[0][bild_clear]"), das PHP
	 * beim Parsen von $_POST nicht mehr als verschachteltes Array erkennt.
	 */
	protected function siblingName(string $name, string $suffix): string {
		if (preg_match('/^(.*)\[([^\[\]]*)\]$/', $name, $m)) {
			return $m[1] . '[' . $m[2] . $suffix . ']';
		}
		return $name . $suffix;
	}

	protected function wrapperEnd(array $field, array $errors): string {
		$name = $field['name'];
		$html = '';
		if (!empty($errors[$name])) {
			$html .= '<p class="bpe-field__error" role="alert">' . $this->escape($errors[$name]) . '</p>';
		}
		$html .= '</div>';
		return $html;
	}
}
