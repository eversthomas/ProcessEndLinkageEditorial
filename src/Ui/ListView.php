<?php namespace ProcessWire\BsProcessEditorial\Ui;

/**
 * Einfache Listenansicht — 3–5 Spalten, „Neu“ prominent, leerer Zustand.
 */
class ListView {

	public function render(array $schema, array $records, array $options = []): string {
		$label = $schema['label'] ?? $schema['template'];
		$newUrl = $options['newUrl'] ?? '#';
		$editUrl = $options['editUrl'] ?? fn($id) => '#';
		$categoryLabels = $this->optionMap($schema, 'kategorie');

		$html = '<div class="bpe-list">';
		$html .= '<header class="bpe-list__header">';
		$html .= '<h1 class="bpe-list__title">' . $this->e($label) . '</h1>';
		$html .= '<a class="bpe-btn bpe-btn--primary" href="' . $this->e($newUrl) . '">Neu anlegen</a>';
		$html .= '</header>';

		if (empty($records)) {
			$html .= '<div class="bpe-empty">';
			$html .= '<p class="bpe-empty__text">Noch keine Einträge vorhanden.</p>';
			$html .= '<a class="bpe-btn bpe-btn--primary" href="' . $this->e($newUrl) . '">Erste Einrichtung anlegen</a>';
			$html .= '</div>';
			$html .= '</div>';
			return $html;
		}

		$html .= '<div class="bpe-table-wrap">';
		$html .= '<table class="bpe-table">';
		$html .= '<thead><tr>';
		$html .= '<th>Name</th><th>Kategorie</th><th>Aktiv</th><th></th>';
		$html .= '</tr></thead><tbody>';

		foreach ($records as $record) {
			$id = (string) ($record['id'] ?? '');
			$url = is_callable($editUrl) ? $editUrl($id) : str_replace('{id}', $id, (string) $editUrl);
			$kat = $categoryLabels[(string) ($record['kategorie'] ?? '')] ?? ($record['kategorie'] ?? '—');
			$aktiv = !empty($record['aktiv']) ? 'Ja' : 'Nein';
			$aktivClass = !empty($record['aktiv']) ? 'bpe-badge bpe-badge--ok' : 'bpe-badge bpe-badge--muted';

			$html .= '<tr>';
			$html .= '<td><a class="bpe-table__link" href="' . $this->e($url) . '">' . $this->e($record['title'] ?? 'Ohne Titel') . '</a></td>';
			$html .= '<td>' . $this->e($kat) . '</td>';
			$html .= '<td><span class="' . $aktivClass . '">' . $this->e($aktiv) . '</span></td>';
			$html .= '<td class="bpe-table__actions"><a class="bpe-btn bpe-btn--ghost bpe-btn--small" href="' . $this->e($url) . '">Bearbeiten</a></td>';
			$html .= '</tr>';
		}

		$html .= '</tbody></table></div></div>';
		return $html;
	}

	protected function optionMap(array $schema, string $fieldName): array {
		foreach ($schema['fields'] as $field) {
			if (($field['name'] ?? '') === $fieldName) {
				$map = [];
				foreach ($field['options'] ?? [] as $opt) {
					$map[(string) $opt['value']] = $opt['label'];
				}
				return $map;
			}
		}
		return [];
	}

	protected function e(mixed $value): string {
		return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
	}
}
