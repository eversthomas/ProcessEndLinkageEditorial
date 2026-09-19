<?php namespace ProcessWire\ProcessEndLinkageEditorial\Adapter;

use ProcessWire\ProcessEndLinkageEditorial;
use ProcessWire\ProcessEndLinkageEditorial\Adapter\Fields\AbstractFieldAdapter;
use ProcessWire\ProcessEndLinkageEditorial\Adapter\Fields\CheckboxFieldAdapter;
use ProcessWire\ProcessEndLinkageEditorial\Adapter\Fields\DatetimeFieldAdapter;
use ProcessWire\ProcessEndLinkageEditorial\Adapter\Fields\EmailFieldAdapter;
use ProcessWire\ProcessEndLinkageEditorial\Adapter\Fields\FileFieldAdapter;
use ProcessWire\ProcessEndLinkageEditorial\Adapter\Fields\ImageFieldAdapter;
use ProcessWire\ProcessEndLinkageEditorial\Adapter\Fields\NumberFieldAdapter;
use ProcessWire\ProcessEndLinkageEditorial\Adapter\Fields\PageReferenceFieldAdapter;
use ProcessWire\ProcessEndLinkageEditorial\Adapter\Fields\RepeaterFieldAdapter;
use ProcessWire\ProcessEndLinkageEditorial\Adapter\Fields\SelectFieldAdapter;
use ProcessWire\ProcessEndLinkageEditorial\Adapter\Fields\TextareaFieldAdapter;
use ProcessWire\ProcessEndLinkageEditorial\Adapter\Fields\TextFieldAdapter;
use ProcessWire\ProcessEndLinkageEditorial\Adapter\Fields\UrlFieldAdapter;
use ProcessWire\ProcessEndLinkageEditorial\Setup\TemplateDiscovery;
use ProcessWire\Field;
use ProcessWire\Page;
use ProcessWire\Template;
use ProcessWire\WireException;

/**
 * ProcessWire-Adapter: liest/schreibt über die PW-API, liefert nur Schema-Arrays an die UI.
 */
class ProcessWireAdapter implements AdapterInterface {

	/** Sicherheitsdeckel für listRecords() — echte Pagination/Suche ist ein separates Vorhaben. */
	protected const LIST_LIMIT = 500;

	protected ProcessEndLinkageEditorial $module;

	/** @var AbstractFieldAdapter[] */
	protected array $fieldAdapters;

	public function __construct(ProcessEndLinkageEditorial $module, ?array $fieldAdapters = null) {
		$this->module = $module;
		if ($fieldAdapters !== null) {
			$this->fieldAdapters = $fieldAdapters;
			return;
		}
		// Repeater-Items nutzen dieselben Adapter wie die Top-Level-Felder (ohne sich selbst —
		// verschachtelte Repeater sind bewusst nicht unterstützt).
		$itemAdapters = [
			new TextareaFieldAdapter(),
			new EmailFieldAdapter(),
			new UrlFieldAdapter(),
			new TextFieldAdapter(),
			new NumberFieldAdapter(),
			new DatetimeFieldAdapter(),
			new CheckboxFieldAdapter(),
			new SelectFieldAdapter(),
			new ImageFieldAdapter(),
			new FileFieldAdapter(),
			new PageReferenceFieldAdapter(),
		];
		$this->fieldAdapters = array_merge($itemAdapters, [new RepeaterFieldAdapter($itemAdapters)]);
	}

	public function listContentTypes(): array {
		$types = [];
		foreach ($this->module->editorialTemplateNames() as $name) {
			$tpl = $this->module->wire()->templates->get($name);
			if (!$tpl || !$tpl->id) {
				continue;
			}
			$types[] = [
				'name' => $tpl->name,
				'label' => $this->templateLabel($tpl),
				'mode' => $this->module->editorialMode($tpl->name),
			];
		}
		return $types;
	}

