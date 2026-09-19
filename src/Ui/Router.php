<?php namespace ProcessWire\BsProcessEditorial\Ui;

use ProcessWire\BsProcessEditorial;
use ProcessWire\BsProcessEditorial\Adapter\AdapterInterface;
use ProcessWire\BsProcessEditorial\Auth\EditorialAuth;
use ProcessWire\BsProcessEditorial\Auth\TemplateAccess;
use ProcessWire\BsProcessEditorial\FormEngine\FormRenderer;
use ProcessWire\BsProcessEditorial\Setup\NavConfig;
use ProcessWire\HookEvent;

/**
 * Routing: Dashboard / Nav / Templates unter /t/{template}/…
 */
class Router {

	protected BsProcessEditorial $module;
	protected EditorialAuth $auth;
	protected AdapterInterface $adapter;
	protected NavConfig $navConfig;

	/** @var string[] */
	protected array $allowedTemplates = [];

	/** @var array<int, array{name: string, label: string, mode?: string}> */
	protected array $contentTypes = [];

	/** @var array<int, array<string, mixed>> */
	protected array $navTree = [];

	public function __construct(BsProcessEditorial $module) {
		$this->module = $module;
		$this->auth = new EditorialAuth($module);
		$this->adapter = $module->adapter();
		$this->navConfig = new NavConfig($module);
		$this->refreshAccess();
	}

	public function dispatch(HookEvent $event): string {
		$segments = $this->segments($event);
		$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

		if (($segments[0] ?? '') === 'login') {
			if ($this->auth->isLoggedIn()) {
				return $this->redirect($this->url());
			}
			return $method === 'POST' ? $this->postLogin() : $this->getLogin();
		}
		if (($segments[0] ?? '') === 'logout') {
			$this->auth->logout();
			return $this->redirect($this->url('login'));
		}

		if (!$this->auth->isLoggedIn()) {
			return $this->redirect($this->url('login'));
		}

		$this->bindEditorialUser();
		$this->refreshAccess();

		if (($segments[0] ?? '') === 'nav') {
			return $this->dispatchNav(array_slice($segments, 1));
		}

		if (($segments[0] ?? '') === 't') {
			return $this->dispatchTemplate(array_slice($segments, 1), $method);
		}

		// Legacy: /editorial/{template}/… → /editorial/t/{template}/…
		if ($segments !== [] && $this->isAllowedTemplate($segments[0])) {
			$path = 't/' . implode('/', $segments);
			return $this->redirect($this->url($path));
		}

		if ($segments === [] || $segments === ['']) {
			return $this->getDashboard();
		}

		return $this->renderError(404, 'Seite nicht gefunden.');
	}

	protected function dispatchNav(array $segments): string {
		$sectionId = $segments[0] ?? '';
		$groupId = $segments[1] ?? null;
		if ($sectionId === '') {
			return $this->redirect($this->url());
		}

		$section = $this->navConfig->findNode($sectionId, $this->navTree);
		if (!$section || ($section['type'] ?? '') !== 'section') {
			return $this->renderError(404, 'Menüpunkt nicht gefunden.');
		}

		if ($groupId) {
			$group = $this->navConfig->findNode($groupId, $section['children'] ?? []);
			if (!$group || ($group['type'] ?? '') !== 'group') {
				return $this->renderError(404, 'Gruppe nicht gefunden.');
			}
			return $this->getNodeDashboard($group, $sectionId, $groupId);
		}

		return $this->getNodeDashboard($section, $sectionId, null);
	}

