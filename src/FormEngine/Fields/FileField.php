<?php namespace ProcessWire\ProcessEndLinkageEditorial\FormEngine\Fields;

class FileField extends AbstractFieldRenderer {

	public function supports(string $type): bool {
		return $type === 'file';
	}

	public function render(array $field, mixed $value, array $errors = []): string {
		$id = $this->fieldId($field);
		$accept = implode(',', $field['accept'] ?? ['.pdf', '.doc', '.docx', '.zip']);
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
			$html .= '<div class="bpe-file-preview">';
			if ($fileUrl !== '') {
				$html .= '<a class="bpe-file-preview__link" href="' . $this->escape($fileUrl)
					. '" target="_blank" rel="noopener">' . $this->escape($fileName) . '</a>';
			} else {
				$html .= '<p class="bpe-file-preview__name">' . $this->escape($fileName) . '</p>';
			}
			$html .= '<label class="bpe-file-preview__clear">';
			$html .= '<input type="checkbox" name="' . $this->escape($name) . '_clear" value="1"> Datei entfernen';
			$html .= '</label>';
			$html .= '<input type="hidden" name="' . $this->escape($name) . '_existing" value="'
				. $this->escape($fileName) . '">';
			$html .= '</div>';
		}

		$html .= sprintf(
			'<input type="file" class="input bpe-input bpe-input--file" id="%s" name="%s" accept="%s"%s>',
			$this->escape($id),
			$this->escape($name),
			$this->escape($accept),
			!empty($field['required']) && $fileName === '' ? ' required' : ''
		);
		$html .= $this->wrapperEnd($field, $errors);
		return $html;
	}
}