	public function supportsTemplate(string $template): bool {
		if (in_array($template, TemplateDiscovery::SKIP, true)) {
			return false;
		}
		$tpl = $this->module->wire()->templates->get($template);
		return (bool) ($tpl && $tpl->id);
	}

	public function readSchema(string $template): array {
		$tpl = $this->requireEditorialTemplate($template);
		$contextPage = $this->contextPage($tpl->name);
		$fields = [];
		foreach ($tpl->fields as $field) {
			$adapter = $this->adapterFor($field);
			if (!$adapter) {
				$fields[] = $this->unsupportedFieldSchema($field);
				continue;
			}
			$fields[] = $adapter->readSchema($field, $contextPage);
		}
		return [
			'template' => $tpl->name,
			'label' => $this->templateLabel($tpl),
			'fields' => $fields,
		];
	}

	/**
	 * Felder ohne Adapter (für Setup-Transparenz; keine Freigabe nötig).
	 *
	 * @return array<int, array{name: string, label: string, type: string}>
	 */
	public function unsupportedFieldsForTemplate(string $template): array {
		$tpl = $this->module->wire()->templates->get($template);
		if (!$tpl || !$tpl->id) {
			return [];
		}
		$out = [];
		foreach ($tpl->fields as $field) {
			if ($this->adapterFor($field)) {
				continue;
			}
			$out[] = [
				'name' => $field->name,
				'label' => (string) $field->getLabel(),
				'type' => $this->fieldTypeLabel($field),
			];
		}
		return $out;
	}

	/**
	 * @return array{name: string, type: string, label: string, pwType: string, required: bool, panel: string}
	 */
	protected function unsupportedFieldSchema(Field $field): array {
		$pwType = $this->fieldTypeLabel($field);
		return [
			'name' => $field->name,
			'type' => 'unsupported',
			'label' => (string) $field->getLabel(),
			'pwType' => $pwType,
			'required' => false,
			'panel' => 'main',
		];
	}

	protected function fieldTypeLabel(Field $field): string {
		$type = $field->type;
		if ($type) {
			$info = $type->getModuleInfo();
			$title = trim((string) ($info['title'] ?? ''));
			if ($title !== '') {
				return $title;
			}
			$class = $type->className();
			if (str_starts_with($class, 'Fieldtype')) {
				return substr($class, 9);
			}
			return $class;
		}
		return 'unbekannt';
	}

	public function listRecords(string $template): array {
		$this->requireEditorialTemplate($template);
		$pages = $this->module->wire()->pages->find(
			"template={$template}, include=all, sort=-modified, limit=" . self::LIST_LIMIT
		);
		$records = [];
		foreach ($pages as $page) {
			$records[] = $this->pageToRecord($page);
		}
		return $records;
	}

	public function countRecords(string $template, ?string $statusFilter = null): int {
		$this->requireEditorialTemplate($template);
		$selector = "template={$template}, include=all";
		if ($statusFilter === 'unpublished') {
			$selector .= ', status>=' . \ProcessWire\Page::statusUnpublished;
		} elseif ($statusFilter === 'published') {
			$selector .= ', status<' . \ProcessWire\Page::statusUnpublished;
		}
		return (int) $this->module->wire()->pages->count($selector);
	}

	public function getRecord(string $template, string $id): ?array {
		$this->requireEditorialTemplate($template);
		$page = $this->module->wire()->pages->get((int) $id);
		if (!$page->id || $page->template->name !== $template) {
			return null;
		}
		return $this->pageToRecord($page);
	}