	protected function dispatchTemplate(array $segments, string $method): string {
		$template = $segments[0] ?? '';
		$action = $segments[1] ?? null;

		if (!$this->isAllowedTemplate($template) || !$this->adapter->supportsTemplate($template)) {
			return $this->renderError(
				404,
				'Dieser Inhaltstyp ist nicht verfügbar. Unter Setup → Redaktion nur Templates freigeben, die in ProcessWire existieren.'
			);
		}

		try {
			$isSingle = $this->module->editorialMode($template) === 'single';

			if ($isSingle) {
				if ($action === 'new') {
					return $this->redirect($this->url('t/' . $template));
				}
				if ($action === null || $action === '' || ctype_digit((string) $action)) {
					$id = ($action !== null && $action !== '' && ctype_digit((string) $action))
						? (string) $action
						: $this->resolveSingletonId($template);
					if ($id === null) {
						return $this->renderError(404, 'Keine Seite für diesen Inhaltstyp gefunden.');
					}
					if ($action !== null && $action !== '' && ctype_digit((string) $action) && $method === 'GET') {
						return $this->redirect($this->url('t/' . $template));
					}
					if ($method === 'POST' && !in_array('edit', $this->allowedActionsFor($template), true)) {
						return $this->renderError(403, 'Für diesen Inhaltstyp dürfen Sie keine Einträge bearbeiten.');
					}
					return $method === 'POST'
						? $this->postForm($template, $id, true)
						: $this->getForm($template, $id, [], [], true);
				}
				return $this->renderError(404, 'Seite nicht gefunden.');
			}

			if ($action === null || $action === '') {
				return $this->getList($template);
			}
			if ($action === 'new') {
				if (!in_array('create', $this->allowedActionsFor($template), true)) {
					return $this->renderError(403, 'Für diesen Inhaltstyp dürfen Sie keine neuen Einträge anlegen.');
				}
				return $method === 'POST' ? $this->postForm($template, null) : $this->getForm($template, null);
			}
			if (ctype_digit($action)) {
				if ($method === 'POST' && !in_array('edit', $this->allowedActionsFor($template), true)) {
					return $this->renderError(403, 'Für diesen Inhaltstyp dürfen Sie keine Einträge bearbeiten.');
				}
				return $method === 'POST' ? $this->postForm($template, $action) : $this->getForm($template, $action);
			}

			return $this->renderError(404, 'Seite nicht gefunden.');
		} catch (\InvalidArgumentException $e) {
			$this->wire()->log->save('bpe-editorial', $e->getMessage());
			return $this->renderError(
				404,
				'Dieser Inhaltstyp konnte nicht geladen werden. Bitte Freigabe und Datenquelle unter Setup → Redaktion prüfen.'
			);
		} catch (\ProcessWire\WireException $e) {
			$this->wire()->log->save('bpe-editorial', $e->getMessage());
			return $this->renderError(
				404,
				'Dieser Inhaltstyp konnte nicht geladen werden. Bitte Freigabe und Datenquelle unter Setup → Redaktion prüfen.'
			);
		}
	}

	protected function refreshAccess(): void {
		$access = new TemplateAccess($this->module);
		// currentUser() liefert für Demo-Sessions bereits null — allowedTemplates(null) deckt
		// den Demo-Fall (alle freigegebenen Templates) mit ab, eine einzige Entscheidungsstelle.
		$user = $this->auth->currentUser();
		$candidates = $access->allowedTemplates($user);

		// Nur Templates, die die aktive Datenquelle wirklich bedienen kann
		$this->allowedTemplates = array_values(array_filter(
			$candidates,
			fn(string $name) => $name !== '' && $this->adapter->supportsTemplate($name)
		));

		$types = [];
		foreach ($this->adapter->listContentTypes() as $type) {
			$name = (string) ($type['name'] ?? '');
			if ($name === '' || !in_array($name, $this->allowedTemplates, true)) {
				continue;
			}
			$type['mode'] = $this->module->editorialMode($name);
			$types[] = $type;
		}
		$this->contentTypes = $types;
		$this->navTree = $this->navConfig->treeForTemplates($this->allowedTemplates);
	}

	/** @return string[] */
	protected function allowedActionsFor(string $template): array {
		$access = new TemplateAccess($this->module);
		return $access->allowedActions($this->auth->currentUser(), $template);
	}

	protected function bindEditorialUser(): void {
		$user = $this->auth->currentUser();
		if ($user) {
			$this->module->wire()->users->setCurrentUser($user);
			$this->auth->touch();
		}
	}

