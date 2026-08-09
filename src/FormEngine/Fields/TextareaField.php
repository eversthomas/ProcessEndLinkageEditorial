<?php namespace ProcessWire\BsProcessEditorial\FormEngine\Fields;

class TextareaField extends AbstractFieldRenderer {

	public function supports(string $type): bool {
		return $type === 'textarea';
	}

	public function render(array $field, mixed $value, array $errors = []): string {
		$id = $this->fieldId($field);
		$rows = (int) ($field['rows'] ?? 5);
		$html = $this->wrapperStart($field, $errors);
		$html .= sprintf(
			'<textarea class="bpe-input bpe-input--textarea" id="%s" name="%s" rows="%d" placeholder="%s"%s>%s</textarea>',
			$this->escape($id),
			$this->escape($field['name']),
			max(3, $rows),
			$this->escape($field['placeholder'] ?? ''),
			!empty($field['required']) ? ' required' : '',
			$this->escape($value ?? '')
		);
		$html .= $this->wrapperEnd($field, $errors);
		return $html;
	}
}
