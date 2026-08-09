<?php namespace ProcessWire\BsProcessEditorial\FormEngine\Fields;

class ImageField extends AbstractFieldRenderer {

	public function supports(string $type): bool {
		return $type === 'image';
	}

	public function render(array $field, mixed $value, array $errors = []): string {
		$id = $this->fieldId($field);
		$accept = implode(',', $field['accept'] ?? ['image/jpeg', 'image/png', 'image/webp']);
		$html = $this->wrapperStart($field, $errors);

		if ($value) {
			$html .= '<div class="bpe-image-preview">';
			$html .= '<p class="bpe-image-preview__name">' . $this->escape($value) . '</p>';
			$html .= '<label class="bpe-image-preview__clear">';
			$html .= '<input type="checkbox" name="' . $this->escape($field['name']) . '_clear" value="1"> Bild entfernen';
			$html .= '</label>';
			$html .= '<input type="hidden" name="' . $this->escape($field['name']) . '_existing" value="' . $this->escape($value) . '">';
			$html .= '</div>';
		}

		$html .= sprintf(
			'<input type="file" class="bpe-input bpe-input--file" id="%s" name="%s" accept="%s"%s>',
			$this->escape($id),
			$this->escape($field['name']),
			$this->escape($accept),
			!empty($field['required']) && !$value ? ' required' : ''
		);
		$html .= '<p class="bpe-field__hint">MVP: Dateiname wird gemerkt; Upload-Persistenz folgt mit dem PW-Adapter.</p>';
		$html .= $this->wrapperEnd($field, $errors);
		return $html;
	}
}