	protected function getDashboard(): string {
		$dash = new DashboardView();
		$templates = $this->allowedTemplates;
		$tiles = $this->tilesForTemplates($templates);
		$recent = $dash->collectRecent(
			$this->adapter,
			$templates,
			fn(string $tpl, string $id) => $this->url('t/' . $tpl . '/' . $id),
			5
		);
		$userName = $this->auth->displayName() ?: 'Redaktion';
		$first = preg_split('/\s+/', trim($userName))[0] ?? $userName;
		$content = $dash->render([
			'skin' => 'daten',
			'title' => 'Guten Tag, ' . $first,
			'lead' => 'Hier ist, was in der Redaktion wichtig ist.',
			'kicker' => $this->dashboardKicker(),
			'tiles' => $tiles,
			'recent' => $recent,
			'userName' => $userName,
			'statusCounts' => $this->statusCounts($templates),
		]);
		return $this->shell($content, [
			'title' => 'Übersicht',
			'treeTitle' => 'Inhalte',
			'treeHtml' => $this->renderFullTree(['node' => 'overview']),
			'primaryAction' => $this->primaryActionForTiles($tiles),
			'flash' => $this->takeFlash(),
			'designSkin' => 'daten',
		]);
	}

	/**
	 * @param array<int, array<string, mixed>> $tiles
	 * @return array{label: string, url: string}|null
	 */
	protected function primaryActionForTiles(array $tiles): ?array {
		foreach ($tiles as $tile) {
			if (!empty($tile['newUrl'])) {
				return ['label' => 'Neu: ' . ($tile['label'] ?? ''), 'url' => $tile['newUrl']];
			}
		}
		return null;
	}

	protected function getNodeDashboard(array $node, string $sectionId, ?string $groupId): string {
		$templates = $this->navConfig->templatesUnder($node);
		$templates = array_values(array_filter($templates, fn($t) => in_array($t, $this->allowedTemplates, true)));
		$dash = new DashboardView();
		$tiles = $this->tilesForTemplates($templates);
		$recent = $dash->collectRecent(
			$this->adapter,
			$templates,
			fn(string $tpl, string $id) => $this->url('t/' . $tpl . '/' . $id),
			5
		);
		$label = $node['label'] ?? $node['id'];
		$content = $dash->render([
			'skin' => 'daten',
			'title' => $label,
			'lead' => 'Inhaltstypen in diesem Bereich.',
			'kicker' => 'Bereich',
			'tiles' => $tiles,
			'recent' => $recent,
			'statusCounts' => $this->statusCounts($templates),
		]);

		$section = $this->navConfig->findNode($sectionId, $this->navTree);
		$treeHtml = $this->renderFullTree([
			'node' => $groupId ?: $sectionId,
			'section' => $sectionId,
		]);

		return $this->shell($content, [
			'title' => $label,
			'treeTitle' => $section['label'] ?? 'Inhalte',
			'treeHtml' => $treeHtml,
			'primaryAction' => $this->primaryActionForTiles($tiles),
			'flash' => $this->takeFlash(),
			'designSkin' => 'daten',
		]);
	}

	protected function dashboardKicker(): string {
		$days = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
		$months = [
			1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April', 5 => 'Mai', 6 => 'Juni',
			7 => 'Juli', 8 => 'August', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
		];
		$ts = time();
		$d = (int) date('w', $ts);
		$day = (int) date('j', $ts);
		$m = (int) date('n', $ts);
		$y = (int) date('Y', $ts);
		return $days[$d] . ', ' . $day . '. ' . $months[$m] . ' ' . $y;
	}

	/**
	 * @param string[] $templates
	 */
	/**
	 * @param string[] $templates
	 * @return array{draft: int, live: int}
	 */
	protected function statusCounts(array $templates): array {
		$draft = 0;
		$live = 0;
		foreach ($templates as $tpl) {
			try {
				$draft += $this->adapter->countRecords($tpl, 'unpublished');
				$live += $this->adapter->countRecords($tpl, 'published');
			} catch (\Throwable $e) {
			}
		}
		return ['draft' => $draft, 'live' => $live];
	}

	protected function tilesForTemplates(array $templates): array {
		$tiles = [];
		foreach ($templates as $tpl) {
			$label = $tpl;
			$mode = $this->module->editorialMode($tpl);
			$count = null;
			try {
				$schema = $this->adapter->readSchema($tpl);
				$label = $schema['label'] ?? $tpl;
				$count = $this->adapter->countRecords($tpl);
			} catch (\Throwable $e) {
			}
			$icon = 'file-text';
			foreach ($this->flattenNav($this->navTree) as $n) {
				if (($n['type'] ?? '') === 'template' && ($n['template'] ?? '') === $tpl) {
					$icon = $n['icon'] ?? $icon;
					if (!empty($n['label'])) {
						$label = $n['label'];
					}
					break;
				}
			}
			$tiles[] = [
				'label' => $label,
				'icon' => $icon,
				'mode' => $mode,
				'datatype' => $this->module->editorialDatatype($tpl),
				'count' => $count,
				'url' => $this->url('t/' . $tpl),
				'newUrl' => $mode === 'list' ? $this->url('t/' . $tpl . '/new') : null,
				'hint' => $mode === 'single' ? 'Einzelseite' : 'Datensätze',
			];
		}
		return $tiles;
	}

