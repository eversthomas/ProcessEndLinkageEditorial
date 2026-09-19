<?php namespace ProcessWire\ProcessEndLinkageEditorial\Ui;

use ProcessWire\ProcessEndLinkageEditorial\Adapter\AdapterInterface;

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
		$statusCounts = $options['statusCounts'] ?? ['draft' => 0, 'live' => 0];
		$draft = (int) ($statusCounts['draft'] ?? 0);
		$live = (int) ($statusCounts['live'] ?? 0);

		$html = '<div class="bpe-dashboard">';

		$html .= '<header class="bpe-dashboard__intro">';
		if ($kicker !== '') {
			$html .= '<p class="bpe-dashboard__kicker">' . $this->e($kicker) . '</p>';
		}
		$html .= '<h1>' . $this->e($title) . '</h1>';
		if ($lead !== '') {
			$html .= '<p class="bpe-dashboard__lead">' . $this->e($lead) . '</p>';
		}
		$html .= '</header>';

		$html .= '<div class="bpe-statusline">';
		$html .= '<span class="bpe-statusline__item"><span class="bpe-dot bpe-dot--draft"></span>'
			. '<strong>' . $draft . '</strong> Entwurf' . ($draft === 1 ? '' : 'e') . '</span>';
		$html .= '<span class="bpe-statusline__item"><span class="bpe-dot bpe-dot--live"></span>'
			. '<strong>' . $live . '</strong> veröffentlicht</span>';
		$html .= '</div>';

		if ($tiles) {
			$html .= '<section class="bpe-dashboard__section">';
			$html .= '<h2 class="bpe-dashboard__section-title">Inhalte</h2>';
			$html .= '<div class="bpe-tilegrid">';
			foreach ($tiles as $tile) {
				$count = $tile['count'];
				$countLabel = $count === null
					? (string) ($tile['hint'] ?? '')
					: (int) $count . ($count === 1 ? ' Eintrag' : ' Einträge');
				$html .= '<a class="bpe-tilecard" href="' . $this->e($tile['url'] ?? '#') . '">';
				if (!empty($tile['newUrl'])) {
					$html .= '<span class="bpe-tile__action bpe-tilecard__new" data-href="'
						. $this->e($tile['newUrl']) . '" title="Neu anlegen" aria-label="Neu anlegen">+</span>';
				}
				$html .= '<span class="bpe-tilecard__label">' . $this->e($tile['label'] ?? '') . '</span>';
				$html .= '<span class="bpe-tilecard__meta">' . $this->e($countLabel) . '</span>';
				$html .= '</a>';
			}
			$html .= '</div>';
			$html .= '</section>';
		}

		$html .= '<section class="bpe-dashboard__section">';
		$html .= '<h2 class="bpe-dashboard__section-title">Zuletzt bearbeitet</h2>';
		if ($recent) {
			$html .= '<ul class="bpe-recentlist">';
			foreach ($recent as $item) {
				$status = (string) ($item['status'] ?? 'published');
				$published = $status !== 'unpublished';
				$by = $item['modifiedBy'] ?? null;
				$meta = $this->e($item['typeLabel'] ?? '') . ' · ' . $this->e($item['modifiedLabel'] ?? '');
				if ($by) {
					$meta .= ' · ' . $this->e((string) $by);
				}
				$html .= '<li class="bpe-recentlist__row">';
				$html .= '<a class="bpe-recentlist__link" href="' . $this->e($item['url'] ?? '#') . '">';
				$html .= '<span class="bpe-recentlist__title">' . $this->e($item['title'] ?? 'Ohne Titel') . '</span>';
				$html .= '<span class="bpe-recentlist__meta">' . $meta . '</span>';
				$html .= '</a>';
				$html .= '<span class="bpe-status-text bpe-status-text--' . ($published ? 'live' : 'draft') . '">'
					. ($published ? 'Live' : 'Entwurf') . '</span>';
				$html .= '</li>';
			}
			$html .= '</ul>';
		} else {
			$html .= '<p class="bpe-dashboard__empty">Noch keine bearbeiteten Einträge in Ihren Inhaltstypen.</p>';
		}
		$html .= '</section>';

		$html .= '</div>';
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
		$labels = [];
		foreach ($templates as $tpl) {
			try {
				$schema = $adapter->readSchema($tpl);
				$labels[$tpl] = $schema['label'] ?? $tpl;
			} catch (\Throwable $e) {
				continue;
			}
		}
		if (!$labels) {
			return [];
		}

		try {
			$recent = $adapter->recentRecords(array_keys($labels), $limit);
		} catch (\Throwable $e) {
			return [];
		}

		$out = [];
		foreach ($recent as $record) {
			$tpl = (string) ($record['template'] ?? '');
			$out[] = [
				'title' => (string) ($record['title'] ?? 'Ohne Titel'),
				'url' => $editUrl($tpl, (string) ($record['id'] ?? '')),
				'typeLabel' => $labels[$tpl] ?? $tpl,
				'modified' => (string) ($record['modified'] ?? ''),
				'modifiedLabel' => $this->relative((string) ($record['modified'] ?? '')),
				'modifiedBy' => $record['modifiedBy'] ?? null,
				'status' => (string) ($record['status'] ?? 'published'),
			];
		}
		return $out;
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
