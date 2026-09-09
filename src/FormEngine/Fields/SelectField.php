<?php namespace ProcessWire\BsProcessEditorial\FormEngine\Fields;

class SelectField extends AbstractFieldRenderer {

	public function supports(string $type): bool {
		return $type === 'select';
	}

	public function render(array $field, mixed $value, array $errors = []): string {
		$id = $this->fieldId($field);
		$multiple = !empty($field['multiple']);
		$selected = array_map('strval', (array) ($value ?? []));
		$current = (string) ($value ?? '');
		$html = $this->wrapperStart($field, $errors);

		$selectName = $this->escape($field['name']) . ($multiple ? '[]' : '');
		$html .= sprintf(
			'<select class="input bpe-input bpe-input--select" id="%s" name="%s"%s%s>',
			$this->escape($id),
			$selectName,
			$multiple ? ' multiple' : '',
			!empty($field['required']) ? ' required' : ''
		);
		if (!$multiple) {
			$html .= '<option value="">— bitte wählen —</option>';
		}
		foreach ($field['options'] ?? [] as $opt) {
			$optValue = (string) ($opt['value'] ?? '');
			$isSelected = $multiple
				? in_array($optValue, $selected, true)
				: $optValue === $current;
			$html .= sprintf(
				'<option value="%s"%s>%s</option>',
				$this->escape($optValue),
				$isSelected ? ' selected' : '',
				$this->escape($opt['label'] ?? $optValue)
			);
		}
		$html .= '</select>';
		if ($multiple) {
			$html .= '<p class="bpe-field__hint">Mehrfachauswahl mit Strg/Cmd.</p>';
		}
		$html .= $this->wrapperEnd($field, $errors);
		return $html;
	}
}