	protected function flattenNav(array $nodes): array {
		$out = [];
		foreach ($nodes as $n) {
			$out[] = $n;
			if (!empty($n['children'])) {
				foreach ($this->flattenNav($n['children']) as $c) {
					$out[] = $c;
				}
			}
		}
		return $out;
	}

	protected function getList(string $template): string {
		$schema = $this->adapter->readSchema($template);
		$records = $this->adapter->listRecords($template);
		$totalCount = $this->adapter->countRecords($template);
		$skin = $this->designSkinForTemplate($template);
		$list = new ListView();
		$content = $list->render($schema, $records, [
			'skin' => $skin,
			'newUrl' => $this->url('t/' . $template . '/new'),
			'editUrl' => fn(string $id) => $this->url('t/' . $template . '/' . $id),
			'totalCount' => $totalCount,
		]);
		return $this->shell($content, $this->shellContextForTemplate($template, $schema['label'] ?? $template, [
			'flash' => $this->takeFlash(),
			'active' => ['template' => $template],
			'designSkin' => $skin,
			'primaryAction' => ['label' => 'Neu: ' . ($schema['label'] ?? $template), 'url' => $this->url('t/' . $template . '/new')],
		]));
	}

	protected function getForm(string $template, ?string $id, array $values = [], array $errors = [], bool $single = false): string {
		$schema = $this->adapter->readSchema($template);
		$isNew = $id === null;

		if (!$isNew && $values === []) {
			$record = $this->adapter->getRecord($template, $id);
			if (!$record) {
				return $this->renderError(404, 'Eintrag nicht gefunden.');
			}
			$values = $record;
		}

		$form = new FormRenderer();
		$listLabel = $schema['label'] ?? $template;
		$title = $single
			? $listLabel
			: ($isNew ? $listLabel . ' anlegen' : ($values['title'] ?? 'Bearbeiten'));
		$skin = $this->designSkinForTemplate($template);

		$formErrors = $errors;
		$formError = $formErrors['_form'] ?? null;
		unset($formErrors['_form']);

		$content = '';
		if ($formError) {
			$content .= '<div class="bpe-flash bpe-flash--error" role="alert">' .
				htmlspecialchars($formError, ENT_QUOTES, 'UTF-8') . '</div>';
		}

		$actionUrl = $single
			? $this->url('t/' . $template)
			: ($isNew ? $this->url('t/' . $template . '/new') : $this->url('t/' . $template . '/' . $id));

		$content .= $form->renderForm($schema, $values, $formErrors, [
			'skin' => $skin,
			'action' => $actionUrl,
			'title' => $title,
			'cancelUrl' => $single ? null : $this->url('t/' . $template),
			'listLabel' => $listLabel,
			'csrf' => $this->csrfField(),
			'submitLabel' => 'Speichern',
			'previewUrl' => $values['url'] ?? null,
			'status' => $values['status'] ?? 'published',
			'showPublish' => true,
			'breadcrumb' => $single ? [
				['label' => 'Inhalte', 'url' => $this->url('nav/content')],
				['label' => $listLabel],
			] : [
				['label' => 'Inhalte', 'url' => $this->url('nav/content')],
				['label' => $listLabel, 'url' => $this->url('t/' . $template)],
				['label' => $isNew ? 'Neu' : 'Bearbeiten'],
			],
			'savedAt' => $values['modified'] ?? null,
		]);

		$needsTiny = $this->schemaNeedsTinyMce($schema);
		return $this->shell($content, $this->shellContextForTemplate($template, $title, [
			'flash' => $this->takeFlash(),
			'active' => ['template' => $template, 'record' => $id],
			'needsTinyMce' => $needsTiny,
			'designSkin' => $skin,
		]));
	}

