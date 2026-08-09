<?php namespace ProcessWire\BsProcessEditorial\FormEngine;

use ProcessWire\BsProcessEditorial\FormEngine\Fields\CheckboxField;
use ProcessWire\BsProcessEditorial\FormEngine\Fields\FieldRendererInterface;
use ProcessWire\BsProcessEditorial\FormEngine\Fields\ImageField;
use ProcessWire\BsProcessEditorial\FormEngine\Fields\PageReferenceField;
use ProcessWire\BsProcessEditorial\FormEngine\Fields\SelectField;
use ProcessWire\BsProcessEditorial\FormEngine\Fields\TextareaField;
use ProcessWire\BsProcessEditorial\FormEngine\Fields\TextField;

/**
 * Form-Engine: rendert Formulare ausschließlich aus Schema-Daten.
 * Layout: Inhaltsfelder links, Meta-Panel rechts (Titelbild, Kategorie, Status, …).
 */
class FormRenderer {

	/** Feldtypen standardmäßig im Meta-Panel */
	protected const META_TYPES = ['checkbox', 'select', 'image', 'pageReference'];

	/** @var FieldRendererInterface[] */
	protected array $renderers = [];

	public function __construct(?array $renderers = null) {
		$this->renderers = $renderers ?? [
			new TextField(),
			new TextareaField(),
			new CheckboxField(),
			new SelectField(),
			new ImageField(),
			new PageReferenceField(),
		];
	}

	public function renderForm(array $schema, array $values = [], array $errors = [], array $options = []): string {
		$action = $options['action'] ?? '';
		$title = $options['title'] ?? ($schema['label'] ?? $schema['template']);
		$submitLabel = $options['submitLabel'] ?? 'Speichern';
		$csrf = $options['csrf'] ?? '';
		$breadcrumb = $options['breadcrumb'] ?? null;
		$savedAt = $options['savedAt'] ?? null;

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
			$html .= implode('<span class="bpe-breadcrumb__sep">/</span>', $parts);
			$html .= '</nav>';
		}
		$html .= '<h1 class="bpe-form__title">' . $this->e($title) . '</h1>';
		if ($savedAt) {
			$html .= '<p class="bpe-form__saved" data-saved-at="' . $this->e($savedAt) . '">Zuletzt gespeichert: '
				. $this->e($this->relativeTime($savedAt)) . '</p>';
		}
		$html .= '</div>';
		$html .= '<div class="bpe-form__header-actions">';
		$html .= '<button type="submit" class="bpe-btn bpe-btn--primary">' . $this->e($submitLabel) . '</button>';
		if (!empty($options['cancelUrl'])) {
			$html .= '<a class="bpe-btn bpe-btn--ghost" href="' . $this->e($options['cancelUrl']) . '">Abbrechen</a>';
		}
		$html .= '</div>';
		$html .= '</header>';

		$html .= '<div class="bpe-form__layout">';
		$html .= '<div class="bpe-form__main">';
		foreach ($mainFields as $field) {
			$html .= $this->renderFieldWithValue($field, $values, $errors);
		}
		$html .= '</div>';

		if ($metaFields) {
			$html .= '<aside class="bpe-form__meta" aria-label="Eigenschaften">';
			$html .= '<h2 class="bpe-form__meta-title">Eigenschaften</h2>';
			foreach ($metaFields as $field) {
				$html .= $this->renderFieldWithValue($field, $values, $errors);
			}
			$html .= '</aside>';
		}
		$html .= '</div>';

		$html .= '<footer class="bpe-form__footer">';
		$html .= '<button type="submit" class="bpe-btn bpe-btn--primary">' . $this->e($submitLabel) . '</button>';
		if (!empty($options['cancelUrl'])) {
			$html .= '<a class="bpe-btn bpe-btn--ghost" href="' . $this->e($options['cancelUrl']) . '">Zur Übersicht</a>';
		}
		$html .= '</footer>';
		$html .= '</form>';

		return $html;
	}

	public function renderField(array $field, mixed $value, array $errors = []): string {
		$type = $field['type'] ?? '';
		foreach ($this->renderers as $renderer) {
			if ($renderer->supports($type)) {
				return $renderer->render($field, $value, $errors);
			}
		}
		return '<!-- unsupported field type: ' . $this->e($type) . ' -->';
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
