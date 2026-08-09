<?php namespace ProcessWire;

/**
 * BsProcessEditorial — Redaktionsoberfläche + Setup unter ProcessWire → Setup.
 */
class BsProcessEditorial extends Process implements ConfigurableModule {

	const BASE_PATH = 'editorial';
	const SESSION_KEY = 'bpe_auth';
	const ADMIN_PAGE_NAME = 'bs-process-editorial';

	public static function getModuleInfo(): array {
		return [
			'title' => 'Redaktion (bs-processEditorial)',
			'version' => 6,
			'summary' => 'Redaktionsoberfläche mit eigenem Login — Inhaltstypen aus dem PW-Datenmodell.',
			'author' => 'BezugsSysteme',
			'icon' => 'edit',
			'autoload' => true,
			'singular' => true,
			'requires' => 'ProcessWire>=3.0.173, PHP>=8.0.0',
			'page' => [
				'name' => self::ADMIN_PAGE_NAME,
				'parent' => 'setup',
				'title' => 'Redaktion',
			],
		];
	}

	public function __construct() {
		parent::__construct();
		$this->set('login_user', 'redaktion');
		$this->set('login_pass', 'redaktion');
		$this->set('base_path', self::BASE_PATH);
		$this->set('data_source', 'auto');
		$this->set('editorial_templates', ['ansprechpartner']);
		$this->set('editorial_modes', ['ansprechpartner' => 'list']);
		$this->set('setup_mvp', 0);
	}

	public function init(): void {
		$this->registerAutoloader();
		parent::init();
		$base = preg_quote(trim((string) $this->get('base_path') ?: self::BASE_PATH, '/'), '!');
		$this->addHook("!^/{$base}(?:/(.*))?/?$!", $this, 'handleRequest');
		$this->addHookAfter('Modules::saveConfig', $this, 'hookAfterSaveConfig');
	}

	public function ready(): void {
		$this->ensureSetupPage();
		$session = $this->wire()->session;
		if (!$session->getFor('bpe', 'nav_cleared_v5')) {
			$this->clearAdminNavCache();
			$session->setFor('bpe', 'nav_cleared_v5', 1);
		}
	}

	public function ___execute(): string {
		$modules = $this->wire()->modules;
		$input = $this->wire()->input;
		$configData = $modules->getModuleConfigData($this);

		if ($input->post('submit_save')) {
			$form = $this->buildSettingsForm($configData);
			$form->processInput($input->post);
			if (!$form->getErrors()) {
				$values = [];
				foreach ($form->getAll() as $field) {
					/** @var Inputfield $field */
					if ($field->name) {
						$values[$field->name] = $field->value;
					}
				}
				if (($values['login_pass'] ?? '') === '' && !empty($configData['login_pass'])) {
					$values['login_pass'] = $configData['login_pass'];
				}
				$values = $this->normalizeConfigValues($values, $configData);
				if (!empty($values['setup_mvp'])) {
					$installer = new \ProcessWire\BsProcessEditorial\Setup\MvpInstaller($this);
					foreach ($installer->install() as $msg) {
						$this->message($msg);
					}
					$values['setup_mvp'] = 0;
				}
				$modules->saveModuleConfigData($this, $values);
				foreach ($values as $key => $value) {
					$this->set($key, $value);
				}
				$this->clearAdminNavCache();
				$this->message('Einstellungen gespeichert.');
				$configData = $values;
			} else {
				$this->error('Bitte Eingaben prüfen.');
			}
		}

		$form = $this->buildSettingsForm($configData);
		$discovery = new \ProcessWire\BsProcessEditorial\Setup\TemplateDiscovery($this);
		$candidates = $discovery->candidates();
		$enabled = $this->editorialTemplateNames();

		$out = '<div class="bpe-admin">';
		$out .= '<p><a class="uk-button uk-button-primary" target="_blank" rel="noopener" href="'
			. $this->wire()->config->urls->root . trim($this->baseUrl(), '/') . '/">Redaktion öffnen</a></p>';

		$out .= '<h2>Entdeckte Inhaltstypen</h2>';
		$out .= '<p class="description">Kandidaten aus dieser Installation. '
			. '<strong>Datensätze</strong> = Listenansicht, <strong>Einzelseite</strong> = direktes Formular (z. B. Home). '
			. 'Freigabe und Modus unten festlegen.</p>';
		$out .= '<table class="AdminDataTable AdminDataList"><thead><tr>'
			. '<th>Template</th><th>Label</th><th>Seiten</th><th>Felder</th><th>Vorschlag</th><th>Status</th>'
			. '</tr></thead><tbody>';
		if (!$candidates) {
			$out .= '<tr><td colspan="6">Keine geeigneten Templates gefunden.</td></tr>';
		}
		foreach ($candidates as $item) {
			$active = in_array($item['name'], $enabled, true);
			$mode = $active ? $this->editorialMode($item['name']) : $item['suggestedMode'];
			$modeLabel = $mode === 'single' ? 'Einzelseite' : 'Datensätze (Liste)';
			$out .= '<tr>'
				. '<td><code>' . htmlspecialchars($item['name']) . '</code></td>'
				. '<td>' . htmlspecialchars($item['label']) . '</td>'
				. '<td>' . (int) $item['pages'] . '</td>'
				. '<td>' . (int) $item['fields'] . '</td>'
				. '<td>' . htmlspecialchars($item['kind']) . ($active ? ' → <strong>' . htmlspecialchars($modeLabel) . '</strong>' : '') . '</td>'
				. '<td>' . ($active ? '<strong>freigegeben</strong>' : '—') . '</td>'
				. '</tr>';
		}
		$out .= '</tbody></table>';

		$out .= '<h2>Einstellungen</h2>';
		$out .= $form->render();
		$out .= '</div>';
		return $out;
	}

