<?php namespace ProcessWire\ProcessEndLinkageEditorial\FormEngine;

use ProcessWire\ProcessEndLinkageEditorial\FormEngine\Fields\CheckboxField;
use ProcessWire\ProcessEndLinkageEditorial\FormEngine\Fields\DatetimeField;
use ProcessWire\ProcessEndLinkageEditorial\FormEngine\Fields\FieldRendererInterface;
use ProcessWire\ProcessEndLinkageEditorial\FormEngine\Fields\FileField;
use ProcessWire\ProcessEndLinkageEditorial\FormEngine\Fields\ImageField;
use ProcessWire\ProcessEndLinkageEditorial\FormEngine\Fields\PageReferenceField;
use ProcessWire\ProcessEndLinkageEditorial\FormEngine\Fields\RepeaterField;
use ProcessWire\ProcessEndLinkageEditorial\FormEngine\Fields\SelectField;
use ProcessWire\ProcessEndLinkageEditorial\FormEngine\Fields\TextareaField;
use ProcessWire\ProcessEndLinkageEditorial\FormEngine\Fields\TextField;
use ProcessWire\ProcessEndLinkageEditorial\FormEngine\Fields\UnsupportedField;
use ProcessWire\ProcessEndLinkageEditorial\Ui\Icons;

/**
 * Form-Engine: Schema → Formular. Hauptfläche + Details-Sidebar inkl. Publish.
 */
class FormRenderer {

	/** Feldtypen standardmäßig im Details-Panel */
	protected const META_TYPES = ['checkbox', 'select', 'image', 'file', 'pageReference'];

	/** @var FieldRendererInterface[] */
	protected array $renderers = [];

	public function __construct(?array $renderers = null) {
		if ($renderers !== null) {
			$this->renderers = $renderers;
			return;
		}
		// Repeater-Items nutzen dieselben Renderer wie die Top-Level-Felder (ohne sich selbst —
		// verschachtelte Repeater sind bewusst nicht unterstützt).
		$itemRenderers = [
			new TextField(),
			new TextareaField(),
			new DatetimeField(),
			new CheckboxField(),
			new SelectField(),
			new ImageField(),
			new FileField(),
			new PageReferenceField(),
			new UnsupportedField(),
		];
		$this->renderers = array_merge($itemRenderers, [new RepeaterField($itemRenderers)]);
	}

	public function renderForm(array $schema, array $values = [], array $errors = [], array $options = []): string {
		$skin = ($options['skin'] ?? 'legacy') === 'daten' ? 'daten' : 'legacy';
		if ($skin === 'daten') {
			return $this->renderFormDaten($schema, $values, $errors, $options);
		}
		return $this->renderFormLegacy($schema, $values, $errors, $options);
	}

