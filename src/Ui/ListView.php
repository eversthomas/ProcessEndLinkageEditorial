<?php namespace ProcessWire\BsProcessEditorial\Ui;

/**
 * Listenansicht — Broadsheet (Daten) oder Legacy-Tabelle.
 */
class ListView {

	public function render(array $schema, array $records, array $options = []): string {
		$skin = ($options['skin'] ?? 'legacy') === 'daten' ? 'daten' : 'legacy';
		if ($skin === 'daten') {
			return $this->renderDaten($schema, $records, $options);
		}
		return $this->renderLegacy($schema, $records, $options);
	}

	protected function renderDaten(array $schema, array $records, array $options): string {
		$label = $schema['label'] ?? $schema['template'];
		$newUrl = $options['newUrl'] ?? '#';
		$editUrl = $options['editUrl'] ?? fn($id) => '#';
		$columns = $this->listColumns($schema);
		$col4Field = $columns[1] ?? null;
		$col4Label = $col4Field['label'] ?? ($col4Field['name'] ?? 'Info');
		$catField = $this->categoryField($schema, $columns);

		$count = count($records);
		$totalCount = (int) ($options['totalCount'] ?? $count);
		$newLabel = 'Neu anlegen';

		$html = '<div class="bpe-daten">';
		$html .= '<header class="bpe-daten__header">';
		$html .= '<div>';
		$html .= '<span class="bpe-daten__kicker">Inhalte</span>';
		$html .= '<h1>' . $this->e($label) . '</h1>';
		$html .= '</div>';
		$html .= '<a class="btn btn-primary" href="' . $this->e($newUrl) . '">'
			. Icons::svg('plus', 'bpe-icon bpe-icon--sm') . ' ' . $this->e($newLabel) . '</a>';
		$html .= '</header>';

		if (empty($records)) {
			$html .= '<div class="card">';
			$html .= '<p class="card-title">Noch leer</p>';
			$html .= '<p class="card-body">Legen Sie den ersten Eintrag an — er erscheint dann in dieser Liste.</p>';
			$html .= '<a class="btn btn-primary" href="' . $this->e($newUrl) . '">Ersten Eintrag anlegen</a>';
			$html .= '</div></div>';
			return $html;
		}

		$html .= '<table class="table">';
		$html .= '<thead><tr>';
		$html .= '<th>Name</th>';
		$html .= '<th>Kategorie</th>';
		$html .= '<th>Status</th>';
		$html .= '<th>' . $this->e($col4Label) . '</th>';
		$html .= '<th>Aktualisiert</th>';
		$html .= '<th style="width:70px"></th>';
		$html .= '</tr></thead><tbody>';

		foreach ($records as $record) {
			$id = (string) ($record['id'] ?? '');
			$url = is_callable($editUrl) ? $editUrl($id) : str_replace('{id}', $id, (string) $editUrl);
			$name = trim((string) ($record['title'] ?? ''));
			if ($name === '') {
				$name = 'Ohne Titel';
			}
			$status = (string) ($record['status'] ?? 'published');
			$published = $status !== 'unpublished';
			$statusLabel = $published ? 'Veröffentlicht' : 'Entwurf';
			$statusClass = $published ? 'tag-accent' : 'tag-neutral';

			$cat = '—';
			if ($catField) {
				$cat = $this->formatCell($catField, $record[$catField['name']] ?? null, $schema);
				if (trim(strip_tags($cat)) === '' || $cat === '—') {
					$cat = '—';
				}
			}

			$info = '—';
			if ($col4Field) {
				$info = $this->formatCell($col4Field, $record[$col4Field['name']] ?? null, $schema, true);
			}

			$updated = $this->relative((string) ($record['modified'] ?? ''));

			$html .= '<tr>';
			$html .= '<td style="font-family:var(--font-heading)"><a href="' . $this->e($url) . '">'
				. $this->e($name) . '</a></td>';
			$html .= '<td class="text-muted">' . ($cat === '—' ? '—' : $cat) . '</td>';
			$html .= '<td><span class="tag ' . $statusClass . '">' . $this->e($statusLabel) . '</span></td>';
			$html .= '<td class="text-muted">' . $info . '</td>';
			$html .= '<td class="text-muted">' . $this->e($updated) . '</td>';
			$html .= '<td><a class="btn btn-ghost btn-icon" href="' . $this->e($url) . '" aria-label="Bearbeiten">'
				. Icons::svg('pencil', 'bpe-icon bpe-icon--sm') . '</a></td>';
			$html .= '</tr>';
		}

		$html .= '</tbody></table>';
		$html .= '<div class="bpe-daten__foot"><span>'
			. $count . ' von ' . $totalCount . ' Einträgen'
			. ($totalCount > $count ? ' — weitere über Suche/Filter (folgt) erreichbar' : '')
			. '</span></div>';
		$html .= '</div>';
		return $html;
	}

