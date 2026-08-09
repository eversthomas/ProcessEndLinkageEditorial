<?php namespace ProcessWire\BsProcessEditorial\FormEngine\Fields;

class SelectField extends AbstractFieldRenderer {

	public function supports(string $type): bool {
		return $type === 'select';
	}

	public function render(array $field, mixed $value, array $errors = []): string {
		$id = $this->fieldId($field);
		$current = (string) ($value ?? '');
		$html = $this->wrapperStart($field, $errors);
		$html .= sprintf(
			'<select class="bpe-input bpe-input--select" id="%s" name="%s"%s>',
			$this->escape($id),
			$this->escape($field['name']),
			!empty($field['required']) ? ' required' : ''
		);
		$html .= '<option value="">— bitte wählen —</option>';
		foreach ($field['options'] ?? [] as $opt) {
			$optValue = (string) ($opt['value'] ?? '');
			$selected = $optValue === $current ? ' selected' : '';
			$html .= sprintf(
				'<option value="%s"%s>%s</option>',
				$this->escape($optValue),
				$selected,
				$this->escape($opt['label'] ?? $optValue)
			);
		}
		$html .= '</select>';
		$html .= $this->wrapperEnd($field, $errors);
		return $html;
	}
}