	protected function postForm(string $template, ?string $id, bool $single = false): string {
		$session = $this->wire()->session;
		if (!$session->CSRF->hasValidToken()) {
			return $this->getForm($template, $id, $this->postedValues($template), [
				'_form' => 'Sicherheits-Token ungültig. Bitte erneut speichern.',
			], $single);
		}

		$data = $this->postedValues($template);
		if ($id !== null) {
			$data['id'] = $id;
		}

		$action = (string) $this->wire()->input->post('bpe_action');
		if ($action === 'publish') {
			$data['status'] = 'published';
		} elseif ($action === 'unpublish') {
			$data['status'] = 'unpublished';
		} else {
			$data['status'] = (string) ($this->wire()->input->post('status') ?: ($data['status'] ?? 'published'));
			if (!in_array($data['status'], ['published', 'unpublished'], true)) {
				$data['status'] = 'published';
			}
		}

		$auditAction = $id === null ? 'create' : ($action === 'publish' || $action === 'unpublish' ? $action : 'edit');
		$result = $this->adapter->saveRecord($template, $data, $auditAction);
		if (!empty($result['errors'])) {
			return $this->getForm($template, $id, $data, $result['errors'], $single);
		}

		$this->setFlash($action === 'publish' ? 'Veröffentlicht.' : ($action === 'unpublish' ? 'Als Entwurf gespeichert.' : 'Gespeichert.'));
		if ($single) {
			return $this->redirect($this->url('t/' . $template));
		}
		$savedId = (string) $result['record']['id'];
		return $this->redirect($this->url('t/' . $template . '/' . $savedId));
	}

	protected function postedValues(string $template): array {
		$input = $this->wire()->input;
		$schema = $this->adapter->readSchema($template);
		$data = [];

		foreach ($schema['fields'] as $field) {
			$name = $field['name'];
			$type = $field['type'];

			if ($type === 'unsupported') {
				continue;
			}
			if ($type === 'checkbox') {
				$data[$name] = (string) $input->post($name) === '1';
				continue;
			}
			if ($type === 'pageReference') {
				$raw = $input->post($name);
				$data[$name] = is_array($raw) ? $raw : ($raw !== null && $raw !== '' ? [$raw] : []);
				continue;
			}
			if ($type === 'select' && !empty($field['multiple'])) {
				$raw = $input->post($name);
				$data[$name] = is_array($raw)
					? array_map('strval', $raw)
					: ($raw !== null && $raw !== '' ? [(string) $raw] : []);
				continue;
			}
			if ($type === 'image' || $type === 'file') {
				$data[$name . '_clear'] = (string) $input->post($name . '_clear') === '1';
				$existing = $input->post($name . '_existing');
				$data[$name . '_existing'] = $existing ? (string) $existing : null;
				$remove = $input->post($name . '_remove');
				$data[$name . '_remove'] = is_array($remove) ? array_map('strval', $remove) : [];
				if ($data[$name . '_clear']) {
					$data[$name] = null;
				} elseif (!empty($_FILES[$name]['name'])) {
					$rawName = $_FILES[$name]['name'];
					$data[$name] = is_array($rawName)
						? array_values(array_filter(array_map('basename', $rawName)))
						: basename((string) $rawName);
				} else {
					$data[$name] = $data[$name . '_existing']
						? ['name' => $data[$name . '_existing']]
						: null;
				}
				continue;
			}
			if ($type === 'integer' || $type === 'float') {
				$raw = $input->post($name);
				$data[$name] = $raw === null ? '' : (string) $raw;
				continue;
			}
			if ($type === 'repeater') {
				// $input->post() begrenzt verschachtelte Arrays auf $config->wireInputArrayDepth (Standard: 1) —
				// Repeater-Zeilen sind aber "name[idx][unterfeld]" = 2 Ebenen, würden also stillschweigend zu []
				// zusammengestrichen. Daher hier bewusst roh aus $_POST lesen; die eigentliche Sanitization pro
				// Unterfeld übernimmt weiterhin RepeaterFieldAdapter über die jeweiligen Feld-Adapter.
				$raw = $_POST[$name] ?? null;
				$data[$name] = is_array($raw) ? $raw : [];
				continue;
			}
			$data[$name] = (string) ($input->post($name) ?? '');
		}

		return $data;
	}

