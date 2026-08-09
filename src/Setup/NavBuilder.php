<?php namespace ProcessWire\BsProcessEditorial\Setup;

use ProcessWire\BsProcessEditorial;

/**
 * Visueller Menü-Builder für Setup → Redaktion (schreibt editorial_nav JSON).
 */
class NavBuilder {

	protected BsProcessEditorial $module;
	protected NavConfig $navConfig;

	public function __construct(BsProcessEditorial $module) {
		$this->module = $module;
		$this->navConfig = new NavConfig($module);
	}

	/**
	 * @param string[] $templateOptions template => label
	 */
	public function renderMarkup(array $tree, array $templateOptions): string {
		$icons = NavConfig::iconChoices();
		$treeJson = htmlspecialchars(
			json_encode($tree, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]',
			ENT_QUOTES,
			'UTF-8'
		);
		$tplJson = htmlspecialchars(
			json_encode($templateOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
			ENT_QUOTES,
			'UTF-8'
		);
		$iconJson = htmlspecialchars(
			json_encode($icons, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
			ENT_QUOTES,
			'UTF-8'
		);

		$html = '<div class="bpe-navbuilder" id="bpe-navbuilder"';
		$html .= ' data-tree="' . $treeJson . '"';
		$html .= ' data-templates="' . $tplJson . '"';
		$html .= ' data-icons="' . $iconJson . '">';
		$html .= '<p class="description">Rail-Module (Übersicht / Sections), darunter Gruppen und freigegebene Templates. '
			. 'Reihenfolge per ▲▼. JSON unten bleibt synchron für Power-User.</p>';
		$html .= '<div class="bpe-navbuilder__toolbar">';
		$html .= '<button type="button" class="ui-button ui-widget ui-corner-all" data-bpe-add-section>+ Section</button>';
		$html .= '<button type="button" class="ui-button ui-widget ui-corner-all" data-bpe-add-dashboard>+ Übersicht</button>';
		$html .= '</div>';
		$html .= '<div class="bpe-navbuilder__list" data-bpe-nav-list></div>';
		$html .= '</div>';
		$html .= '<style>' . $this->css() . '</style>';
		return $html;
	}

	protected function css(): string {
		return <<<'CSS'
.bpe-navbuilder { margin: 0 0 1rem; }
.bpe-navbuilder__toolbar { display:flex; gap:.5rem; margin:0 0 .75rem; flex-wrap:wrap; }
.bpe-navbuilder__list { display:flex; flex-direction:column; gap:.75rem; }
.bpe-navbuilder__card {
  border:1px solid #d2d6d3; border-radius:6px; background:#fafbfa; padding:.75rem .9rem;
}
.bpe-navbuilder__card--dashboard { background:#f0f5f2; }
.bpe-navbuilder__row { display:flex; flex-wrap:wrap; gap:.5rem; align-items:flex-end; margin-bottom:.5rem; }
.bpe-navbuilder__row label { display:flex; flex-direction:column; gap:.2rem; font-size:12px; color:#555; }
.bpe-navbuilder__row input[type=text],
.bpe-navbuilder__row select { min-width:8rem; }
.bpe-navbuilder__actions { margin-left:auto; display:flex; gap:.25rem; }
.bpe-navbuilder__actions button { font-size:12px; }
.bpe-navbuilder__children { margin:.5rem 0 0 1rem; padding-left:.75rem; border-left:2px solid #dde2df; display:flex; flex-direction:column; gap:.5rem; }
.bpe-navbuilder__group {
  border:1px dashed #cfd5d1; border-radius:5px; padding:.55rem .65rem; background:#fff;
}
.bpe-navbuilder__tpls { display:flex; flex-direction:column; gap:.35rem; margin-top:.4rem; }
.bpe-navbuilder__tpl {
  display:flex; flex-wrap:wrap; gap:.4rem; align-items:center; padding:.35rem .4rem; background:#f7f8f7; border-radius:4px;
}
.bpe-navbuilder__muted { color:#777; font-size:12px; }
CSS;
	}
}
