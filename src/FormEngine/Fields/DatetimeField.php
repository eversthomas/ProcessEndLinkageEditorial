<?php namespace ProcessWire\ProcessEndLinkageEditorial\FormEngine\Fields;

class DatetimeField extends AbstractFieldRenderer {

	public function supports(string $type): bool {
		return $type === 'datetime';
	}

	public function render(array $field, mixed $value, array $errors = []): string {
		$id = $this->fieldId($field);
		$includeTime = !empty($field['includeTime']);
		$inputType = $includeTime ? 'datetime-local' : 'date';
		$html = $this->wrapperStart($field, $errors);
		$html .= sprintf(
			'<input type="%s" class="input bpe-input" id="%s" name="%s" value="%s"%s>',
			$this->escape($inputType),
			$this->escape($id),
			$this->escape($field['name']),
			$this->escape($value ?? ''),
			!empty($field['required']) ? ' required' : ''
		);
		$html .= $this->wrapperEnd($field, $errors);
		return $html;
	}
}