	protected function schemaNeedsTinyMce(array $schema): bool {
		return $this->fieldsNeedTinyMce($schema['fields'] ?? []);
	}

	protected function fieldsNeedTinyMce(array $fields): bool {
		foreach ($fields as $field) {
			if (($field['type'] ?? '') === 'html' || !empty($field['html'])) {
				return true;
			}
			if (($field['type'] ?? '') === 'repeater' && $this->fieldsNeedTinyMce($field['itemFields'] ?? [])) {
				return true;
			}
		}
		return false;
	}

	protected function tinyMceUrl(): string {
		$config = $this->wire()->config;
		$path = $config->paths->modules . 'Inputfield/InputfieldTinyMCE/tinymce-6.8.2/tinymce.min.js';
		if (is_file($path)) {
			return $config->urls->modules . 'Inputfield/InputfieldTinyMCE/tinymce-6.8.2/tinymce.min.js';
		}
		return '';
	}

	protected function shellContextForTemplate(string $template, string $title, array $extra = []): array {
		$section = $this->findSectionForTemplate($template);
		$active = $extra['active'] ?? ['template' => $template];
		$treeHtml = $this->renderFullTree($active);

		return array_merge([
			'title' => $title,
			'treeTitle' => $section['label'] ?? 'Inhalte',
			'treeHtml' => $treeHtml,
		], $extra);
	}

	protected function findSectionForTemplate(string $template): ?array {
		foreach ($this->navTree as $node) {
			if (($node['type'] ?? '') !== 'section') {
				continue;
			}
			if (in_array($template, $this->navConfig->templatesUnder($node), true)) {
				return $node;
			}
		}
		foreach ($this->navTree as $node) {
			if (($node['type'] ?? '') === 'section') {
				return $node;
			}
		}
		return null;
	}

	protected function renderSectionTree(array $section, array $active): string {
		$modes = $this->module->editorialModes();
		$tree = new NavTree(
			$this->adapter,
			$this->navConfig,
			fn(string $path) => $this->url($path),
			fn(string $tpl, string $id) => $this->url('t/' . $tpl . '/' . $id),
			$modes
		);
		return $tree->render($section['children'] ?? [], $active);
	}

	/**
	 * Vollständiger Navigationsbaum (Übersicht + alle Sections mit ihren Gruppen/Templates) —
	 * eine Navigationsfläche statt der früheren zweigeteilten Rail+Baum-Ansicht, damit auf
	 * Mobil ein einzelnes Overlay für die gesamte Navigation reicht.
	 */
	protected function renderFullTree(array $active): string {
		$html = '<ul class="bpe-tree__list">';
		foreach ($this->navTree as $node) {
			$type = $node['type'] ?? '';
			if ($type === 'dashboard') {
				$href = $this->url();
				$activeCls = ($active['node'] ?? '') === 'overview' ? ' is-active' : '';
				$html .= '<li class="bpe-tree__item' . $activeCls . '"><a class="bpe-tree__row" href="'
					. htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">'
					. '<span class="bpe-tree__label">' . htmlspecialchars($node['label'] ?? 'Übersicht', ENT_QUOTES, 'UTF-8')
					. '</span></a></li>';
				continue;
			}
			if ($type !== 'section') {
				continue;
			}
			$open = $this->isSectionActive($node, $active);
			$html .= '<li class="bpe-tree__item bpe-tree__item--group' . ($open ? ' is-open' : '') . '">';
			$html .= '<button type="button" class="bpe-tree__row bpe-tree__toggle" aria-expanded="' . ($open ? 'true' : 'false') . '">';
			$html .= '<span class="bpe-tree__label">' . htmlspecialchars($node['label'] ?? '', ENT_QUOTES, 'UTF-8') . '</span>';
			$html .= '</button>';
			$html .= '<div class="bpe-tree__children"' . ($open ? '' : ' hidden') . '>';
			$html .= $this->renderSectionTree($node, $active);
			$html .= '</div></li>';
		}
		$html .= '</ul>';
		return '<div class="bpe-tree">' . $html . '</div>';
	}

