<?php namespace ProcessWire\BsProcessEditorial\Ui;

use ProcessWire\BsProcessEditorial;
use ProcessWire\BsProcessEditorial\Adapter\AdapterInterface;
use ProcessWire\BsProcessEditorial\Auth\EditorialAuth;
use ProcessWire\BsProcessEditorial\FormEngine\FormRenderer;
use ProcessWire\HookEvent;

/**
 * Routing der Editorial-App unter /editorial/…
 */
class Router {

	protected BsProcessEditorial $module;
	protected EditorialAuth $auth;
	protected AdapterInterface $adapter;

	public function __construct(BsProcessEditorial $module) {
		$this->module = $module;
		$this->auth = new EditorialAuth($module);
		$this->adapter = $module->adapter();
	}

	public function dispatch(HookEvent $event): string {
		$segments = $this->segments($event);
		$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

		if (($segments[0] ?? '') === 'login') {
			return $method === 'POST' ? $this->postLogin() : $this->getLogin();
		}
		if (($segments[0] ?? '') === 'logout') {
			$this->auth->logout();
			return $this->redirect($this->url('login'));
		}

		if (!$this->auth->isLoggedIn()) {
			return $this->redirect($this->url('login'));
		}

		if ($segments === [] || $segments === ['']) {
			return $this->redirect($this->url('einrichtung'));
		}

		$template = $segments[0];
		$action = $segments[1] ?? null;

		if ($template !== 'einrichtung') {
			return $this->renderError(404, 'Unbekannter Inhaltstyp.');
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

	protected function wire() {
		return $this->module->wire();
	}

	protected function getLogin(string $error = ''): string {
		return $this->view('login', [
			'title' => 'Anmelden',
			'error' => $error,
			'action' => $this->url('login'),
			'csrf' => $this->csrfField(),
			'userName' => null,
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
			return $this->redirect($this->url('einrichtung'));
		}
		return $this->getLogin('Benutzername oder Passwort ungültig.');
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

	protected function getForm(string $template, ?string $id, array $values = [], array $errors = []): string {
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
		$title = $isNew
			? $listLabel . ' anlegen'
			: ($values['title'] ?? 'Bearbeiten');

		$formErrors = $errors;
		$formError = $formErrors['_form'] ?? null;
		unset($formErrors['_form']);

		$content = '';
		if ($formError) {
			$content .= '<div class="bpe-flash bpe-flash--error" role="alert">' .
				htmlspecialchars($formError, ENT_QUOTES, 'UTF-8') . '</div>';
		}

		$content .= $form->renderForm($schema, $values, $formErrors, [
			'action' => $isNew ? $this->url($template . '/new') : $this->url($template . '/' . $id),
			'title' => $title,
			'cancelUrl' => $this->url($template),
			'csrf' => $this->csrfField(),
			'submitLabel' => 'Speichern',
			'breadcrumb' => [
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

	protected function postForm(string $template, ?string $id): string {
		$session = $this->wire()->session;
		if (!$session->CSRF->hasValidToken()) {
			return $this->getForm($template, $id, $this->postedValues($template), [
				'_form' => 'Sicherheits-Token ungültig. Bitte erneut speichern.',
			]);
		}

		$data = $this->postedValues($template);
		if ($id !== null) {
			$data['id'] = $id;
		}

		$result = $this->adapter->saveRecord($template, $data);
		if (!empty($result['errors'])) {
			return $this->getForm($template, $id, $data, $result['errors']);
		}

		$this->setFlash('Gespeichert.');
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
				// Für MockAdapter weiterhin einfacher Dateiname
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
		$adapter = $this->adapter;
		if ($adapter instanceof \ProcessWire\BsProcessEditorial\Adapter\ProcessWireAdapter) {
			return 'ProcessWire';
		}
		return 'Mock';
	}

	protected function view(string $name, array $vars): string {
		$vars['module'] = $this->module;
		$vars['baseUrl'] = $this->module->baseUrl();
		$vars['assetUrl'] = $this->module->moduleUrl() . 'assets/';
		$vars['userName'] = $vars['userName'] ?? $this->auth->userName();
		$vars['flash'] = $vars['flash'] ?? null;
		$vars['navActive'] = $vars['navActive'] ?? null;
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
		return $this->view('app', [
			'title' => 'Fehler',
			'content' => '<div class="bpe-empty"><p class="bpe-empty__text">' .
				htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>' .
				'<a class="bpe-btn bpe-btn--primary" href="' . htmlspecialchars($this->url('einrichtung'), ENT_QUOTES, 'UTF-8') .
				'">Zur Übersicht</a></div>',
			'navActive' => null,
			'userName' => $this->auth->userName(),
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
