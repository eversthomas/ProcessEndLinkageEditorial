<?php namespace ProcessWire\ProcessEndLinkageEditorial\Setup;

use ProcessWire\ProcessEndLinkageEditorial;

/**
 * Visueller Menü-Builder für Setup → Redaktion (schreibt editorial_nav JSON).
 *
 * Der Baum ist zugleich die Freigabe-Oberfläche: ein Template-Knoten im Baum = für die
 * Redaktion freigegeben. Ergänzend dazu ein „Neue Inhalte hinzufügen"-Bereich (ersetzt die
 * frühere separate Discovery-Tabelle + AsmSelect) und eine schematische Live-Vorschau.
 */
class NavBuilder {

	protected ProcessEndLinkageEditorial $module;
	protected NavConfig $navConfig;

	public function __construct(ProcessEndLinkageEditorial $module) {
		$this->module = $module;
		$this->navConfig = new NavConfig($module);
	}

	/**
	 * @param array<int, array<string, mixed>> $tree
	 * @param string[] $templateOptions template => Anzeige-Label (bereits im Baum)
	 * @param array<string, array{mode: string, datatype: string}> $templateSettings
	 * @param array<int, array{name: string, label: string, pages: int, fields: int, suggestedMode: string, kind: string, unsupportedFields: array}> $candidates noch nicht im Baum
	 * @param array<string, string> $datatypeLabels
	 */
	public function renderMarkup(
		array $tree,
		array $templateOptions,
		array $templateSettings,
		array $candidates,
		array $datatypeLabels
	): string {
		$icons = NavConfig::iconChoices();
		$json = fn(mixed $v) => htmlspecialchars(
			json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]',
			ENT_QUOTES,
			'UTF-8'
		);

		$html = '<div class="bpe-navbuilder" id="bpe-navbuilder"';
		$html .= ' data-tree="' . $json($tree) . '"';
		$html .= ' data-templates="' . $json($templateOptions) . '"';
		$html .= ' data-icons="' . $json($icons) . '"';
		$html .= ' data-settings="' . $json($templateSettings) . '"';
		$html .= ' data-candidates="' . $json(array_values($candidates)) . '"';
		$html .= ' data-datatypes="' . $json($datatypeLabels) . '">';

		$html .= '<p class="description">Ein Template im Baum unten = für die Redaktion freigegeben. '
			. '<strong>Section</strong> erscheint als Hauptpunkt in der linken Leiste, '
			. '<strong>Gruppe</strong> als Überschrift im Inhaltsbaum der Redaktion. '
			. 'Darstellung und Daten-Art stellst du direkt an jedem Inhaltstyp ein.</p>';

		$html .= '<div class="bpe-navbuilder__layout">';
		$html .= '<div class="bpe-navbuilder__editor">';
		$html .= '<div class="bpe-navbuilder__toolbar">';
		$html .= '<button type="button" class="ui-button ui-widget ui-corner-all" data-bpe-add-section>+ Section</button>';
		$html .= '<button type="button" class="ui-button ui-widget ui-corner-all" data-bpe-add-dashboard>+ Übersicht</button>';
		$html .= '</div>';
		$html .= '<div class="bpe-navbuilder__list" data-bpe-nav-list></div>';

		$html .= '<div class="bpe-navbuilder__section-heading">Neue Inhalte hinzufügen</div>';
		$html .= '<p class="bpe-navbuilder__muted">Templates aus dieser Installation, die noch nicht freigegeben sind.</p>';
		$html .= '<div class="bpe-navbuilder__add" data-bpe-nav-add></div>';
		$html .= '</div>';

		$html .= '<div class="bpe-navbuilder__preview">';
		$html .= '<div class="bpe-navbuilder__section-heading">Vorschau (schematisch)</div>';
		$html .= '<div class="bpe-navbuilder__preview-body" data-bpe-nav-preview></div>';
		$html .= '</div>';
		$html .= '</div>';

