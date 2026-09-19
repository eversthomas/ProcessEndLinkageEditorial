<?php namespace ProcessWire\ProcessEndLinkageEditorial\Ui;

use ProcessWire\ProcessEndLinkageEditorial\Adapter\AdapterInterface;
use ProcessWire\ProcessEndLinkageEditorial\Setup\NavConfig;

/**
 * Inhaltsbaum: Gruppen + Inhaltstypen (keine Einzelsätze — die gehören in die Liste).
 */
class NavTree {

	protected AdapterInterface $adapter;

	/** @var callable(string): string */
	protected $url;

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
		$this->url = $url;
		$this->modes = $modes;
		// $navConfig / $recordUrl bewusst ungenutzt (Signatur stabil für Aufrufer)
	}

	/**
	 * @param array<int, array<string, mixed>> $children section children
	 */
	public function render(array $children, array $active = []): string {
		$html = '<div class="bpe-tree">';
		$html .= $this->renderNodes($children, $active);
		$html .= '</div>';
		return $html;
	}

	protected function renderNodes(array $nodes, array $active): string {
		if (!$nodes) {
			return '<p class="bpe-tree__empty">Keine Einträge.</p>';
		}
		// Genau eine Gruppe ohne Geschwister: Gruppen-Ebene überspringen — sonst verdoppelt
		// sich "Inhalte" (Section) → "Alle Inhalte" (einzige Gruppe) ohne Mehrwert.
		if (count($nodes) === 1 && ($nodes[0]['type'] ?? '') === 'group') {
			return $this->renderNodes($nodes[0]['children'] ?? [], $active);
		}
		$html = '<ul class="bpe-tree__list">';
		foreach ($nodes as $node) {
			$html .= $this->renderNode($node, $active);
		}
		$html .= '</ul>';
		return $html;
	}

	protected function renderNode(array $node, array $active): string {
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
			$html .= $this->renderNodes($node['children'] ?? [], $active);
			$html .= '</div></li>';
			return $html;
		}

		if ($type === 'template') {
			$tpl = (string) ($node['template'] ?? '');
			$mode = $this->modes[$tpl] ?? 'list';
			$urlFn = $this->url;
			$href = $urlFn('t/' . $tpl);
			$count = null;
			if ($mode === 'list') {
				try {
					$count = $this->adapter->countRecords($tpl);
				} catch (\Throwable $e) {
					$count = null;
				}
			}
			$html = '<li class="bpe-tree__item bpe-tree__item--template' . ($isActive ? ' is-active' : '') . '">';
			$html .= '<a class="bpe-tree__row" href="' . $this->e($href) . '">';
			$html .= Icons::svg($icon, 'bpe-icon bpe-icon--sm');
			$html .= '<span class="bpe-tree__label">' . $this->e($label) . '</span>';
			if ($count !== null) {
				$html .= '<span class="bpe-tree__count" title="' . $this->e((string) $count . ' Einträge') . '">'
					. (int) $count . '</span>';
			} elseif ($mode === 'single') {
				$html .= '<span class="bpe-tree__count bpe-tree__count--mode" title="Einzelseite">1</span>';
			}
			$html .= '</a></li>';
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
