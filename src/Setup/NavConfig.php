<?php namespace ProcessWire\BsProcessEditorial\Setup;

use ProcessWire\BsProcessEditorial;

/**
 * Entwickler-konfigurierbare Menühierarchie für die Redaktion.
 *
 * Knotentypen: dashboard | section | group | template
 */
class NavConfig {

	protected BsProcessEditorial $module;

	public function __construct(BsProcessEditorial $module) {
		$this->module = $module;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function tree(): array {
		$raw = $this->module->get('editorial_nav');
		if (is_string($raw) && trim($raw) !== '') {
			$decoded = json_decode($raw, true);
			$raw = is_array($decoded) ? $decoded : null;
		}
		if (!is_array($raw) || $raw === []) {
			return $this->migrateFromTemplates();
		}
		return $this->normalizeTree($raw);
	}

	/**
	 * Tree gefiltert auf erlaubte Templates (+ leere Gruppen entfernen).
	 *
	 * @param string[] $allowedTemplates
	 * @return array<int, array<string, mixed>>
	 */
	public function treeForTemplates(array $allowedTemplates): array {
		return $this->filterTree($this->tree(), $allowedTemplates);
	}

	/**
	 * Rail-Einträge (dashboard + section).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function railItems(array $allowedTemplates): array {
		$items = [];
		foreach ($this->treeForTemplates($allowedTemplates) as $node) {
			$type = $node['type'] ?? '';
			if ($type === 'dashboard' || $type === 'section') {
				$items[] = $node;
			}
		}
		return $items;
	}

	public function findNode(string $id, ?array $tree = null): ?array {
		$tree ??= $this->tree();
		foreach ($tree as $node) {
			if (($node['id'] ?? '') === $id) {
				return $node;
			}
			if (!empty($node['children']) && is_array($node['children'])) {
				$found = $this->findNode($id, $node['children']);
				if ($found) {
					return $found;
				}
			}
		}
		return null;
	}

	/**
	 * Alle Template-Namen unter einem Knoten (rekursiv).
	 *
	 * @return string[]
	 */
	public function templatesUnder(array $node): array {
		$out = [];
		if (($node['type'] ?? '') === 'template' && !empty($node['template'])) {
			$out[] = (string) $node['template'];
		}
		foreach ($node['children'] ?? [] as $child) {
			if (!is_array($child)) {
				continue;
			}
			foreach ($this->templatesUnder($child) as $t) {
				$out[] = $t;
			}
		}
		return array_values(array_unique($out));
	}

	/**
	 * JSON für Setup-Feld.
	 */
	public function toJson(): string {
		return json_encode($this->tree(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
	}

	/**
	 * @param mixed $raw
	 * @return array<int, array<string, mixed>>
	 */
	public function parseAndValidate($raw, array $allowedTemplates): array {
		if (is_array($raw)) {
			$tree = $raw;
		} else {
			$tree = json_decode((string) $raw, true);
			if (!is_array($tree)) {
				throw new \InvalidArgumentException('Menü-JSON ist ungültig.');
			}
		}
		$tree = $this->normalizeTree($tree);
		$unknown = [];
		foreach ($this->collectTemplates($tree) as $tpl) {
			if (!in_array($tpl, $allowedTemplates, true)) {
				$unknown[] = $tpl;
			}
		}
		if ($unknown) {
			// Unbekannte Templates entfernen statt hart fehlzuschlagen
			$tree = $this->filterTree($tree, $allowedTemplates);
		}
		return $tree;
	}

	/**
	 * Kuratierte Lucide-Icon-Namen für Setup-Select.
	 *
	 * @return array<string, string> name => label
	 */
	public static function iconChoices(): array {
		return [
			'layout-dashboard' => 'Dashboard',
			'file-text' => 'Dokument',
			'folder' => 'Ordner',
			'users' => 'Personen',
			'user' => 'Person',
			'calendar' => 'Kalender',
			'image' => 'Bild',
			'settings' => 'Einstellungen',
			'home' => 'Home',
			'newspaper' => 'News',
			'map-pin' => 'Ort',
			'building-2' => 'Gebäude',
			'layers' => 'Ebenen',
			'bookmark' => 'Lesezeichen',
			'star' => 'Stern',
			'inbox' => 'Posteingang',
			'globe' => 'Globus',
			'tag' => 'Tag',
		];
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	protected function migrateFromTemplates(): array {
		$templates = $this->module->editorialTemplateNames();
		$children = [];
		foreach ($templates as $name) {
			$children[] = [
				'id' => 'tpl-' . $name,
				'type' => 'template',
				'template' => $name,
				'label' => $name,
				'icon' => 'file-text',
			];
		}
		return [
			[
				'id' => 'overview',
				'type' => 'dashboard',
				'label' => 'Übersicht',
				'icon' => 'layout-dashboard',
			],
			[
				'id' => 'content',
				'type' => 'section',
				'label' => 'Inhalte',
				'icon' => 'file-text',
				'children' => [
					[
						'id' => 'content-all',
						'type' => 'group',
						'label' => 'Alle Inhalte',
						'icon' => 'folder',
						'children' => $children,
					],
				],
			],
		];
	}

	/**
	 * @param array<int, mixed> $tree
	 * @return array<int, array<string, mixed>>
	 */
	protected function normalizeTree(array $tree): array {
		$out = [];
		$seen = [];
		foreach ($tree as $i => $node) {
			if (!is_array($node)) {
				continue;
			}
			$norm = $this->normalizeNode($node, (string) $i, $seen);
			if ($norm) {
				$out[] = $norm;
			}
		}
		return $out;
	}

	/**
	 * @param array<string, bool> $seen
	 */
	protected function normalizeNode(array $node, string $fallbackId, array &$seen): ?array {
		$type = (string) ($node['type'] ?? '');
		if (!in_array($type, ['dashboard', 'section', 'group', 'template'], true)) {
			return null;
		}

		$id = trim((string) ($node['id'] ?? ''));
		if ($id === '') {
			$id = $type === 'template'
				? 'tpl-' . ($node['template'] ?? $fallbackId)
				: $type . '-' . $fallbackId;
		}
		if (isset($seen[$id])) {
			$id .= '-' . substr(md5($fallbackId . json_encode($node)), 0, 6);
		}
		$seen[$id] = true;

		$label = trim((string) ($node['label'] ?? ''));
		$icon = trim((string) ($node['icon'] ?? ''));
		if ($icon === '') {
			$icon = match ($type) {
				'dashboard' => 'layout-dashboard',
				'group' => 'folder',
				'template' => 'file-text',
				default => 'layers',
			};
		}

		$result = [
			'id' => $id,
			'type' => $type,
			'label' => $label !== '' ? $label : $id,
			'icon' => $icon,
		];

		if ($type === 'template') {
			$tpl = trim((string) ($node['template'] ?? ''));
			if ($tpl === '') {
				return null;
			}
			$result['template'] = $tpl;
			if ($label === '') {
				$result['label'] = $tpl;
			}
			return $result;
		}

		$children = [];
		foreach ($node['children'] ?? [] as $j => $child) {
			if (!is_array($child)) {
				continue;
			}
			$norm = $this->normalizeNode($child, $id . '-' . $j, $seen);
			if ($norm) {
				$children[] = $norm;
			}
		}
		if ($type === 'section' || $type === 'group') {
			$result['children'] = $children;
		}
		return $result;
	}

	/**
	 * @param string[] $allowed
	 * @return array<int, array<string, mixed>>
	 */
	protected function filterTree(array $tree, array $allowed): array {
		$out = [];
		foreach ($tree as $node) {
			$type = $node['type'] ?? '';
			if ($type === 'dashboard') {
				$out[] = $node;
				continue;
			}
			if ($type === 'template') {
				if (in_array($node['template'] ?? '', $allowed, true)) {
					$out[] = $node;
				}
				continue;
			}
			$children = $this->filterTree($node['children'] ?? [], $allowed);
			if ($type === 'section') {
				// Section behalten (auch leer → Empty State im Baum)
				$node['children'] = $children;
				$out[] = $node;
				continue;
			}
			if ($type === 'group' && $children !== []) {
				$node['children'] = $children;
				$out[] = $node;
			}
		}
		return $out;
	}

	/**
	 * @return string[]
	 */
	protected function collectTemplates(array $tree): array {
		$out = [];
		foreach ($tree as $node) {
			foreach ($this->templatesUnder($node) as $t) {
				$out[] = $t;
			}
		}
		return array_values(array_unique($out));
	}
}