	public function saveRecord(string $template, array $data, string $action = 'edit'): array {
		$tpl = $this->requireEditorialTemplate($template);
		$pages = $this->module->wire()->pages;
		$sanitizer = $this->module->wire()->sanitizer;
		$id = isset($data['id']) ? (int) $data['id'] : 0;
		$isNewPage = !$id;

		if ($id) {
			$page = $pages->get($id);
			if (!$page->id || $page->template->name !== $template) {
				return ['record' => null, 'errors' => ['_form' => 'Eintrag nicht gefunden.']];
			}
		} else {
			$parent = $this->resolveParent($template);
			if (!$parent || !$parent->id) {
				return ['record' => null, 'errors' => [
					'_form' => "Keine Elternseite für „{$template}“ gefunden. Lege z. B. /{$template}/ an oder pflege bestehende Einträge.",
				]];
			}
			$page = new Page();
			$page->template = $tpl;
			$page->parent = $parent;
		}

		$page->of(false);

		$titleField = $tpl->fields->get('title');
		$titleRaw = $data['title'] ?? '';
		$titleCheck = $titleField && $this->adapterFor($titleField)
			? $this->adapterFor($titleField)->sanitizeAndValidate($titleField, $page, $titleRaw)
			: ['value' => $sanitizer->text((string) $titleRaw), 'errors' => []];

		if (!empty($titleCheck['errors'])) {
			return ['record' => null, 'errors' => ['title' => $titleCheck['errors'][0]]];
		}

		if (!$page->id) {
			$page->title = $titleCheck['value'] !== '' ? $titleCheck['value'] : 'Ohne Titel';
			$page->name = $sanitizer->pageName($page->title, true) ?: 'eintrag';
			if ($page->parent->child('name=' . $sanitizer->selectorValue($page->name))->id) {
				$page->name .= '-' . time();
			}
			$page->save();
			$page->of(false);
		}

		$validated = [];
		$errors = [];
		$rollback = [];

		if ($isNewPage) {
			$rollback[] = ['type' => 'page', 'id' => $page->id];
		}

		foreach ($tpl->fields as $field) {
			$adapter = $this->adapterFor($field);
			if (!$adapter) {
				continue;
			}
			$raw = $this->rawForField($field, $data);
			$result = $adapter->sanitizeAndValidate($field, $page, $raw);
			if (!empty($result['rollback'])) {
				array_push($rollback, ...$result['rollback']);
			}
			if ($result['errors']) {
				$errors[$field->name] = $result['errors'][0];
			} else {
				$validated[$field->name] = ['adapter' => $adapter, 'value' => $result['value'], 'field' => $field];
			}
		}

		if ($errors) {
			$this->rollback($rollback);
			return ['record' => null, 'errors' => $errors];
		}

		foreach ($validated as $item) {
			$item['adapter']->writeValue($item['field'], $page, $item['value']);
		}

		$status = (string) ($data['status'] ?? '');
		if ($status === 'unpublished') {
			$page->addStatus(Page::statusUnpublished);
		} elseif ($status === 'published') {
			$page->removeStatus(Page::statusUnpublished);
		}

		$page->save();

		$user = $this->module->wire()->user;
		$this->module->wire()->log->save('bpe-audit', sprintf(
			'%s template=%s id=%s user=%s status=%s',
			$action,
			$template,
			$page->id,
			($user && $user->id) ? $user->name : 'guest',
			$status !== '' ? $status : 'published'
		));

		return ['record' => $this->pageToRecord($page), 'errors' => []];
	}

	/**
	 * Räumt spekulativ geschriebene Seiten/Repeater-Items/Dateien auf, wenn eine Gesamt-
	 * validierung scheitert. Löscht nur, was in genau diesem Request neu entstanden ist —
	 * niemals einen vorher schon existierenden Datensatz.
	 *
	 * @param array<int, array{type: string, id?: int, path?: string}> $entries
	 */
	protected function rollback(array $entries): void {
		$pages = $this->module->wire()->pages;
		foreach ($entries as $entry) {
			$type = $entry['type'] ?? '';
			if ($type === 'page' && !empty($entry['id'])) {
				$p = $pages->get((int) $entry['id']);
				if ($p->id) {
					$p->of(false);
					$pages->delete($p, true);
				}
			} elseif ($type === 'file' && !empty($entry['path']) && is_file($entry['path'])) {
				@unlink($entry['path']);
			}
		}
	}

