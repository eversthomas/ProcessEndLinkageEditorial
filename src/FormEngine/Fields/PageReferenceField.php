<?php namespace ProcessWire\ProcessEndLinkageEditorial\FormEngine\Fields;

class PageReferenceField extends AbstractFieldRenderer {

	public function supports(string $type): bool {
		return $type === 'pageReference';
	}

	public function render(array $field, mixed $value, array $errors = []): string {
		$id = $this->fieldId($field);
		$multiple = !empty($field['multiple']);
		$selected = array_map('strval', (array) ($value ?? []));
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
			$optId = (string) ($opt['id'] ?? '');
			$isSelected = in_array($optId, $selected, true) ? ' selected' : '';
			$html .= sprintf(
				'<option value="%s"%s>%s</option>',
				$this->escape($optId),
				$isSelected,
				$this->escape($opt['label'] ?? $optId)
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
