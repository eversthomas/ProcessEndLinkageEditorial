<?php namespace ProcessWire\BsProcessEditorial\FormEngine\Fields;

class ImageField extends AbstractFieldRenderer {

	public function supports(string $type): bool {
		return $type === 'image';
	}

	public function render(array $field, mixed $value, array $errors = []): string {
		$id = $this->fieldId($field);
		$accept = implode(',', $field['accept'] ?? ['image/jpeg', 'image/png', 'image/webp']);
		$name = $field['name'];
		$fileName = '';
		$fileUrl = '';
		if (is_array($value)) {
			$fileName = (string) ($value['name'] ?? '');
			$fileUrl = (string) ($value['url'] ?? '');
		} elseif (is_string($value) && $value !== '') {
			$fileName = $value;
		}

		$html = $this->wrapperStart($field, $errors);

		if ($fileName !== '') {
			$html .= '<div class="bpe-image-preview">';
			if ($fileUrl !== '') {
				$html .= '<img class="bpe-image-preview__img" src="' . $this->escape($fileUrl)
					. '" alt="' . $this->escape($fileName) . '">';
			}
			$html .= '<p class="bpe-image-preview__name">' . $this->escape($fileName) . '</p>';
			$html .= '<label class="bpe-image-preview__clear">';
			$html .= '<input type="checkbox" name="' . $this->escape($name) . '_clear" value="1"> Bild entfernen';
			$html .= '</label>';
			$html .= '<input type="hidden" name="' . $this->escape($name) . '_existing" value="'
				. $this->escape($fileName) . '">';
			$html .= '</div>';
		}

		$html .= sprintf(
			'<input type="file" class="bpe-input bpe-input--file" id="%s" name="%s" accept="%s"%s>',
			$this->escape($id),
			$this->escape($name),
			$this->escape($accept),
			!empty($field['required']) && $fileName === '' ? ' required' : ''
		);
		$html .= $this->wrapperEnd($field, $errors);
		return $html;
	}
}
