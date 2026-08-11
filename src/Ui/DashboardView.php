<?php namespace ProcessWire\BsProcessEditorial\Ui;

use ProcessWire\BsProcessEditorial\Adapter\AdapterInterface;

/**
 * Dashboard-Übersicht: Broadsheet (Daten) oder Legacy-Kacheln.
 */
class DashboardView {

	public function render(array $options): string {
		$skin = ($options['skin'] ?? 'daten') === 'legacy' ? 'legacy' : 'daten';
		if ($skin === 'daten') {
			return $this->renderDaten($options);
		}
		return $this->renderLegacy($options);
	}

	protected function renderDaten(array $options): string {
		$title = $options['title'] ?? 'Übersicht';
		$lead = $options['lead'] ?? '';
		$kicker = $options['kicker'] ?? '';
		$tiles = $options['tiles'] ?? [];
		$recent = $options['recent'] ?? [];

		$html = '<div class="bpe-daten">';
		$html .= '<header class="bpe-daten__header bpe-daten__header--dash">';
		$html .= '<div>';
		if ($kicker !== '') {
			$html .= '<span class="bpe-daten__kicker">' . $this->e($kicker) . '</span>';
		}
		$html .= '<h1>' . $this->e($title) . '</h1>';
		if ($lead !== '') {
			$html .= '<p class="bpe-daten__lead">' . $this->e($lead) . '</p>';
		}
		$html .= '</div>';
		$primaryNew = null;
		foreach ($tiles as $tile) {
			if (!empty($tile['newUrl'])) {
				$primaryNew = $tile;
				break;
			}
		}
		if ($primaryNew) {
			$html .= '<div class="bpe-daten__actions">';
			$html .= '<a class="btn btn-primary" href="' . $this->e($primaryNew['newUrl']) . '">'
				. Icons::svg('plus', 'bpe-icon bpe-icon--sm')
				. ' Neu: ' . $this->e($primaryNew['label'] ?? '') . '</a>';
			$html .= '</div>';
		}
		$html .= '</header>';

		if ($tiles) {
			$html .= '<h2 class="bpe-daten__section-title">Inhalte im Überblick</h2>';
			$html .= '<div class="bpe-overview">';
			$i = 0;
			foreach ($tiles as $tile) {
				$i++;
				$url = $tile['url'] ?? '#';
				$count = $tile['count'];
				$hint = (string) ($tile['hint'] ?? '');
				$dt = (string) ($tile['datatype'] ?? 'daten');
				if ($dt !== 'daten') {
					$hint = ($hint !== '' ? $hint . ' · ' : '') . 'Ansicht noch generisch';
				}
				$html .= '<a class="bpe-overview__row" href="' . $this->e($url) . '">';
				$html .= '<span class="bpe-overview__num">' . sprintf('%02d', $i) . '</span>';
				$html .= '<span class="bpe-overview__label">' . $this->e($tile['label'] ?? '') . '</span>';
				$html .= '<span class="bpe-overview__hint">' . $this->e($hint) . '</span>';
				$html .= '<span class="bpe-overview__count">' . ($count === null ? '—' : (int) $count) . '</span>';
				$html .= '</a>';
			}
			$html .= '</div>';
		}

		$html .= '<div class="bpe-daten__lower">';
		$html .= '<div>';
		$html .= '<h2 class="bpe-daten__section-title">Zuletzt bearbeitet</h2>';
		if (!$recent) {
			$html .= '<p class="text-muted">Noch keine bearbeiteten Einträge in Ihren Inhaltstypen.</p>';
		} else {
			$html .= '<table class="table"><thead><tr>';
			$html .= '<th>Name</th><th>Typ</th><th>Status</th><th>Aktualisiert</th>';
			$html .= '</tr></thead><tbody>';
			foreach ($recent as $item) {
				$status = (string) ($item['status'] ?? 'published');
				$published = $status !== 'unpublished';
				$html .= '<tr>';
				$html .= '<td style="font-family:var(--font-heading)"><a href="'
					. $this->e($item['url'] ?? '#') . '">' . $this->e($item['title'] ?? 'Ohne Titel') . '</a></td>';
				$html .= '<td class="text-muted">' . $this->e($item['typeLabel'] ?? '') . '</td>';
				$html .= '<td><span class="tag ' . ($published ? 'tag-accent' : 'tag-neutral') . '">'
					. ($published ? 'Veröffentlicht' : 'Entwurf') . '</span></td>';
				$html .= '<td class="text-muted">' . $this->e($item['modifiedLabel'] ?? '') . '</td>';
				$html .= '</tr>';
			}
			$html .= '</tbody></table>';
		}
		$html .= '</div></div></div>';
		return $html;
	}

