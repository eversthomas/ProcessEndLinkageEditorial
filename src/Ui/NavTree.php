<?php namespace ProcessWire\BsProcessEditorial\Ui;

use ProcessWire\BsProcessEditorial\Adapter\AdapterInterface;
use ProcessWire\BsProcessEditorial\Setup\NavConfig;

/**
 * Hybrid-Inhaltsbaum: Gruppen + Typen; Datensätze nur bei count ≤ HYBRID_RECORD_LIMIT.
 */
class NavTree {

	protected AdapterInterface $adapter;
	protected NavConfig $navConfig;

	/** @var callable(string): string */
	protected $url;

	/** @var callable(string, string): string */
	protected $recordUrl;

	/** @var array<string, string> template => mode */
	protected array $modes;

	public function __construct(
		AdapterInterface $adapter,
		NavConfig $navConfig,
		callable $url,
		callable $recordUrl,
		array $modes = []
	) {
		$this->adapter = $adapter;
		$this->navConfig = $navConfig;
		$this->url = $url;
		$this->recordUrl = $recordUrl;
		$this->modes = $modes;
	}

	/**
	 * @param array<int, array<string, mixed>> $children section children
	 */
	public function render(array $children, array $active = []): string {
		$html = '<div class="bpe-tree">';
		$html .= $this->renderNodes($children, $active, 0);
		$html .= '</div>';
		return $html;
	}

	protected function renderNodes(array $nodes, array $active, int $depth): string {
		if (!$nodes) {
			return '<p class="bpe-tree__empty">Keine Einträge.</p>';
		}
		$html = '<ul class="bpe-tree__list">';
		foreach ($nodes as $node) {
			$html .= $this->renderNode($node, $active, $depth);
		}
		$html .= '</ul>';
		return $html;
	}

	protected function renderNode(array $node, array $active, int $depth): string {
		$type = $node['type'] ?? '';
		$id = $node['id'] ?? '';
		$label = $node['label'] ?? $id;
		$icon = $node['icon'] ?? 'folder';
		$isActive = ($active['node'] ?? null) === $id
			|| (($active['template'] ?? null) && $type === 'template' && ($node['template'] ?? '') === $active['template']);

		if ($type === 'group') {
			$open = $isActive || $this->containsActive($node, $active);
			$html = '<li class="bpe-tree__item bpe-tree__item--group' . ($open ? ' is-open' : '') . '">';
			$html .= '<button type="button" class="bpe-tree__row bpe-tree__toggle" aria-expanded="' . ($open ? 'true' : 'false') . '">';
			$html .= Icons::svg($icon, 'bpe-icon bpe-icon--sm');
			$html .= '<span class="bpe-tree__label">' . $this->e($label) . '</span>';
			$html .= '</button>';
			$html .= '<div class="bpe-tree__children"' . ($open ? '' : ' hidden') . '>';
			$html .= $this->renderNodes($node['children'] ?? [], $active, $depth + 1);
			$html .= '</div></li>';
			return $html;
		}

		if ($type === 'template') {
			$tpl = (string) ($node['template'] ?? '');
			$mode = $this->modes[$tpl] ?? 'list';
			$urlFn = $this->url;
			$href = $urlFn('t/' . $tpl);
			$html = '<li class="bpe-tree__item bpe-tree__item--template' . ($isActive ? ' is-active' : '') . '">';
			$html .= '<a class="bpe-tree__row" href="' . $this->e($href) . '">';
			$html .= Icons::svg($icon, 'bpe-icon bpe-icon--sm');
			$html .= '<span class="bpe-tree__label">' . $this->e($label) . '</span>';
			$html .= '</a>';

			if ($mode === 'list') {
				try {
					$records = $this->adapter->listRecords($tpl);
				} catch (\Throwable $e) {
					$records = [];
				}
				if (count($records) > 0 && count($records) <= NavConfig::HYBRID_RECORD_LIMIT) {
					$recUrl = $this->recordUrl;
					$html .= '<ul class="bpe-tree__list bpe-tree__list--records">';
					foreach ($records as $record) {
						$rid = (string) ($record['id'] ?? '');
						$rtitle = (string) ($record['title'] ?? 'Ohne Titel');
						$rActive = ($active['record'] ?? null) === $rid;
						$html .= '<li class="bpe-tree__item bpe-tree__item--record' . ($rActive ? ' is-active' : '') . '">';
						$html .= '<a class="bpe-tree__row" href="' . $this->e($recUrl($tpl, $rid)) . '">';
						$html .= '<span class="bpe-tree__label">' . $this->e($rtitle) . '</span>';
						$html .= '</a></li>';
					}
					$html .= '</ul>';
				}
			}
			$html .= '</li>';
			return $html;
		}

		return '';
	}

	protected function containsActive(array $node, array $active): bool {
		$activeNode = $active['node'] ?? null;
		$activeTpl = $active['template'] ?? null;
		foreach ($node['children'] ?? [] as $child) {
			if (($child['id'] ?? '') === $activeNode) {
				return true;
			}
			if ($activeTpl && ($child['type'] ?? '') === 'template' && ($child['template'] ?? '') === $activeTpl) {
				return true;
			}
			if ($this->containsActive($child, $active)) {
				return true;
			}
		}
		return false;
	}

	protected function e(mixed $v): string {
		return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
	}
}