	protected function renderFormDaten(array $schema, array $values, array $errors, array $options): string {
		$action = $options['action'] ?? '';
		$title = $options['title'] ?? ($schema['label'] ?? $schema['template']);
		$submitLabel = $options['submitLabel'] ?? 'Speichern';
		$csrf = $options['csrf'] ?? '';
		$savedAt = $options['savedAt'] ?? null;
		$previewUrl = $options['previewUrl'] ?? null;
		$cancelUrl = $options['cancelUrl'] ?? null;
		$listLabel = $options['listLabel'] ?? ($schema['label'] ?? $schema['template']);
		$status = $values['status'] ?? ($options['status'] ?? 'published');
		$showPublish = array_key_exists('showPublish', $options) ? (bool) $options['showPublish'] : true;

		[$mainFields, $metaFields] = $this->splitFields($schema['fields'] ?? [], $options);
		$titleField = null;
		$mainRest = [];
		foreach ($mainFields as $field) {
			if (($field['name'] ?? '') === 'title' && $titleField === null) {
				$titleField = $field;
			} else {
				$mainRest[] = $field;
			}
		}

		$html = '<form class="bpe-daten bpe-daten--form bpe-form-daten" method="post" action="'
			. $this->e($action) . '" enctype="multipart/form-data" novalidate>';
		if ($csrf !== '') {
			$html .= $csrf;
		}
		if (!empty($values['id'])) {
			$html .= '<input type="hidden" name="id" value="' . $this->e($values['id']) . '">';
		}

		if ($cancelUrl) {
			$html .= '<a class="btn btn-ghost bpe-form-daten__back" href="' . $this->e($cancelUrl) . '">'
				. Icons::svg('chevron-left', 'bpe-icon bpe-icon--sm')
				. ' Zurück zu ' . $this->e($listLabel) . '</a>';
		}

		$html .= '<header class="bpe-daten__header">';
		$html .= '<h1>' . $this->e($title) . '</h1>';
		$html .= '<div class="bpe-daten__actions">';
		if ($previewUrl) {
			$html .= '<a class="btn btn-secondary" href="' . $this->e($previewUrl) . '" target="_blank" rel="noopener">Vorschau</a>';
		}
		$html .= '<button type="submit" name="bpe_action" value="save" class="btn btn-primary">'
			. $this->e($submitLabel) . '</button>';
		$html .= '</div></header>';

		$html .= '<div class="bpe-form-daten__layout">';
		$html .= '<div class="bpe-form-daten__main">';

		if ($titleField) {
			$html .= '<div class="bpe-form-daten__title-block">';
			$name = $titleField['name'];
			$id = 'field-' . preg_replace('/[^a-z0-9_-]/i', '', $name);
			$val = array_key_exists($name, $values) ? $values[$name] : '';
			$html .= '<div class="field bpe-field" data-field="' . $this->e($name) . '">';
			$html .= '<label class="bpe-field__label" for="' . $this->e($id) . '">'
				. $this->e($titleField['label'] ?? 'Titel') . '</label>';
			$html .= '<input type="text" class="input bpe-input" id="' . $this->e($id) . '" name="'
				. $this->e($name) . '" value="' . $this->e($val) . '"'
				. (!empty($titleField['required']) ? ' required' : '') . '>';
			if (!empty($errors[$name])) {
				$html .= '<p class="bpe-field__error" role="alert">' . $this->e($errors[$name]) . '</p>';
			}
			$html .= '</div>';
			if (!empty($values['url'])) {
				$html .= '<p class="bpe-form-daten__permalink">' . $this->e($values['url']) . '</p>';
			}
			$html .= '</div>';
		}

		foreach ($mainRest as $field) {
			$html .= $this->renderFieldWithValue($field, $values, $errors);
		}
		if (!$titleField && !$mainRest) {
			$html .= '<p class="text-muted">Keine Inhaltsfelder in diesem Template.</p>';
		}
		$html .= '</div>';

		$html .= '<aside class="bpe-form-daten__meta" aria-label="Details">';
		if ($metaFields) {
			$html .= '<div>';
			$html .= '<h3 class="bpe-form-daten__meta-title">Details</h3>';
			foreach ($metaFields as $field) {
				$html .= $this->renderFieldWithValue($field, $values, $errors);
			}
			$html .= '</div>';
		}
		if ($showPublish) {
			$html .= $this->renderPublishBlockDaten($status);
		}
		if ($savedAt) {
			$html .= '<p class="bpe-form-daten__saved">Zuletzt gespeichert: '
				. $this->e($this->relativeTime($savedAt)) . '</p>';
		}
		$html .= '</aside>';
		$html .= '</div></form>';
		return $html;
	}

