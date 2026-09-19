<?php namespace ProcessWire\ProcessEndLinkageEditorial\FormEngine\Fields;

class CheckboxField extends AbstractFieldRenderer {

	public function supports(string $type): bool {
		return $type === 'checkbox';
	}

	public function render(array $field, mixed $value, array $errors = []): string {
		$id = $this->fieldId($field);
		$name = $field['name'];
		$checked = filter_var($value, FILTER_VALIDATE_BOOLEAN);
		// Wenn kein Wert gesetzt (neues Formular), Schema-Default nutzen
		if ($value === null && array_key_exists('default', $field)) {
			$checked = (bool) $field['default'];
		}

		$hasError = !empty($errors[$name]);
		$classes = 'field bpe-field bpe-field--checkbox' . ($hasError ? ' bpe-field--error' : '');

		$html = '<div class="' . $classes . '" data-field="' . $this->escape($name) . '">';
		$html .= '<input type="hidden" name="' . $this->escape($name) . '" value="0">';
		$html .= '<label class="bpe-checkbox" for="' . $this->escape($id) . '">';
		$html .= sprintf(
			'<input type="checkbox" class="bpe-checkbox__input" id="%s" name="%s" value="1"%s>',
			$this->escape($id),
			$this->escape($name),
			$checked ? ' checked' : ''
		);
		$html .= '<span class="bpe-checkbox__label">' . $this->escape($field['label'] ?? $name) . '</span>';
		$html .= '</label>';
		if ($hasError) {
			$html .= '<p class="bpe-field__error" role="alert">' . $this->escape($errors[$name]) . '</p>';
		}
		$html .= '</div>';
		return $html;
	}
}