	protected function renderLegacy(array $options): string {
		$title = $options['title'] ?? 'Übersicht';
		$lead = $options['lead'] ?? 'Was zuletzt bearbeitet wurde und woran Sie weiterarbeiten können.';
		$tiles = $options['tiles'] ?? [];
		$recent = $options['recent'] ?? [];

		$html = '<div class="bpe-dash">';
		$html .= '<header class="bpe-dash__header">';
		$html .= '<h1 class="bpe-dash__title">' . $this->e($title) . '</h1>';
		$html .= '<p class="bpe-dash__lead">' . $this->e($lead) . '</p>';
		$html .= '</header>';

		if ($tiles) {
			$html .= '<section class="bpe-dash__section" aria-label="Inhaltstypen">';
			$html .= '<h2 class="bpe-dash__section-title">Schnellzugriff</h2>';
			$html .= '<div class="bpe-dash__tiles">';
			foreach ($tiles as $tile) {
				$url = $tile['url'] ?? '#';
				$icon = $tile['icon'] ?? 'file-text';
				$count = $tile['count'] ?? null;
				$mode = $tile['mode'] ?? 'list';
				$html .= '<a class="bpe-tile" href="' . $this->e($url) . '">';
				$html .= '<span class="bpe-tile__icon">' . Icons::svg($icon) . '</span>';
				$html .= '<span class="bpe-tile__body">';
				$html .= '<span class="bpe-tile__label">' . $this->e($tile['label'] ?? '') . '</span>';
				$meta = [];
				if ($count !== null) {
					$meta[] = (int) $count . ' ' . ((int) $count === 1 ? 'Eintrag' : 'Einträge');
				}
				$meta[] = $mode === 'single' ? 'Einzelseite' : 'Liste';
				$html .= '<span class="bpe-tile__meta">' . $this->e(implode(' · ', $meta)) . '</span>';
				$html .= '</span>';
				if ($mode === 'list' && !empty($tile['newUrl'])) {
					$html .= '<span class="bpe-tile__action" data-href="' . $this->e($tile['newUrl']) . '">'
						. Icons::svg('plus', 'bpe-icon bpe-icon--sm') . '</span>';
				}
				$html .= '</a>';
			}
			$html .= '</div></section>';
		}

		$html .= '<section class="bpe-dash__section" aria-label="Zuletzt bearbeitet">';
		$html .= '<h2 class="bpe-dash__section-title">Zuletzt bearbeitet</h2>';
		if (!$recent) {
			$html .= '<div class="bpe-empty bpe-empty--soft">';
			$html .= '<p class="bpe-empty__text">Noch keine bearbeiteten Einträge in Ihren Inhaltstypen.</p>';
			$html .= '</div>';
		} else {
			$html .= '<ul class="bpe-dash__recent">';
			foreach ($recent as $item) {
				$html .= '<li><a class="bpe-dash__recent-link" href="' . $this->e($item['url'] ?? '#') . '">';
				$html .= '<span class="bpe-dash__recent-title">' . $this->e($item['title'] ?? 'Ohne Titel') . '</span>';
				$html .= '<span class="bpe-dash__recent-meta">'
					. $this->e($item['typeLabel'] ?? '') . ' · '
					. $this->e($item['modifiedLabel'] ?? '')
					. '</span>';
				$html .= '</a></li>';
			}
			$html .= '</ul>';
		}
		$html .= '</section></div>';
		return $html;
	}

	/**
	 * @param string[] $templates
	 * @return array<int, array{title: string, url: string, typeLabel: string, modifiedLabel: string, modified: string, status: string}>
	 */
	public function collectRecent(AdapterInterface $adapter, array $templates, callable $editUrl, int $limit = 8): array {
		$all = [];
		foreach ($templates as $tpl) {
			try {
				$schema = $adapter->readSchema($tpl);
				$label = $schema['label'] ?? $tpl;
				foreach ($adapter->listRecords($tpl) as $record) {
					$all[] = [
						'title' => (string) ($record['title'] ?? 'Ohne Titel'),
						'url' => $editUrl($tpl, (string) ($record['id'] ?? '')),
						'typeLabel' => $label,
						'modified' => (string) ($record['modified'] ?? ''),
						'modifiedLabel' => $this->relative((string) ($record['modified'] ?? '')),
						'status' => (string) ($record['status'] ?? 'published'),
					];
				}
			} catch (\Throwable $e) {
				continue;
			}
		}
		usort($all, fn($a, $b) => strcmp($b['modified'], $a['modified']));
		return array_slice($all, 0, $limit);
	}

	protected function relative(string $iso): string {
		$ts = strtotime($iso);
		if ($ts === false) {
			return $iso;
		}
		$diff = time() - $ts;
		if ($diff < 60) {
			return 'gerade eben';
		}
		if ($diff < 3600) {
			$m = (int) floor($diff / 60);
			return 'vor ' . $m . ' Min.';
		}
		if ($diff < 86400) {
			$h = (int) floor($diff / 3600);
			return 'vor ' . $h . ' Std.';
		}
		return date('d.m.Y', $ts);
	}

	protected function e(mixed $v): string {
		return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
	}
}