	protected function renderFormLegacy(array $schema, array $values, array $errors, array $options): string {
		$action = $options['action'] ?? '';
		$title = $options['title'] ?? ($schema['label'] ?? $schema['template']);
		$submitLabel = $options['submitLabel'] ?? 'Speichern';
		$csrf = $options['csrf'] ?? '';
		$breadcrumb = $options['breadcrumb'] ?? null;
		$savedAt = $options['savedAt'] ?? null;
		$previewUrl = $options['previewUrl'] ?? null;
		$status = $values['status'] ?? ($options['status'] ?? 'published');
		$showPublish = array_key_exists('showPublish', $options) ? (bool) $options['showPublish'] : true;

		[$mainFields, $metaFields] = $this->splitFields($schema['fields'] ?? [], $options);

		$html = '<form class="bpe-form" method="post" action="' . $this->e($action) . '" enctype="multipart/form-data" novalidate>';
		if ($csrf !== '') {
			$html .= $csrf;
		}
		if (!empty($values['id'])) {
			$html .= '<input type="hidden" name="id" value="' . $this->e($values['id']) . '">';
		}

		$html .= '<header class="bpe-form__header">';
		$html .= '<div class="bpe-form__heading">';
		if (is_array($breadcrumb) && $breadcrumb) {
			$html .= '<nav class="bpe-breadcrumb" aria-label="Brotkrumen">';
			$parts = [];
			foreach ($breadcrumb as $item) {
				$label = $this->e($item['label'] ?? '');
				if (!empty($item['url'])) {
					$parts[] = '<a href="' . $this->e($item['url']) . '">' . $label . '</a>';
				} else {
					$parts[] = '<span aria-current="page">' . $label . '</span>';
				}
			}
			$html .= implode('<span class="bpe-breadcrumb__sep">›</span>', $parts);
			$html .= '</nav>';
		}
		$html .= '<h1 class="bpe-form__title">' . $this->e($title) . '</h1>';
		if ($savedAt) {
			$html .= '<p class="bpe-form__saved" data-saved-at="' . $this->e($savedAt) . '">Zuletzt gespeichert: '
				. $this->e($this->relativeTime($savedAt)) . '</p>';
		}
		$html .= '</div>';
		$html .= '<div class="bpe-form__header-actions">';
		if ($previewUrl) {
			$html .= '<a class="bpe-btn bpe-btn--ghost" href="' . $this->e($previewUrl) . '" target="_blank" rel="noopener">'
				. Icons::svg('eye', 'bpe-icon bpe-icon--sm') . ' Vorschau</a>';
		}
		$html .= '<button type="submit" name="bpe_action" value="save" class="bpe-btn bpe-btn--primary">'
			. Icons::svg('save', 'bpe-icon bpe-icon--sm') . ' ' . $this->e($submitLabel) . '</button>';
		$html .= '</div>';
		$html .= '</header>';

		$html .= '<div class="bpe-form__layout">';
		$html .= '<div class="bpe-form__main">';
		foreach ($mainFields as $field) {
			$html .= $this->renderFieldWithValue($field, $values, $errors);
		}
		if (!$mainFields) {
			$html .= '<p class="bpe-muted">Keine Inhaltsfelder in diesem Template.</p>';
		}
		$html .= '</div>';

		$html .= '<aside class="bpe-form__meta" aria-label="Details">';
		$html .= '<div class="bpe-form__meta-head">';
		$html .= '<h2 class="bpe-form__meta-title">Details</h2>';
		$html .= '</div>';
		foreach ($metaFields as $field) {
			$html .= $this->renderFieldWithValue($field, $values, $errors);
		}
		if ($showPublish) {
			$html .= $this->renderPublishBlock($status);
		}
		$html .= '</aside>';
		$html .= '</div>';
		$html .= '</form>';

		return $html;
	}

	protected function renderPublishBlockDaten(string $status): string {
		$published = $status === 'published';
		$html = '<div>';
		$html .= '<h3 class="bpe-form-daten__meta-title">Status</h3>';
		$html .= '<div style="display:grid;gap:8px">';
		$html .= '<label class="radio"><input type="radio" name="status" value="published"'
			. ($published ? ' checked' : '') . ' /><span class="dot"></span>Veröffentlicht</label>';
		$html .= '<label class="radio"><input type="radio" name="status" value="unpublished"'
			. (!$published ? ' checked' : '') . ' /><span class="dot"></span>Entwurf</label>';
		$html .= '</div>';
		$html .= '<div style="margin-top:14px;display:grid;gap:8px">';
		if ($published) {
			$html .= '<button type="submit" name="bpe_action" value="unpublish" class="btn btn-ghost">Zurück auf Entwurf</button>';
		} else {
			$html .= '<button type="submit" name="bpe_action" value="publish" class="btn btn-primary">Veröffentlichen</button>';
		}
		$html .= '</div>';
		$html .= '<p class="bpe-form-daten__saved" style="margin-top:12px">Zeitplan festlegen (demnächst)</p>';
		$html .= '</div>';
		return $html;
	}

