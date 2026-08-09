<?php namespace ProcessWire\BsProcessEditorial\FormEngine\Fields;

class TextareaField extends AbstractFieldRenderer {

	public function supports(string $type): bool {
		return $type === 'textarea' || $type === 'html';
	}

	public function render(array $field, mixed $value, array $errors = []): string {
		$id = $this->fieldId($field);
		$rows = (int) ($field['rows'] ?? 5);
		$isHtml = ($field['type'] ?? '') === 'html' || !empty($field['html']);
		$class = $isHtml ? 'bpe-input bpe-input--textarea bpe-input--html' : 'bpe-input bpe-input--textarea';
		$html = $this->wrapperStart($field, $errors);
		$html .= sprintf(
			'<textarea class="%s" id="%s" name="%s" rows="%d" placeholder="%s"%s>%s</textarea>',
			$this->escape($class),
			$this->escape($id),
			$this->escape($field['name']),
			max($isHtml ? 12 : 3, $rows),
			$this->escape($field['placeholder'] ?? ''),
			!empty($field['required']) ? ' required' : '',
			$this->escape($value ?? '')
		);
		$html .= $this->wrapperEnd($field, $errors);
		return $html;
	}
}
