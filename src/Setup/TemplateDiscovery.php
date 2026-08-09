<?php namespace ProcessWire\BsProcessEditorial\Setup;

use ProcessWire\BsProcessEditorial;
use ProcessWire\Template;

/**
 * Findet redaktionell plausible Templates in einer beliebigen PW-Installation.
 * System-/Admin-Templates werden ausgeschlossen; Freigabe bleibt Konfigurationssache.
 */
class TemplateDiscovery {

	/** Templates, die nie als Redaktions-Inhaltstyp angeboten werden */
	protected const SKIP = [
		'admin', 'user', 'role', 'permission', 'language', 'language-gateway',
		'basic-page', // typischer Container, keine Datensatz-Sammlung
	];

	protected BsProcessEditorial $module;

	public function __construct(BsProcessEditorial $module) {
		$this->module = $module;
	}

	/**
	 * @return array<int, array{name: string, label: string, pages: int, fields: int}>
	 */
	public function candidates(): array {
		$templates = $this->module->wire()->templates;
		$pages = $this->module->wire()->pages;
		$out = [];

		foreach ($templates as $tpl) {
			/** @var Template $tpl */
			if (!$this->isCandidate($tpl)) {
				continue;
			}
			$out[] = [
				'name' => $tpl->name,
				'label' => $this->label($tpl),
				'pages' => (int) $pages->count("template={$tpl->name}, include=all"),
				'fields' => $tpl->fields->count(),
			];
		}

		usort($out, function ($a, $b) {
			return strcasecmp($a['label'], $b['label']);
		});

		return $out;
	}

	/**
	 * Optionen für AsmSelect: name => "Label (name) — N Seiten".
	 *
	 * @return array<string, string>
	 */
	public function optionsForSelect(): array {
		$options = [];
		foreach ($this->candidates() as $item) {
			$options[$item['name']] = sprintf(
				'%s (%s) — %d Seite(n)',
				$item['label'],
				$item['name'],
				$item['pages']
			);
		}
		return $options;
	}

	public function isCandidate(Template $tpl): bool {
		if ($tpl->flags & Template::flagSystem) {
			return false;
		}
		if (in_array($tpl->name, self::SKIP, true)) {
			return false;
		}
		// Mindestens title + ein weiteres Feld, sonst kaum redaktionell sinnvoll
		if ($tpl->fields->count() < 2) {
			return false;
		}
		return true;
	}

	protected function label(Template $tpl): string {
		$label = trim((string) $tpl->get('label'));
		return $label !== '' ? $label : ucfirst($tpl->name);
	}
}
