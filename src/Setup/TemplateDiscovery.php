<?php namespace ProcessWire\ProcessEndLinkageEditorial\Setup;

use ProcessWire\ProcessEndLinkageEditorial;
use ProcessWire\Template;

/**
 * Findet redaktionell plausible Templates in einer beliebigen PW-Installation.
 * Schlägt Darstellung vor: Liste (Datensätze) vs. EinzelSeite (z. B. Home).
 */
class TemplateDiscovery {

	/**
	 * Echte PW-System-Templates, die nie redaktionell freigegeben werden dürfen (auch serverseitig
	 * durchgesetzt, siehe ProcessWireAdapter). Bewusst KEINE site-spezifischen Namenskonventionen
	 * (z. B. "basic-page") — das Modul soll mit beliebigen Template-Namen jeder PW-Installation
	 * funktionieren, nicht nur mit denen, die während der Entwicklung getestet wurden.
	 */
	public const SKIP = [
		'admin', 'user', 'role', 'permission', 'language', 'language-gateway',
	];

	public const MODE_LIST = 'list';
	public const MODE_SINGLE = 'single';

	protected ProcessEndLinkageEditorial $module;

	public function __construct(ProcessEndLinkageEditorial $module) {
		$this->module = $module;
	}

	/**
	 * @return array<int, array{
	 *   name: string,
	 *   label: string,
	 *   pages: int,
	 *   fields: int,
	 *   suggestedMode: string,
	 *   kind: string,
	 *   unsupportedFields: array<int, array{name: string, label: string, type: string}>
	 * }>
	 */
	public function candidates(): array {
		$templates = $this->module->wire()->templates;
		$pages = $this->module->wire()->pages;
		$adapter = new \ProcessWire\ProcessEndLinkageEditorial\Adapter\ProcessWireAdapter($this->module);
		$out = [];

		foreach ($templates as $tpl) {
			/** @var Template $tpl */
			if (!$this->isCandidate($tpl)) {
				continue;
			}
			$count = (int) $pages->count("template={$tpl->name}, include=all");
			$mode = $this->suggestModeForCount($count);
			$out[] = [
				'name' => $tpl->name,
				'label' => $this->label($tpl),
				'pages' => $count,
				'fields' => $tpl->fields->count(),
				'suggestedMode' => $mode,
				'kind' => $mode === self::MODE_SINGLE ? 'Einzelseite' : 'Datensätze',
				'unsupportedFields' => $adapter->unsupportedFieldsForTemplate($tpl->name),
			];
		}

		usort($out, fn($a, $b) => strcasecmp($a['label'], $b['label']));
		return $out;
	}

	/**
	 * @return array<string, string>
	 */
	public function optionsForSelect(): array {
		$options = [];
		foreach ($this->candidates() as $item) {
			$options[$item['name']] = sprintf(
				'%s (%s) — %d Seite(n), Vorschlag: %s',
				$item['label'],
				$item['name'],
				$item['pages'],
				$item['kind']
			);
		}
		return $options;
	}

	public function suggestMode(string $templateName): string {
		$pages = $this->module->wire()->pages;
		$count = (int) $pages->count("template={$templateName}, include=all");
		return $this->suggestModeForCount($count);
	}

	/**
	 * Bewusst nur über die Seitenanzahl entschieden, nicht über den Template-Namen — das Modul
	 * soll unabhängig davon funktionieren, wie ein Entwickler seine Templates genannt hat
	 * (z. B. "home", "homepage", "startseite" sind alle gleich plausibel).
	 */
	public function suggestModeForCount(int $count): string {
		// Genau eine Seite → typischerweise Homepages / Singleton-Inhalte
		if ($count === 1) {
			return self::MODE_SINGLE;
		}
		return self::MODE_LIST;
	}

	public function isCandidate(Template $tpl): bool {
		if ($tpl->flags & Template::flagSystem) {
			return false;
		}
		if (in_array($tpl->name, self::SKIP, true)) {
			return false;
		}
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