	/**
	 * Elternseite für neue Einträge:
	 * 1) Parent bestehender Seiten dieses Templates
	 * 2) Seite /$template/
	 * 3) Kind von Home mit name=$template
	 */
	public function resolveParent(string $template): ?Page {
		$pages = $this->module->wire()->pages;
		$existing = $pages->get("template={$template}, include=all");
		if ($existing->id && $existing->parent->id) {
			return $existing->parent;
		}

		$byPath = $pages->get('/' . trim($template, '/') . '/');
		if ($byPath->id) {
			return $byPath;
		}

		$home = $pages->get(1);
		$child = $home->child('name=' . $this->module->wire()->sanitizer->selectorValue($template) . ', include=all');
		if ($child->id) {
			return $child;
		}

		return null;
	}

	protected function pageToRecord(Page $page): array {
		$page->of(false);
		$modifiedUser = $page->modifiedUser;
		$record = [
			'id' => (string) $page->id,
			'created' => date('c', $page->created),
			'modified' => date('c', $page->modified),
			'modifiedBy' => ($modifiedUser && $modifiedUser->id) ? $modifiedUser->name : null,
			'status' => $page->isUnpublished() ? 'unpublished' : 'published',
			'url' => $page->id ? (string) $page->url : null,
		];
		foreach ($page->template->fields as $field) {
			$adapter = $this->adapterFor($field);
			if (!$adapter) {
				continue;
			}
			$record[$field->name] = $adapter->readValue($field, $page);
		}
		return $record;
	}

	protected function rawForField(Field $field, array $data): mixed {
		$name = $field->name;
		$adapter = $this->adapterFor($field);
		if ($adapter instanceof ImageFieldAdapter || $adapter instanceof FileFieldAdapter) {
			return [
				'clear' => !empty($data[$name . '_clear']),
				'keep' => $data[$name . '_existing'] ?? (is_array($data[$name] ?? null) ? ($data[$name]['name'] ?? null) : ($data[$name] ?? null)),
				'remove' => (array) ($data[$name . '_remove'] ?? []),
				'upload' => !empty($_FILES[$name]['name']),
			];
		}
		return $data[$name] ?? null;
	}

	protected function adapterFor(Field $field): ?AbstractFieldAdapter {
		foreach ($this->fieldAdapters as $adapter) {
			if ($adapter->supports($field)) {
				return $adapter;
			}
		}
		return null;
	}

	protected function requireEditorialTemplate(string $template) {
		if (in_array($template, TemplateDiscovery::SKIP, true)) {
			throw new WireException("Inhaltstyp „{$template}“ ist ein System-Template und für die Redaktion gesperrt.");
		}
		$allowed = $this->module->editorialTemplateNames();
		if (!in_array($template, $allowed, true)) {
			throw new WireException("Inhaltstyp „{$template}“ ist nicht für die Redaktion freigegeben.");
		}
		return $this->requireTemplate($template);
	}

	protected function requireTemplate(string $template) {
		$tpl = $this->module->wire()->templates->get($template);
		if (!$tpl || !$tpl->id) {
			throw new WireException("Template „{$template}“ nicht gefunden.");
		}
		return $tpl;
	}

	protected function templateLabel(Template $tpl): string {
		$label = trim((string) $tpl->get('label'));
		return $label !== '' ? $label : ucfirst($tpl->name);
	}

	protected function contextPage(string $templateName): Page {
		$pages = $this->module->wire()->pages;
		$existing = $pages->get("template={$templateName}, include=all");
		if ($existing->id) {
			return $existing;
		}
		$parent = $this->resolveParent($templateName);
		$page = new Page();
		$page->template = $templateName;
		$page->parent = $parent && $parent->id ? $parent : $pages->get(1);
		return $page;
	}
}