	/**
	 * @return string[]
	 */
	public function editorialTemplateNames(): array {
		$raw = $this->get('editorial_templates');
		if (is_string($raw) && $raw !== '') {
			$raw = preg_split('/[\s,]+/', $raw) ?: [];
		}
		if (!is_array($raw)) {
			$raw = ['ansprechpartner'];
		}
		$names = [];
		foreach ($raw as $name) {
			$name = trim((string) $name);
			if ($name !== '' && !in_array($name, $names, true)) {
				$names[] = $name;
			}
		}
		return $names ?: ['ansprechpartner'];
	}

	/**
	 * Darstellung: list | single
	 */
	public function editorialMode(string $template): string {
		$modes = $this->get('editorial_modes');
		if (!is_array($modes)) {
			$modes = [];
		}
		if (isset($modes[$template]) && in_array($modes[$template], ['list', 'single'], true)) {
			return $modes[$template];
		}
		$discovery = new \ProcessWire\BsProcessEditorial\Setup\TemplateDiscovery($this);
		return $discovery->suggestMode($template);
	}

	/**
	 * @return array<string, string> template => list|single
	 */
	public function editorialModes(): array {
		$out = [];
		foreach ($this->editorialTemplateNames() as $name) {
			$out[$name] = $this->editorialMode($name);
		}
		return $out;
	}

	/**
	 * Formularwerte + mode__*-Felder zu speicherbarer Config normalisieren.
	 */
	protected function normalizeConfigValues(array $values, array $previous): array {
		$modes = is_array($previous['editorial_modes'] ?? null) ? $previous['editorial_modes'] : [];
		foreach ($values as $key => $value) {
			if (!str_starts_with((string) $key, 'mode__')) {
				continue;
			}
			$tpl = substr((string) $key, 6);
			if ($tpl !== '') {
				$modes[$tpl] = ((string) $value === 'single') ? 'single' : 'list';
			}
			unset($values[$key]);
		}
		$templates = $values['editorial_templates'] ?? [];
		if (!is_array($templates)) {
			$templates = $templates ? [(string) $templates] : [];
		}
		// Nur Modi für freigegebene Templates behalten; fehlende per Vorschlag füllen
		$discovery = new \ProcessWire\BsProcessEditorial\Setup\TemplateDiscovery($this);
		$cleanModes = [];
		foreach ($templates as $tpl) {
			$tpl = (string) $tpl;
			if ($tpl === '') {
				continue;
			}
			$cleanModes[$tpl] = $modes[$tpl] ?? $discovery->suggestMode($tpl);
		}
		$values['editorial_modes'] = $cleanModes;
		$values['editorial_templates'] = array_values(array_filter(array_map('strval', $templates)));
		return $values;
	}