	protected function renderLegacy(array $schema, array $records, array $options): string {
		$label = $schema['label'] ?? $schema['template'];
		$newUrl = $options['newUrl'] ?? '#';
		$editUrl = $options['editUrl'] ?? fn($id) => '#';
		$columns = $this->listColumns($schema);

		$count = count($records);
		$totalCount = (int) ($options['totalCount'] ?? $count);
		$html = '<div class="bpe-list">';
		$html .= '<header class="bpe-list__header">';
		$html .= '<div class="bpe-list__heading">';
		$html .= '<h1 class="bpe-list__title">' . $this->e($label) . '</h1>';
		$html .= '<p class="bpe-list__meta">' . $count . ' ' . ($count === 1 ? 'Eintrag' : 'Einträge')
			. ($totalCount > $count ? ' von ' . $totalCount : '') . '</p>';
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

	/** Kategorie-Spalte: erstes select, sonst zweites Listenspalten-Feld. */
	protected function categoryField(array $schema, array $columns): ?array {
		foreach ($schema['fields'] ?? [] as $field) {
			if (($field['type'] ?? '') === 'select' && ($field['name'] ?? '') !== 'title') {
				return $field;
			}
		}
		return $columns[1] ?? null;
	}

	protected function formatCell(array $field, mixed $value, array $schema, bool $plain = false): string {
		$type = $field['type'] ?? 'text';
		if ($type === 'checkbox') {
			$on = filter_var($value, FILTER_VALIDATE_BOOLEAN);
			if ($plain) {
				return $on ? 'Ja' : 'Nein';
			}
			$class = $on ? 'bpe-badge bpe-badge--ok' : 'bpe-badge bpe-badge--muted';
			return '<span class="' . $class . '">' . ($on ? 'Ja' : 'Nein') . '</span>';
		}
		if ($type === 'select') {
			$map = [];
			foreach ($field['options'] ?? [] as $opt) {
				$map[(string) ($opt['value'] ?? '')] = $opt['label'] ?? $opt['value'];
			}
			if (is_array($value)) {
				$labels = [];
				foreach ($value as $v) {
					$key = (string) $v;
					$labels[] = $map[$key] ?? $key;
				}
				return $labels ? $this->e(implode(', ', $labels)) : '—';
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

	protected function relative(string $iso): string {
		$ts = strtotime($iso);
		if ($ts === false) {
			return $iso !== '' ? $iso : '—';
		}
		$diff = time() - $ts;
		if ($diff < 60) {
			return 'gerade eben';
		}
		if ($diff < 3600) {
			return 'vor ' . (int) floor($diff / 60) . ' Min.';
		}
		if ($diff < 86400) {
			return 'vor ' . (int) floor($diff / 3600) . ' Std.';
		}
		return date('d.m.Y', $ts);
	}

	protected function e(mixed $value): string {
		return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
	}
}
