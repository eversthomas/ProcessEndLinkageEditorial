<?php namespace ProcessWire\BsProcessEditorial\FormEngine\Fields;

class TextField extends AbstractFieldRenderer {

	public function supports(string $type): bool {
		return $type === 'text';
	}

	public function render(array $field, mixed $value, array $errors = []): string {
		$id = $this->fieldId($field);
		$html = $this->wrapperStart($field, $errors);
		$html .= sprintf(
			'<input type="text" class="bpe-input" id="%s" name="%s" value="%s" placeholder="%s"%s%s>',
			$this->escape($id),
			$this->escape($field['name']),
			$this->escape($value ?? ''),
			$this->escape($field['placeholder'] ?? ''),
			!empty($field['required']) ? ' required' : '',
			isset($field['maxLength']) ? ' maxlength="' . (int) $field['maxLength'] . '"' : ''
		);
		$html .= $this->wrapperEnd($field, $errors);
		return $html;
	}
}