	public function hookAfterSaveConfig(HookEvent $event): void {
		$className = $event->arguments(0);
		if ($className !== $this->className()) {
			return;
		}
		$data = $event->arguments(1);
		if (!is_array($data) || empty($data['setup_mvp'])) {
			return;
		}
		$installer = new \ProcessWire\BsProcessEditorial\Setup\MvpInstaller($this);
		foreach ($installer->install() as $msg) {
			$this->message($msg);
		}
		$data['setup_mvp'] = 0;
		$event->arguments(1, $data);
		$this->wire()->modules->saveModuleConfigData($this, $data);
	}

	protected function registerAutoloader(): void {
		spl_autoload_register(function (string $class): void {
			$prefix = 'ProcessWire\\BsProcessEditorial\\';
			if (!str_starts_with($class, $prefix)) {
				return;
			}
			$relative = str_replace('\\', '/', substr($class, strlen($prefix)));
			$file = __DIR__ . '/src/' . $relative . '.php';
			if (is_file($file)) {
				require_once $file;
			}
		});
	}

	public function handleRequest(HookEvent $event): string {
		$router = new \ProcessWire\BsProcessEditorial\Ui\Router($this);
		return $router->dispatch($event);
	}

	public function modulePath(): string {
		return __DIR__;
	}

	public function moduleUrl(): string {
		return $this->wire()->config->urls->siteModules . 'bs-processEditorial/';
	}

	public function baseUrl(): string {
		$base = trim((string) $this->get('base_path') ?: self::BASE_PATH, '/');
		$root = rtrim($this->wire()->config->urls->root, '/');
		return ($root === '' ? '' : $root) . '/' . $base . '/';
	}

	public function adapter(): \ProcessWire\BsProcessEditorial\Adapter\AdapterInterface {
		return \ProcessWire\BsProcessEditorial\Adapter\AdapterFactory::make($this);
	}

	public function ___install(): void {
		parent::___install();
	}

	public function ___uninstall(): void {
		parent::___uninstall();
	}

	protected function ensureSetupPage(): void {
		$pages = $this->wire()->pages;
		$existing = $pages->get('template=admin, name=' . self::ADMIN_PAGE_NAME);
		if ($existing->id) {
			if ((string) $existing->process !== $this->className()) {
				$existing->of(false);
				$existing->process = $this;
				$existing->save();
				$this->clearAdminNavCache();
			}
			return;
		}
		try {
			$this->installPage(self::ADMIN_PAGE_NAME, 'setup', 'Redaktion');
			$this->clearAdminNavCache();
		} catch (\Throwable $e) {
		}
	}

	protected function clearAdminNavCache(): void {
		$session = $this->wire()->session;
		foreach (['AdminThemeUikit', 'AdminThemeDefault', 'AdminThemeReno'] as $ns) {
			$session->removeFor($ns, 'prnav');
			$session->removeFor($ns, 'sidenav');
		}
	}

	protected function buildSettingsForm(array $data): InputfieldForm {
		/** @var InputfieldForm $form */
		$form = $this->wire()->modules->get('InputfieldForm');
		$form->attr('id', 'bpe-settings-form');
		$form->attr('method', 'post');
		$form->attr('action', './');

		foreach ($this->buildConfigFields($data) as $field) {
			$form->add($field);
		}

		/** @var InputfieldSubmit $submit */
		$submit = $this->wire()->modules->get('InputfieldSubmit');
		$submit->name = 'submit_save';
		$submit->value = 'Speichern';
		$form->add($submit);

		return $form;
	}

