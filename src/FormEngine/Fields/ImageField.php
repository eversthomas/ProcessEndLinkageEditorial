<?php namespace ProcessWire\BsProcessEditorial\FormEngine\Fields;

class ImageField extends AbstractFieldRenderer {

	public function supports(string $type): bool {
		return $type === 'image';
	}

	public function render(array $field, mixed $value, array $errors = []): string {
		$id = $this->fieldId($field);
		$accept = implode(',', $field['accept'] ?? ['image/jpeg', 'image/png', 'image/webp']);
		$name = $field['name'];

		if (!empty($field['multiple'])) {
			return $this->renderMultiple($field, is_array($value) ? $value : [], $errors);
		}

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
			$html .= '<input type="checkbox" name="' . $this->escape($this->siblingName($name, '_clear')) . '" value="1"> Bild entfernen';
			$html .= '</label>';
			$html .= '<input type="hidden" name="' . $this->escape($this->siblingName($name, '_existing')) . '" value="'
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

	/**
	 * @param array<int, array{name?: string, url?: string}> $items
	 */
	protected function renderMultiple(array $field, array $items, array $errors): string {
		$id = $this->fieldId($field);
		$name = $field['name'];
		$accept = implode(',', $field['accept'] ?? ['image/jpeg', 'image/png', 'image/webp']);

		$html = $this->wrapperStart($field, $errors);

		if ($items) {
			$html .= '<div class="bpe-image-grid">';
			foreach ($items as $item) {
				$fileName = (string) ($item['name'] ?? '');
				if ($fileName === '') {
					continue;
				}
				$fileUrl = (string) ($item['url'] ?? '');
				$html .= '<div class="bpe-image-grid__item">';
				if ($fileUrl !== '') {
					$html .= '<img class="bpe-image-grid__img" src="' . $this->escape($fileUrl)
						. '" alt="' . $this->escape($fileName) . '">';
				}
				$html .= '<label class="bpe-image-grid__remove">';
				$html .= '<input type="checkbox" name="' . $this->escape($this->siblingName($name, '_remove')) . '[]" value="'
					. $this->escape($fileName) . '"> Entfernen';
				$html .= '</label>';
				$html .= '</div>';
			}
			$html .= '</div>';
		}

		$html .= sprintf(
			'<input type="file" class="input bpe-input bpe-input--file" id="%s" name="%s[]" accept="%s" multiple%s>',
			$this->escape($id),
			$this->escape($name),
			$this->escape($accept),
			(!empty($field['required']) && !$items) ? ' required' : ''
		);
		$html .= $this->wrapperEnd($field, $errors);
		return $html;
	}
}
