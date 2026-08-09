<?php namespace ProcessWire\BsProcessEditorial\Ui;

use ProcessWire\BsProcessEditorial;
use ProcessWire\BsProcessEditorial\Adapter\AdapterInterface;
use ProcessWire\BsProcessEditorial\Adapter\ProcessWireAdapter;
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

		if (!$this->isAllowedTemplate($template)) {
			return $this->renderError(404, 'Unbekannter Inhaltstyp.');
		}

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
			return $method === 'POST' ? $this->postForm($template, null) : $this->getForm($template, null);
		}
		if (ctype_digit($action)) {
			return $method === 'POST' ? $this->postForm($template, $action) : $this->getForm($template, $action);
		}

		return $this->renderError(404, 'Seite nicht gefunden.');
	}

	protected function refreshAccess(): void {
		$access = new TemplateAccess($this->module);
		$user = $this->auth->currentUser();
		$this->allowedTemplates = $this->auth->isDemoSession()
			? $this->module->editorialTemplateNames()
			: $access->allowedTemplates($user);

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

	protected function bindEditorialUser(): void {
		$user = $this->auth->currentUser();
		if ($user) {
			$this->module->wire()->users->setCurrentUser($user);
		}
	}

	protected function getDashboard(): string {
		$dash = new DashboardView();
		$templates = $this->allowedTemplates;
		$tiles = $this->tilesForTemplates($templates);
		$recent = $dash->collectRecent(
			$this->adapter,
			$templates,
			fn(string $tpl, string $id) => $this->url('t/' . $tpl . '/' . $id)
		);
		$content = $dash->render([
			'title' => 'Übersicht',
			'lead' => 'Zuletzt bearbeitet und Schnellzugriff auf Ihre Inhaltstypen.',
			'tiles' => $tiles,
			'recent' => $recent,
		]);
		return $this->shell($content, [
			'title' => 'Übersicht',
			'railActive' => 'overview',
			'treeTitle' => 'Navigation',
			'treeHtml' => $this->renderGlobalTree(['node' => 'overview']),
			'flash' => $this->takeFlash(),
		]);
	}

	protected function getNodeDashboard(array $node, string $sectionId, ?string $groupId): string {
		$templates = $this->navConfig->templatesUnder($node);
		$templates = array_values(array_filter($templates, fn($t) => in_array($t, $this->allowedTemplates, true)));
		$dash = new DashboardView();
		$tiles = $this->tilesForTemplates($templates);
		$recent = $dash->collectRecent(
			$this->adapter,
			$templates,
			fn(string $tpl, string $id) => $this->url('t/' . $tpl . '/' . $id)
		);
		$label = $node['label'] ?? $node['id'];
		$content = $dash->render([
			'title' => $label,
			'lead' => 'Inhaltstypen in diesem Bereich.',
			'tiles' => $tiles,
			'recent' => $recent,
		]);

		$section = $this->navConfig->findNode($sectionId, $this->navTree);
		$treeHtml = $this->renderSectionTree($section ?? [], [
			'node' => $groupId ?: $sectionId,
		]);

		return $this->shell($content, [
			'title' => $label,
			'railActive' => $sectionId,
			'treeTitle' => $section['label'] ?? 'Inhalte',
			'treeHtml' => $treeHtml,
			'flash' => $this->takeFlash(),
		]);
	}

	/**
	 * @param string[] $templates
	 */
	protected function tilesForTemplates(array $templates): array {
		$tiles = [];
		foreach ($templates as $tpl) {
			$label = $tpl;
			$mode = $this->module->editorialMode($tpl);
			$count = null;
			try {
				$schema = $this->adapter->readSchema($tpl);
				$label = $schema['label'] ?? $tpl;
				$count = count($this->adapter->listRecords($tpl));
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
				'count' => $count,
				'url' => $this->url('t/' . $tpl),
				'newUrl' => $mode === 'list' ? $this->url('t/' . $tpl . '/new') : null,
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
		$list = new ListView();
		$content = $list->render($schema, $records, [
			'newUrl' => $this->url('t/' . $template . '/new'),
			'editUrl' => fn(string $id) => $this->url('t/' . $template . '/' . $id),
		]);
		return $this->shell($content, $this->shellContextForTemplate($template, $schema['label'] ?? $template, [
			'flash' => $this->takeFlash(),
			'active' => ['template' => $template],
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
			'action' => $actionUrl,
			'title' => $title,
			'cancelUrl' => $single ? null : $this->url('t/' . $template),
			'csrf' => $this->csrfField(),
			'submitLabel' => 'Speichern',
			'previewUrl' => $values['url'] ?? null,
			'status' => $values['status'] ?? 'published',
			'showPublish' => !$isNew || $this->adapter instanceof ProcessWireAdapter,
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

		$result = $this->adapter->saveRecord($template, $data);
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

			if ($type === 'checkbox') {
				$data[$name] = (string) $input->post($name) === '1';
				continue;
			}
			if ($type === 'pageReference') {
				$raw = $input->post($name);
				$data[$name] = is_array($raw) ? $raw : ($raw !== null && $raw !== '' ? [$raw] : []);
				continue;
			}
			if ($type === 'image' || $type === 'file') {
				$data[$name . '_clear'] = (string) $input->post($name . '_clear') === '1';
				$existing = $input->post($name . '_existing');
				$data[$name . '_existing'] = $existing ? (string) $existing : null;
				if ($data[$name . '_clear']) {
					$data[$name] = null;
				} elseif (!empty($_FILES[$name]['name'])) {
					$data[$name] = basename((string) $_FILES[$name]['name']);
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
			$data[$name] = (string) ($input->post($name) ?? '');
		}

		return $data;
	}

	protected function schemaNeedsTinyMce(array $schema): bool {
		foreach ($schema['fields'] ?? [] as $field) {
			if (($field['type'] ?? '') === 'html' || !empty($field['html'])) {
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
		$sectionId = $section['id'] ?? 'content';
		$active = $extra['active'] ?? ['template' => $template];
		$treeHtml = $section
			? $this->renderSectionTree($section, $active)
			: $this->renderGlobalTree($active);

		return array_merge([
			'title' => $title,
			'railActive' => $sectionId,
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

	protected function renderGlobalTree(array $active): string {
		$html = '<ul class="bpe-tree__list">';
		foreach ($this->navTree as $node) {
			$type = $node['type'] ?? '';
			if ($type === 'dashboard') {
				$href = $this->url();
				$activeCls = ($active['node'] ?? '') === 'overview' ? ' is-active' : '';
				$html .= '<li class="bpe-tree__item' . $activeCls . '"><a class="bpe-tree__row" href="'
					. htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">'
					. Icons::svg($node['icon'] ?? 'layout-dashboard', 'bpe-icon bpe-icon--sm')
					. '<span class="bpe-tree__label">' . htmlspecialchars($node['label'] ?? 'Übersicht', ENT_QUOTES, 'UTF-8')
					. '</span></a></li>';
			} elseif ($type === 'section') {
				$href = $this->url('nav/' . ($node['id'] ?? ''));
				$html .= '<li class="bpe-tree__item"><a class="bpe-tree__row" href="'
					. htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">'
					. Icons::svg($node['icon'] ?? 'file-text', 'bpe-icon bpe-icon--sm')
					. '<span class="bpe-tree__label">' . htmlspecialchars($node['label'] ?? '', ENT_QUOTES, 'UTF-8')
					. '</span></a></li>';
			}
		}
		$html .= '</ul>';
		return '<div class="bpe-tree">' . $html . '</div>';
	}

	protected function shell(string $content, array $vars): string {
		$shell = new ShellView();
		$theme = $this->themeCss();
		return $shell->render(array_merge([
			'baseUrl' => $this->module->baseUrl(),
			'assetUrl' => $this->module->moduleUrl() . 'assets/',
			'content' => $content,
			'userName' => $this->auth->displayName(),
			'isDemo' => $this->auth->isDemoSession(),
			'railItems' => $this->navConfig->railItems($this->allowedTemplates),
			'dataSource' => $this->adapter instanceof ProcessWireAdapter ? 'ProcessWire' : 'Mock',
			'themeStyle' => $theme,
			'tinyMceUrl' => $this->tinyMceUrl(),
			'needsTinyMce' => false,
			'treeCollapsed' => false,
			'brandName' => $this->module->brandName(),
			'brandLogoUrl' => $this->module->brandLogoUrl(),
		], $vars));
	}

	protected function themeCss(): string {
		$accent = (string) ($this->module->get('theme_accent') ?: '#1f6b4a');
		$rail = (string) ($this->module->get('theme_rail_bg') ?: '#1c1f1d');
		$radius = (string) ($this->module->get('theme_radius') ?: '8');
		$accent = preg_match('/^#[0-9a-fA-F]{3,8}$/', $accent) ? $accent : '#1f6b4a';
		$rail = preg_match('/^#[0-9a-fA-F]{3,8}$/', $rail) ? $rail : '#1c1f1d';
		$radius = preg_match('/^\d+(\.\d+)?$/', $radius) ? $radius : '8';
		return ':root{--bpe-accent:' . $accent . ';--bpe-rail-bg:' . $rail . ';--bpe-radius:' . $radius . 'px;}';
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
		return $this->getLogin('Anmeldung fehlgeschlagen. Prüfen Sie Benutzername, Passwort und die Permission „editorial-access“.');
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
			'railActive' => 'overview',
			'treeTitle' => 'Navigation',
			'treeHtml' => $this->renderGlobalTree([]),
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