		$html .= '<style>' . $this->css() . '</style>';
		return $html;
	}

	protected function css(): string {
		return <<<'CSS'
.bpe-navbuilder { margin: 0 0 1rem; }
.bpe-navbuilder__toolbar { display:flex; gap:.5rem; margin:0 0 .75rem; flex-wrap:wrap; }
.bpe-navbuilder__layout { display:flex; gap:1.5rem; align-items:flex-start; flex-wrap:wrap; }
.bpe-navbuilder__editor { flex: 2 1 480px; min-width:320px; }
.bpe-navbuilder__preview { flex: 1 1 260px; min-width:240px; position:sticky; top:1rem; }
.bpe-navbuilder__preview-body {
  border:1px solid #d2d6d3; border-radius:6px; background:#fff; padding:.75rem .9rem; font-size:13px;
}
.bpe-navbuilder__section-heading { font-weight:600; margin:1.25rem 0 .35rem; font-size:13px; text-transform:uppercase; letter-spacing:.02em; color:#444; }
.bpe-navbuilder__list { display:flex; flex-direction:column; gap:.75rem; }
.bpe-navbuilder__card {
  border:1px solid #d2d6d3; border-radius:6px; background:#fafbfa; padding:.75rem .9rem;
}
.bpe-navbuilder__card--dashboard { background:#f0f5f2; }
.bpe-navbuilder__row { display:flex; flex-wrap:wrap; gap:.5rem; align-items:flex-end; margin-bottom:.5rem; }
.bpe-navbuilder__row label,
.bpe-navbuilder__tpl label { display:flex; flex-direction:column; gap:.2rem; font-size:12px; color:#555; }
.bpe-navbuilder__row input[type=text],
.bpe-navbuilder__row select { min-width:8rem; }
.bpe-navbuilder__hint { flex-basis:100%; font-size:11px; color:#888; margin-top:-.3rem; }
.bpe-navbuilder__actions { margin-left:auto; display:flex; gap:.25rem; }
.bpe-navbuilder__actions button { font-size:12px; }
.bpe-navbuilder__children { margin:.5rem 0 0 1rem; padding-left:.75rem; border-left:2px solid #dde2df; display:flex; flex-direction:column; gap:.5rem; }
.bpe-navbuilder__group {
  border:1px dashed #cfd5d1; border-radius:5px; padding:.55rem .65rem; background:#fff;
}
.bpe-navbuilder__badge {
  display:inline-block; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.04em;
  padding:.15rem .5rem; border-radius:3px; margin-bottom:.5rem;
}
.bpe-navbuilder__badge--dashboard { background:#1f6b4a; color:#fff; }
.bpe-navbuilder__badge--section { background:#24292a; color:#fff; }
.bpe-navbuilder__badge--group { background:#fff; color:#555; border:1px solid #cfd5d1; }
.bpe-navbuilder__advanced { margin-top:.4rem; }
.bpe-navbuilder__advanced summary { cursor:pointer; font-size:11px; color:#888; }
.bpe-navbuilder__advanced label { display:flex; flex-direction:column; gap:.2rem; font-size:12px; color:#555; margin-top:.35rem; max-width:16rem; }
.bpe-navbuilder__addlink {
  align-self:flex-start; background:none; border:1px dashed #b7bdb9; color:#3a5c4a;
  padding:.35rem .7rem; border-radius:4px; font-size:12px; cursor:pointer;
}
.bpe-navbuilder__addlink:hover { background:#eef3f0; }
.bpe-navbuilder__tpls { display:flex; flex-direction:column; gap:.35rem; margin-top:.4rem; }
.bpe-navbuilder__tpl {
  display:flex; flex-wrap:wrap; gap:.4rem; align-items:center; padding:.35rem .4rem; background:#f7f8f7; border-radius:4px;
}
.bpe-navbuilder__tpl-settings { display:flex; gap:.4rem; margin-left:auto; }
.bpe-navbuilder__tpl-settings select { font-size:12px; }
.bpe-navbuilder__muted { color:#777; font-size:12px; margin:0 0 .5rem; }
.bpe-navbuilder__add table { width:100%; border-collapse:collapse; font-size:13px; }
.bpe-navbuilder__add th, .bpe-navbuilder__add td { text-align:left; padding:.4rem .5rem; border-bottom:1px solid #e4e7e5; }
.bpe-navbuilder__add select { font-size:12px; }
.bpe-navbuilder__preview ul { list-style:none; margin:0; padding-left:0; }
.bpe-navbuilder__preview .bpv-section > ul { padding-left:.9rem; }
.bpe-navbuilder__preview .bpv-group > ul { padding-left:.9rem; }
.bpe-navbuilder__preview .bpv-dashboard,
.bpe-navbuilder__preview .bpv-section-label { font-weight:600; margin-top:.5rem; }
.bpe-navbuilder__preview .bpv-group-label { font-weight:500; color:#555; margin-top:.3rem; font-size:12px; text-transform:uppercase; letter-spacing:.02em; }
.bpe-navbuilder__preview .bpv-template { padding:.15rem 0; color:#333; }
CSS;
	}
}