	protected function isSectionActive(array $section, array $active): bool {
		$id = $section['id'] ?? null;
		if ($id !== null && (($active['node'] ?? null) === $id || ($active['section'] ?? null) === $id)) {
			return true;
		}
		$activeTpl = $active['template'] ?? null;
		return $activeTpl !== null && in_array($activeTpl, $this->navConfig->templatesUnder($section), true);
	}

	protected function shell(string $content, array $vars): string {
		$shell = new ShellView();
		$theme = $this->themeCss();
		$designSkin = (string) ($vars['designSkin'] ?? 'daten');
		if ($designSkin !== 'daten') {
			$designSkin = 'legacy';
		}
		return $shell->render(array_merge([
			'baseUrl' => $this->module->baseUrl(),
			'assetUrl' => $this->module->moduleUrl() . 'assets/',
			'content' => $content,
			'userName' => $this->auth->displayName(),
			'isDemo' => $this->auth->isDemoSession(),
			'dataSource' => 'ProcessWire',
			'themeStyle' => $theme,
			'tinyMceUrl' => $this->tinyMceUrl(),
			'needsTinyMce' => false,
			'brandName' => $this->module->brandName(),
			'brandLogoUrl' => $this->module->brandLogoUrl(),
			'designSkin' => $designSkin,
		], $vars));
	}

	/** Broadsheet-Skin nur wenn Template Daten-Art „Daten“ hat. */
	protected function designSkinForTemplate(string $template): string {
		return $this->module->hasDatatypeView($template) ? 'daten' : 'legacy';
	}

	protected function themeCss(): string {
		$accent = (string) ($this->module->get('theme_accent') ?: '#1f6b4a');
		$radius = (string) ($this->module->get('theme_radius') ?: '8');
		$text = (string) ($this->module->get('theme_text_color') ?: '#201e1d');
		$muted = (string) ($this->module->get('theme_muted_color') ?: '#6b736e');
		$navBg = (string) ($this->module->get('theme_rail_bg') ?: '#ffffff');
		$navText = (string) ($this->module->get('theme_nav_text') ?: '#201e1d');
		$spacingKey = (string) ($this->module->get('theme_spacing') ?: 'normal');

		$accent = preg_match('/^#[0-9a-fA-F]{3,8}$/', $accent) ? $accent : '#1f6b4a';
		$text = preg_match('/^#[0-9a-fA-F]{3,8}$/', $text) ? $text : '#201e1d';
		$muted = preg_match('/^#[0-9a-fA-F]{3,8}$/', $muted) ? $muted : '#6b736e';
		$navBg = preg_match('/^#[0-9a-fA-F]{3,8}$/', $navBg) ? $navBg : '#ffffff';
		$navText = preg_match('/^#[0-9a-fA-F]{3,8}$/', $navText) ? $navText : '#201e1d';
		$radius = preg_match('/^\d+(\.\d+)?$/', $radius) ? $radius : '8';
		$space = match ($spacingKey) {
			'compact' => '7.5',
			'generous' => '12.5',
			default => '10',
		};
		$radiusNum = (float) $radius;
		$radiusMd = max(2, $radiusNum - 2);
		$radiusSm = max(2, $radiusNum - 4);
		$accentInk = $this->module->contrastTextColor($accent);

		// --bpe-* bleibt Settings-Schicht (theme_* Config); Broadsheet-/App-Namen sind Aliase darauf.
		// Hintergrund/Rahmen/Status-Farben sind bewusst fix (nicht konfigurierbar) — schützt
		// Lesbarkeit und Status-Bedeutung vor Kunden-Farbwahl (siehe Design-Tab-Absprache).
		return ':root{'
			. '--bpe-accent:' . $accent . ';'
			. '--bpe-radius:' . $radius . 'px;'
			. '--bpe-ink:' . $text . ';'
			. '--bpe-muted:' . $muted . ';'
			. '--bpe-space:' . $space . 'px;'
			. '--color-accent:var(--bpe-accent);'
			. '--color-accent-ink:' . $accentInk . ';'
			. '--color-text:var(--bpe-ink);'
			. '--color-muted:var(--bpe-muted);'
			. '--color-bg:var(--bpe-bg);'
			. '--color-surface:var(--bpe-bg-panel);'
			. '--color-border:#e2e5e2;'
			. '--color-divider:var(--bpe-line);'
			. '--nav-bg:' . $navBg . ';'
			. '--nav-text:' . $navText . ';'
			. '--nav-muted:color-mix(in srgb, ' . $navText . ' 65%, transparent);'
			. '--status-draft:#c79e28;'
			. '--status-review:#2b6cb0;'
			. '--status-live:#2e8159;'
			. '--radius-lg:var(--bpe-radius);'
			. '--radius-md:' . $radiusMd . 'px;'
			. '--radius-sm:' . $radiusSm . 'px;'
			. '--space-1:calc(var(--bpe-space)*0.5);'
			. '--space-2:var(--bpe-space);'
			. '--space-3:calc(var(--bpe-space)*1.5);'
			. '--space-4:calc(var(--bpe-space)*2);'
			. '--space-6:calc(var(--bpe-space)*3);'
			. '--space-8:calc(var(--bpe-space)*4);'
			. '}';
	}