	/**
	 * @return Inputfield[]
	 */
	protected function buildConfigFields(array $data): array {
		$modules = $this->wire()->modules;
		$discovery = new \ProcessWire\BsProcessEditorial\Setup\TemplateDiscovery($this);
		$fields = [];

		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$f->name = 'base_path';
		$f->label = 'URL-Pfad der Redaktion';
		$f->description = 'Ohne führenden Slash, z. B. editorial → /editorial/';
		$f->value = $data['base_path'] ?? self::BASE_PATH;
		$fields[] = $f;

		/** @var InputfieldAsmSelect $f */
		$f = $modules->get('InputfieldAsmSelect');
		$f->name = 'editorial_templates';
		$f->label = 'Redaktionelle Templates (Freigabe)';
		$f->description = 'Was Redakteure sehen dürfen. Reihenfolge = Navigation.';
		$f->setAttribute('size', 10);
		$optionNames = [];
		foreach ($discovery->optionsForSelect() as $name => $label) {
			$f->addOption($name, $label);
			$optionNames[$name] = true;
		}
		$selected = $data['editorial_templates'] ?? ['ansprechpartner'];
		if (!is_array($selected)) {
			$selected = [$selected];
		}
		foreach ($selected as $name) {
			$name = (string) $name;
			if ($name !== '' && empty($optionNames[$name])) {
				$f->addOption($name, $name . ' (manuell)');
			}
		}
		$f->value = $selected;
		$fields[] = $f;

		// Darstellungsmodus pro freigegebenem Template
		$modes = is_array($data['editorial_modes'] ?? null) ? $data['editorial_modes'] : [];
		foreach ($selected as $name) {
			$name = (string) $name;
			if ($name === '') {
				continue;
			}
			/** @var InputfieldSelect $mf */
			$mf = $modules->get('InputfieldSelect');
			$mf->name = 'mode__' . $name;
			$mf->label = 'Darstellung: ' . $name;
			$mf->description = 'Datensätze = Liste + Anlegen. Einzelseite = direktes Formular (Homepage-Inhalte o. ä.).';
			$mf->addOption('list', 'Datensätze (Liste)');
			$mf->addOption('single', 'Einzelseite (ohne Liste)');
			$mf->value = $modes[$name] ?? $discovery->suggestMode($name);
			$fields[] = $mf;
		}

		/** @var InputfieldSelect $f */
		$f = $modules->get('InputfieldSelect');
		$f->name = 'data_source';
		$f->label = 'Datenquelle';
		$f->description = 'auto = ProcessWire, sobald mindestens ein freigegebenes Template existiert.';
		$f->addOption('auto', 'Automatisch');
		$f->addOption('mock', 'Mock (schema-mock.json)');
		$f->addOption('processwire', 'ProcessWire');
		$f->value = $data['data_source'] ?? 'auto';
		$fields[] = $f;

		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$f->name = 'login_user';
		$f->label = 'Demo-Benutzer (Mock-Login)';
		$f->value = $data['login_user'] ?? 'redaktion';
		$fields[] = $f;

		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$f->name = 'login_pass';
		$f->label = 'Demo-Passwort (Mock-Login)';
		$f->description = 'Leer lassen = bestehendes Passwort behalten.';
		$f->attr('type', 'password');
		$f->attr('autocomplete', 'new-password');
		$f->value = '';
		$fields[] = $f;

		/** @var InputfieldCheckbox $f */
		$f = $modules->get('InputfieldCheckbox');
		$f->name = 'setup_mvp';
		$f->label = 'Legacy: MVP-Testdatenmodell „einrichtung“ anlegen';
		$f->checked = false;
		$fields[] = $f;

		return $fields;
	}

	public static function getModuleConfigInputfields(array $data): InputfieldWrapper {
		/** @var BsProcessEditorial $module */
		$module = wire('modules')->getModule('BsProcessEditorial', ['noPermissionCheck' => true]);
		$wrapper = new InputfieldWrapper();

		/** @var InputfieldMarkup $info */
		$info = wire('modules')->get('InputfieldMarkup');
		$info->label = 'Hinweis';
		$setup = wire('pages')->get('template=admin, name=' . self::ADMIN_PAGE_NAME);
		$url = $setup->id ? $setup->url : wire('config')->urls->admin . 'setup/';
		$info->value = '<p>Einstellungen unter <a href="'
			. htmlspecialchars($url) . '"><strong>Setup → Redaktion</strong></a>.</p>';
		$wrapper->add($info);

		if ($module instanceof self) {
			foreach ($module->buildConfigFields($data) as $field) {
				$wrapper->add($field);
			}
		}
		return $wrapper;
	}
}
