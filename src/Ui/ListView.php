<?php namespace ProcessWire\BsProcessEditorial\Ui;

/**
 * Listenansicht — Spalten aus Schema (3–5), „Neu“ prominent, leerer Zustand.
 */
class ListView {

	public function render(array $schema, array $records, array $options = []): string {
		$label = $schema['label'] ?? $schema['template'];
		$newUrl = $options['newUrl'] ?? '#';
		$editUrl = $options['editUrl'] ?? fn($id) => '#';
		$columns = $this->listColumns($schema);

		$count = count($records);
		$html = '<div class="bpe-list">';
		$html .= '<header class="bpe-list__header">';
		$html .= '<div class="bpe-list__heading">';
		$html .= '<h1 class="bpe-list__title">' . $this->e($label) . '</h1>';
		$html .= '<p class="bpe-list__meta">' . $count . ' ' . ($count === 1 ? 'Eintrag' : 'Einträge') . '</p>';
		$html .= '</div>';
		$html .= '<a class="bpe-btn bpe-btn--primary" href="' . $this->e($newUrl) . '">Neu anlegen</a>';
		$html .= '</header>';

		if (empty($records)) {
			$html .= '<div class="bpe-empty">';
			$html .= '<p class="bpe-empty__title">Noch leer</p>';
			$html .= '<p class="bpe-empty__text">Legen Sie den ersten Eintrag an — er erscheint dann in dieser Liste.</p>';
			$html .= '<a class="bpe-btn bpe-btn--primary" href="' . $this->e($newUrl) . '">Ersten Eintrag anlegen</a>';
			$html .= '</div>';
			$html .= '</div>';
			return $html;
		}

		$html .= '<div class="bpe-table-wrap">';
		$html .= '<table class="bpe-table">';
		$html .= '<thead><tr>';
		foreach ($columns as $col) {
			$html .= '<th>' . $this->e($col['label'] ?? $col['name']) . '</th>';
		}
		$html .= '<th></th></tr></thead><tbody>';

		foreach ($records as $record) {
			$id = (string) ($record['id'] ?? '');
			$url = is_callable($editUrl) ? $editUrl($id) : str_replace('{id}', $id, (string) $editUrl);

			$html .= '<tr>';
			foreach ($columns as $i => $col) {
				$name = $col['name'];
				$cell = $this->formatCell($col, $record[$name] ?? null, $schema);
				if ($i === 0) {
					$html .= '<td><a class="bpe-table__link" href="' . $this->e($url) . '">' . $this->e($cell) . '</a></td>';
				} else {
					$html .= '<td>' . $cell . '</td>';
				}
			}
			$html .= '<td class="bpe-table__actions"><a class="bpe-btn bpe-btn--ghost bpe-btn--small" href="'
				. $this->e($url) . '">Bearbeiten</a></td>';
			$html .= '</tr>';
		}

		$html .= '</tbody></table></div></div>';
		return $html;
	}

	/**
	 * Bis zu 4 Listenspalten: title zuerst, dann kompakte Feldtypen.
	 */
	protected function listColumns(array $schema): array {
		$title = null;
		$rest = [];
		foreach ($schema['fields'] ?? [] as $field) {
			$type = $field['type'] ?? '';
			$name = $field['name'] ?? '';
			if ($name === '' || in_array($type, ['image', 'file', 'pageReference', 'textarea', 'html'], true)) {
				continue;
			}
			if ($name === 'title') {
				$title = $field;
			} else {
				$rest[] = $field;
			}
		}
		$cols = [];
		if ($title) {
			$cols[] = $title;
		} elseif ($rest) {
			$cols[] = array_shift($rest);
		}
		foreach ($rest as $field) {
			if (count($cols) >= 4) {
				break;
			}
			$cols[] = $field;
		}
		return $cols;
	}

	protected function formatCell(array $field, mixed $value, array $schema): string {
		$type = $field['type'] ?? 'text';
		if ($type === 'checkbox') {
			$on = filter_var($value, FILTER_VALIDATE_BOOLEAN);
			$class = $on ? 'bpe-badge bpe-badge--ok' : 'bpe-badge bpe-badge--muted';
			return '<span class="' . $class . '">' . ($on ? 'Ja' : 'Nein') . '</span>';
		}
		if ($type === 'select') {
			$map = [];
			foreach ($field['options'] ?? [] as $opt) {
				$map[(string) ($opt['value'] ?? '')] = $opt['label'] ?? $opt['value'];
			}
			$key = (string) ($value ?? '');
			return $this->e($map[$key] ?? ($key !== '' ? $key : '—'));
		}
		if ($type === 'datetime') {
			$text = trim((string) ($value ?? ''));
			if ($text === '') {
				return '—';
			}
			$ts = strtotime(str_replace('T', ' ', $text));
			if (!$ts) {
				return $this->e($text);
			}
			$format = str_contains($text, 'T') ? 'd.m.Y H:i' : 'd.m.Y';
			return $this->e(date($format, $ts));
		}
		if ($type === 'integer' || $type === 'float') {
			return $value === null || $value === '' ? '—' : $this->e((string) $value);
		}
		$text = trim((string) ($value ?? ''));
		return $text !== '' ? $this->e($text) : '—';
	}

	protected function e(mixed $value): string {
		return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
	}
}
