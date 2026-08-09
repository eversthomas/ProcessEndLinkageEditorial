<?php namespace ProcessWire\BsProcessEditorial\Ui;

use ProcessWire\BsProcessEditorial\Adapter\AdapterInterface;
use ProcessWire\BsProcessEditorial\Setup\NavConfig;

/**
 * Dashboard-Übersicht: Kacheln + zuletzt bearbeitet.
 */
class DashboardView {

	public function render(array $options): string {
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
	 * @return array<int, array{title: string, url: string, typeLabel: string, modifiedLabel: string, modified: string}>
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
