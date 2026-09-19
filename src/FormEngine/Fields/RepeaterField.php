<?php namespace ProcessWire\ProcessEndLinkageEditorial\FormEngine\Fields;

/**
 * Repeater: rendert jedes Item über die injizierten Item-Feld-Renderer.
 * Neue Items entstehen client-seitig durch Klonen von <template data-bpe-repeater-template>
 * (Platzhalter __INDEX__ in name/id, siehe assets/js/editorial.js) — kein Server-Roundtrip
 * beim Hinzufügen/Entfernen. Kein Reorder (siehe Plan „Repeater-Unterstützung").
 */
class RepeaterField extends AbstractFieldRenderer {

	/** @var FieldRendererInterface[] */
	protected array $itemRenderers;

	/** @param FieldRendererInterface[] $itemRenderers Renderer für die Felder innerhalb eines Items */
	public function __construct(array $itemRenderers) {
		$this->itemRenderers = $itemRenderers;
	}

	public function supports(string $type): bool {
		return $type === 'repeater';
	}

	public function render(array $field, mixed $value, array $errors = []): string {
		$name = $field['name'];
		$itemFields = $field['itemFields'] ?? [];
		$rows = is_array($value) ? array_values($value) : [];

		$html = $this->wrapperStart($field, $errors);
		$html .= '<div class="bpe-repeater" data-bpe-repeater>';
		$html .= '<div class="bpe-repeater__items" data-bpe-repeater-items>';
		foreach ($rows as $idx => $row) {
			// Bestehende Items starten zugeklappt (Accordion) — neue (Klon-Vorlage) starten offen,
			// damit man direkt mit dem Ausfüllen loslegen kann.
			$html .= $this->renderItem($name, $itemFields, (string) $idx, is_array($row) ? $row : [], false);
		}
		$html .= '</div>';

		if (!$itemFields) {
			$html .= '<p class="bpe-muted">Dieses Repeater-Feld enthält keine unterstützten Felder.</p>';
		} else {
			$html .= '<template data-bpe-repeater-template>'
				. $this->renderItem($name, $itemFields, '__INDEX__', [], true)
				. '</template>';
			$html .= '<button type="button" class="bpe-btn bpe-btn--ghost bpe-repeater__add" data-bpe-repeater-add>+ Element hinzufügen</button>';
		}

		$html .= '</div>';
		$html .= $this->wrapperEnd($field, $errors);
		return $html;
	}

	/**
	 * @param array $itemFields Item-Schema (Feldliste)
	 * @param array $row Werte dieses Items (leer für die Klon-Vorlage)
	 */
	protected function renderItem(string $repeaterName, array $itemFields, string $index, array $row, bool $expanded): string {
		$itemId = (string) ($row['_itemId'] ?? '');
		$summary = $this->itemSummary($itemFields, $row, $index);
		$expandedAttr = $expanded ? 'true' : 'false';

		$html = '<div class="bpe-repeater__item" data-bpe-repeater-item>';
		$html .= '<div class="bpe-repeater__item-head">';
		$html .= '<button type="button" class="bpe-repeater__toggle" data-bpe-repeater-toggle aria-expanded="' . $expandedAttr . '">';
		$html .= '<span class="bpe-repeater__item-title">' . $this->escape($summary) . '</span>';
		$html .= '<span class="bpe-repeater__chevron" aria-hidden="true"></span>';
		$html .= '</button>';
		$html .= '<button type="button" class="bpe-repeater__remove" data-bpe-repeater-remove aria-label="Element entfernen">&times;</button>';
		$html .= '</div>';
		$html .= '<input type="hidden" name="' . $this->escape($repeaterName) . '[' . $this->escape($index) . '][_itemId]" value="'
			. $this->escape($itemId) . '">';
		$html .= '<div class="bpe-repeater__item-fields" data-bpe-repeater-fields' . ($expanded ? '' : ' hidden') . '>';
		foreach ($itemFields as $subField) {
			$subSchema = $subField;
			$subName = (string) ($subField['name'] ?? '');
			$subSchema['name'] = $repeaterName . '[' . $index . '][' . $subName . ']';
			$subValue = $row[$subName] ?? null;
			$html .= $this->renderSubField($subSchema, $subValue);
		}
		$html .= '</div>';
		$html .= '</div>';
		return $html;
	}

	/**
	 * Kurzer Anzeigetext für den zugeklappten Zustand: erster nicht-leere Text-/HTML-Feldwert, sonst "Element N".
	 */
	protected function itemSummary(array $itemFields, array $row, string $index): string {
		if ($index === '__INDEX__') {
			// Klon-Vorlage: echte Position ist erst beim Hinzufügen im Browser bekannt (siehe editorial.js).
			return 'Element __LABEL__';
		}
		foreach ($itemFields as $subField) {
			$type = $subField['type'] ?? '';
			if (!in_array($type, ['text', 'textarea', 'html'], true)) {
				continue;
			}
			$raw = trim((string) strip_tags((string) ($row[$subField['name'] ?? ''] ?? '')));
			if ($raw !== '') {
				return mb_strlen($raw) > 60 ? (mb_substr($raw, 0, 57) . '…') : $raw;
			}
		}
		return 'Element ' . ((int) $index + 1);
	}

	protected function renderSubField(array $field, mixed $value): string {
		$type = $field['type'] ?? '';
		foreach ($this->itemRenderers as $renderer) {
			if ($renderer->supports($type)) {
				return $renderer->render($field, $value, []);
			}
		}
		$fallback = $field;
		$fallback['type'] = 'unsupported';
		if (empty($fallback['pwType'])) {
			$fallback['pwType'] = $type !== '' ? $type : 'unbekannt';
		}
		return (new UnsupportedField())->render($fallback, $value, []);
	}
}
