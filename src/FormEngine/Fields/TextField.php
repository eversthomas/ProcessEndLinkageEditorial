<?php namespace ProcessWire\BsProcessEditorial\FormEngine\Fields;

/**
 * text | email | url | integer | float
 */
class TextField extends AbstractFieldRenderer {

	public function supports(string $type): bool {
		return in_array($type, ['text', 'email', 'url', 'integer', 'float'], true);
	}

	public function render(array $field, mixed $value, array $errors = []): string {
		$id = $this->fieldId($field);
		$type = $field['type'] ?? 'text';
		$inputType = match ($type) {
			'email' => 'email',
			'url' => 'url',
			'integer', 'float' => 'number',
			default => 'text',
		};
		$attrs = '';
		if (!empty($field['required'])) {
			$attrs .= ' required';
		}
		if (isset($field['maxLength']) && $inputType === 'text') {
			$attrs .= ' maxlength="' . (int) $field['maxLength'] . '"';
		}
		if ($inputType === 'number') {
			$attrs .= ' step="' . $this->escape($field['step'] ?? ($type === 'float' ? 'any' : '1')) . '"';
			if (isset($field['min']) && $field['min'] !== '') {
				$attrs .= ' min="' . $this->escape($field['min']) . '"';
			}
			if (isset($field['max']) && $field['max'] !== '') {
				$attrs .= ' max="' . $this->escape($field['max']) . '"';
			}
		}
		if ($inputType === 'email') {
			$attrs .= ' autocomplete="email"';
		}
		if ($inputType === 'url') {
			$attrs .= ' inputmode="url"';
		}

		$html = $this->wrapperStart($field, $errors);
		$html .= sprintf(
			'<input type="%s" class="bpe-input" id="%s" name="%s" value="%s" placeholder="%s"%s>',
			$this->escape($inputType),
			$this->escape($id),
			$this->escape($field['name']),
			$this->escape($value ?? ''),
			$this->escape($field['placeholder'] ?? ''),
			$attrs
		);
		$html .= $this->wrapperEnd($field, $errors);
		return $html;
	}
}