	protected function renderPublishBlock(string $status): string {
		$published = $status === 'published';
		$html = '<div class="bpe-publish">';
		$html .= '<p class="bpe-publish__label">Veröffentlichungsstatus</p>';
		$html .= '<p class="bpe-publish__status' . ($published ? ' is-live' : ' is-draft') . '">'
			. ($published ? 'Veröffentlicht' : 'Entwurf') . '</p>';
		$html .= '<input type="hidden" name="status" id="bpe-status" value="' . $this->e($status) . '">';
		if ($published) {
			$html .= '<button type="submit" name="bpe_action" value="unpublish" class="bpe-btn bpe-btn--ghost bpe-btn--block">Zurück auf Entwurf</button>';
		} else {
			$html .= '<button type="submit" name="bpe_action" value="publish" class="bpe-btn bpe-btn--publish bpe-btn--block">Veröffentlichen</button>';
		}
		$html .= '<p class="bpe-publish__schedule"><span class="bpe-muted">Zeitplan festlegen (demnächst)</span></p>';
		$html .= '</div>';
		return $html;
	}

	public function renderField(array $field, mixed $value, array $errors = []): string {
		$type = $field['type'] ?? '';
		foreach ($this->renderers as $renderer) {
			if ($renderer->supports($type)) {
				return $renderer->render($field, $value, $errors);
			}
		}
		$fallback = $field;
		$fallback['type'] = 'unsupported';
		if (empty($fallback['pwType'])) {
			$fallback['pwType'] = $type !== '' ? $type : 'unbekannt';
		}
		return (new UnsupportedField())->render($fallback, $value, $errors);
	}

	protected function renderFieldWithValue(array $field, array $values, array $errors): string {
		$type = $field['type'] ?? '';
		$name = $field['name'] ?? '';
		$value = array_key_exists($name, $values) ? $values[$name] : null;
		if ($value === null && $type === 'checkbox' && array_key_exists('default', $field)) {
			$value = $field['default'];
		}
		return $this->renderField($field, $value, $errors);
	}

	/**
	 * @return array{0: array, 1: array}
	 */
	protected function splitFields(array $fields, array $options): array {
		$metaNames = $options['metaFields'] ?? null;
		$main = [];
		$meta = [];
		foreach ($fields as $field) {
			$type = $field['type'] ?? '';
			$name = $field['name'] ?? '';
			$panel = $field['panel'] ?? null;
			if ($panel === 'main') {
				$main[] = $field;
				continue;
			}
			if ($panel === 'meta' || $panel === 'sidebar') {
				$meta[] = $field;
				continue;
			}
			$isMeta = is_array($metaNames)
				? in_array($name, $metaNames, true)
				: in_array($type, self::META_TYPES, true);
			if ($isMeta) {
				$meta[] = $field;
			} else {
				$main[] = $field;
			}
		}
		return [$main, $meta];
	}

	protected function relativeTime(string $iso): string {
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
			return 'vor ' . $m . ' Minute' . ($m === 1 ? '' : 'n');
		}
		if ($diff < 86400) {
			$h = (int) floor($diff / 3600);
			return 'vor ' . $h . ' Stunde' . ($h === 1 ? '' : 'n');
		}
		return date('d.m.Y H:i', $ts) . ' Uhr';
	}

	protected function e(mixed $value): string {
		return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
	}
}
