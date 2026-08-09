<?php namespace ProcessWire\BsProcessEditorial\Ui;

use ProcessWire\BsProcessEditorial;
use ProcessWire\BsProcessEditorial\Adapter\AdapterInterface;
use ProcessWire\BsProcessEditorial\Auth\EditorialAuth;
use ProcessWire\BsProcessEditorial\Auth\TemplateAccess;
use ProcessWire\BsProcessEditorial\FormEngine\FormRenderer;
use ProcessWire\HookEvent;

/**
 * Routing der Editorial-App — Inhaltstypen dynamisch aus dem Adapter + Rechtefilter.
 */
class Router {

	protected BsProcessEditorial $module;
	protected EditorialAuth $auth;
	protected AdapterInterface $adapter;

	/** @var array<int, array{name: string, label: string, mode?: string}> */
	protected array $contentTypes = [];

	public function __construct(BsProcessEditorial $module) {
		$this->module = $module;
		$this->auth = new EditorialAuth($module);
		$this->adapter = $module->adapter();
		$this->refreshContentTypes();
	}

	public function dispatch(HookEvent $event): string {
		$segments = $this->segments($event);
		$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

		if (($segments[0] ?? '') === 'login') {
			if ($this->auth->isLoggedIn()) {
				return $this->redirectHome();
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
		$this->refreshContentTypes();

		if ($this->contentTypes === []) {
			return $this->renderError(
				403,
				'Keine Inhaltstypen für Ihren Zugang freigegeben. Bitte einen Administrator unter Setup → Redaktion prüfen.'
			);
		}

		if ($segments === [] || $segments === ['']) {
			return $this->redirectHome();
		}

		$template = $segments[0];
		$action = $segments[1] ?? null;

		if (!$this->isAllowedTemplate($template)) {
			return $this->renderError(404, 'Unbekannter Inhaltstyp.');
		}

		$isSingle = $this->module->editorialMode($template) === 'single';

		if ($isSingle) {
			if ($action === 'new') {
				return $this->redirect($this->url($template));
			}
			if ($action === null || $action === '' || ctype_digit((string) $action)) {
				$id = ($action !== null && $action !== '' && ctype_digit((string) $action))
					? (string) $action
					: $this->resolveSingletonId($template);
				if ($id === null) {
					return $this->renderError(404, 'Keine Seite für diesen Inhaltstyp gefunden.');
				}
				if ($action !== null && $action !== '' && ctype_digit((string) $action) && $method === 'GET') {
					return $this->redirect($this->url($template));
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

	protected function refreshContentTypes(): void {
		$all = $this->adapter->listContentTypes();
		$access = new TemplateAccess($this->module);
		$user = $this->auth->currentUser();
		$allowed = $this->auth->isDemoSession()
			? $this->module->editorialTemplateNames()
			: $access->allowedTemplates($user);

		$types = [];
		foreach ($all as $type) {
			$name = (string) ($type['name'] ?? '');
			if ($name === '' || !in_array($name, $allowed, true)) {
				continue;
			}
			$type['mode'] = $this->module->editorialMode($name);
			$types[] = $type;
		}
		$this->contentTypes = $types;
	}

	protected function bindEditorialUser(): void {
		$user = $this->auth->currentUser();
		if ($user) {
			$this->module->wire()->users->setCurrentUser($user);
		}
	}

	protected function redirectHome(): string {
		$first = $this->contentTypes[0]['name'] ?? '';
		if ($first === '') {
			$this->refreshContentTypes();
			$first = $this->contentTypes[0]['name'] ?? '';
		}
		return $this->redirect($first !== '' ? $this->url($first) : $this->module->baseUrl());
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
		foreach ($this->contentTypes as $type) {
			if ($type['name'] === $template) {
				return true;
			}
		}
		return false;
	}

	protected function getLogin(string $error = ''): string {
		return $this->view('login', [
			'title' => 'Anmelden',
			'error' => $error,
			'action' => $this->url('login'),
			'csrf' => $this->csrfField(),
			'userName' => null,
			'navItems' => [],
			'demoEnabled' => $this->auth->demoEnabled(),
			'demoUser' => (string) $this->module->get('login_user'),
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
			$this->refreshContentTypes();
			return $this->redirectHome();
		}
		return $this->getLogin('Anmeldung fehlgeschlagen. Prüfen Sie Benutzername, Passwort und die Permission „editorial-access“.');
	}

	protected function getList(string $template): string {
		$schema = $this->adapter->readSchema($template);
		$records = $this->adapter->listRecords($template);
		$list = new ListView();
		$content = $list->render($schema, $records, [
			'newUrl' => $this->url($template . '/new'),
			'editUrl' => fn(string $id) => $this->url($template . '/' . $id),
		]);
		return $this->view('app', [
			'title' => $schema['label'] ?? $template,
			'content' => $content,
			'navActive' => $template,
			'flash' => $this->takeFlash(),
			'dataSource' => $this->dataSourceLabel(),
		]);
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
			? $this->url($template)
			: ($isNew ? $this->url($template . '/new') : $this->url($template . '/' . $id));

		$content .= $form->renderForm($schema, $values, $formErrors, [
			'action' => $actionUrl,
			'title' => $title,
			'cancelUrl' => $single ? null : $this->url($template),
			'csrf' => $this->csrfField(),
			'submitLabel' => 'Speichern',
			'breadcrumb' => $single ? [
				['label' => $listLabel],
			] : [
				['label' => $listLabel, 'url' => $this->url($template)],
				['label' => $isNew ? 'Neu' : 'Bearbeiten'],
			],
			'savedAt' => $values['modified'] ?? null,
		]);

		return $this->view('app', [
			'title' => $title,
			'content' => $content,
			'navActive' => $template,
			'flash' => $this->takeFlash(),
			'dataSource' => $this->dataSourceLabel(),
		]);
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

		$result = $this->adapter->saveRecord($template, $data);
		if (!empty($result['errors'])) {
			return $this->getForm($template, $id, $data, $result['errors'], $single);
		}

		$this->setFlash('Gespeichert.');
		if ($single) {
			return $this->redirect($this->url($template));
		}
		$savedId = (string) $result['record']['id'];
		return $this->redirect($this->url($template . '/' . $savedId));
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
			if ($type === 'image') {
				$data[$name . '_clear'] = (string) $input->post($name . '_clear') === '1';
				$existing = $input->post($name . '_existing');
				$data[$name . '_existing'] = $existing ? (string) $existing : null;
				if ($data[$name . '_clear']) {
					$data[$name] = null;
				} elseif (!empty($_FILES[$name]['name'])) {
					$data[$name] = basename((string) $_FILES[$name]['name']);
				} else {
					$data[$name] = $data[$name . '_existing'];
				}
				continue;
			}
			$data[$name] = (string) ($input->post($name) ?? '');
		}

		return $data;
	}

	protected function dataSourceLabel(): string {
		if ($this->adapter instanceof \ProcessWire\BsProcessEditorial\Adapter\ProcessWireAdapter) {
			return 'ProcessWire';
		}
		return 'Mock';
	}

	protected function view(string $name, array $vars): string {
		$vars['module'] = $this->module;
		$vars['baseUrl'] = $this->module->baseUrl();
		$vars['assetUrl'] = $this->module->moduleUrl() . 'assets/';
		$vars['userName'] = $vars['userName'] ?? $this->auth->displayName();
		$vars['isDemo'] = $vars['isDemo'] ?? $this->auth->isDemoSession();
		$vars['flash'] = $vars['flash'] ?? null;
		$vars['navActive'] = $vars['navActive'] ?? null;
		$vars['navItems'] = $vars['navItems'] ?? $this->contentTypes;
		$vars['dataSource'] = $vars['dataSource'] ?? $this->dataSourceLabel();

		$file = $this->module->modulePath() . '/views/' . $name . '.php';
		if (!is_file($file)) {
			throw new \RuntimeException("View fehlt: {$name}");
		}
		extract($vars, EXTR_SKIP);
		ob_start();
		include $file;
		return (string) ob_get_clean();
	}

	protected function renderError(int $code, string $message): string {
		http_response_code($code);
		$home = $this->contentTypes[0]['name'] ?? '';
		$homeUrl = $home !== '' ? $this->url($home) : $this->module->baseUrl();
		return $this->view('app', [
			'title' => 'Hinweis',
			'content' => '<div class="bpe-empty"><p class="bpe-empty__text">' .
				htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>' .
				($home !== ''
					? '<a class="bpe-btn bpe-btn--primary" href="' . htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') .
						'">Zur Übersicht</a>'
					: '') .
				'</div>',
			'navActive' => null,
			'userName' => $this->auth->displayName(),
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