	protected function resolveSingletonId(string $template): ?string {
		$records = $this->adapter->listRecords($template);
		if (!$records) {
			return null;
		}
		return (string) ($records[0]['id'] ?? '');
	}

	protected function wire() {
		return $this->module->wire();
	}

	protected function isAllowedTemplate(string $template): bool {
		return in_array($template, $this->allowedTemplates, true);
	}

	protected function getLogin(string $error = ''): string {
		return $this->viewLogin([
			'title' => 'Anmelden',
			'error' => $error,
			'action' => $this->url('login'),
			'csrf' => $this->csrfField(),
			'demoEnabled' => $this->auth->demoEnabled(),
			'demoUser' => (string) $this->module->get('login_user'),
			'brandName' => $this->module->brandName(),
			'brandLogoUrl' => $this->module->brandLogoUrl(),
		]);
	}

	protected function postLogin(): string {
		$session = $this->wire()->session;
		if (!$session->CSRF->hasValidToken()) {
			return $this->getLogin('Sicherheits-Token ungültig. Bitte erneut versuchen.');
		}
		$input = $this->wire()->input;
		$user = (string) $input->post('username');
		$pass = (string) $input->post('password');
		if ($this->auth->attempt($user, $pass)) {
			$this->bindEditorialUser();
			$this->refreshAccess();
			return $this->redirect($this->url());
		}
		return $this->getLogin($this->auth->throttleMessage()
			?? 'Anmeldung fehlgeschlagen. Prüfen Sie Benutzername, Passwort und die Permission „editorial-access“.');
	}

	protected function viewLogin(array $vars): string {
		$vars['module'] = $this->module;
		$vars['baseUrl'] = $this->module->baseUrl();
		$vars['assetUrl'] = $this->module->moduleUrl() . 'assets/';
		$file = $this->module->modulePath() . '/views/login.php';
		extract($vars, EXTR_SKIP);
		ob_start();
		include $file;
		return (string) ob_get_clean();
	}

	protected function renderError(int $code, string $message): string {
		http_response_code($code);
		$content = '<div class="bpe-empty"><p class="bpe-empty__text">' .
			htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>' .
			'<a class="bpe-btn bpe-btn--primary" href="' . htmlspecialchars($this->url(), ENT_QUOTES, 'UTF-8') .
			'">Zur Übersicht</a></div>';
		return $this->shell($content, [
			'title' => 'Hinweis',
			'treeTitle' => 'Inhalte',
			'treeHtml' => $this->renderFullTree([]),
		]);
	}

	protected function segments(HookEvent $event): array {
		$all = (string) ($event->arguments(1) ?? '');
		if ($all === '') {
			return [];
		}
		return array_values(array_filter(explode('/', trim($all, '/')), fn($s) => $s !== ''));
	}

	protected function url(string $path = ''): string {
		$path = trim($path, '/');
		return $this->module->baseUrl() . ($path !== '' ? $path . '/' : '');
	}

	protected function redirect(string $url): string {
		$this->wire()->session->redirect($url);
		return '';
	}

	protected function csrfField(): string {
		return $this->wire()->session->CSRF->renderInput();
	}

	protected function setFlash(string $message): void {
		$this->wire()->session->setFor('bpe', 'flash', $message);
	}

	protected function takeFlash(): ?string {
		$session = $this->wire()->session;
		$msg = $session->getFor('bpe', 'flash');
		$session->removeFor('bpe', 'flash');
		return $msg ? (string) $msg : null;
	}
}
