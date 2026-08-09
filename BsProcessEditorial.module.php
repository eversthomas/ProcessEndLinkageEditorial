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
			'version' => 8,
			'summary' => 'Filigrane Redaktions-UX mit Menühierarchie, Dashboard, TinyMCE und Publish.',
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
		$this->set('role_templates', []);
		$this->set('editorial_nav', '');
		$this->set('theme_accent', '#1f6b4a');
		$this->set('theme_rail_bg', '#1c1f1d');
		$this->set('theme_radius', '8');
		$this->set('allow_demo_login', 0);
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
		$auth = new \ProcessWire\BsProcessEditorial\Auth\EditorialAuth($this);
		$auth->ensureAccessInfrastructure();
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

		$out .= '<h2>Zugang &amp; UX</h2>';
		$out .= '<p class="description">Redakteure brauchen die Permission <code>editorial-access</code> '
			. '(Rolle <code>editorial</code> wird automatisch angelegt). Superuser haben immer Zugang. '
			. 'Login unter <code>/editorial/</code> ist vom Admin-Login getrennt. '
			. 'Menühierarchie und Theme-Farben unten konfigurieren — die Shell zeigt Dashboard, Hybrid-Baum und Details-Sidebar.</p>';

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
	 * Formularwerte + mode__/role__*-Felder zu speicherbarer Config normalisieren.
	 */
	protected function normalizeConfigValues(array $values, array $previous): array {
		$modes = is_array($previous['editorial_modes'] ?? null) ? $previous['editorial_modes'] : [];
		$roleTemplates = is_array($previous['role_templates'] ?? null) ? $previous['role_templates'] : [];

		foreach ($values as $key => $value) {
			$key = (string) $key;
			if (str_starts_with($key, 'mode__')) {
				$tpl = substr($key, 6);
				if ($tpl !== '') {
					$modes[$tpl] = ((string) $value === 'single') ? 'single' : 'list';
				}
				unset($values[$key]);
				continue;
			}
			if (str_starts_with($key, 'role__')) {
				$role = substr($key, 6);
				if ($role !== '') {
					if (!is_array($value)) {
						$value = $value ? [(string) $value] : [];
					}
					$roleTemplates[$role] = array_values(array_filter(array_map('strval', $value)));
				}
				unset($values[$key]);
			}
		}

		$templates = $values['editorial_templates'] ?? [];
		if (!is_array($templates)) {
			$templates = $templates ? [(string) $templates] : [];
		}
		$templates = array_values(array_filter(array_map('strval', $templates)));

		$discovery = new \ProcessWire\BsProcessEditorial\Setup\TemplateDiscovery($this);
		$cleanModes = [];
		foreach ($templates as $tpl) {
			if ($tpl === '') {
				continue;
			}
			$cleanModes[$tpl] = $modes[$tpl] ?? $discovery->suggestMode($tpl);
		}

		$cleanRoles = [];
		foreach ($roleTemplates as $role => $tpls) {
			$role = (string) $role;
			if ($role === '' || $role === 'guest' || $role === 'superuser') {
				continue;
			}
			if (!is_array($tpls)) {
				continue;
			}
			$cleanRoles[$role] = array_values(array_filter(
				array_map('strval', $tpls),
				fn(string $t) => $t !== '' && in_array($t, $templates, true)
			));
		}

		$values['editorial_modes'] = $cleanModes;
		$values['editorial_templates'] = $templates;
		$values['role_templates'] = $cleanRoles;
		$values['allow_demo_login'] = !empty($values['allow_demo_login']) ? 1 : 0;

		$navRaw = $values['editorial_nav'] ?? '';
		$navConfig = new \ProcessWire\BsProcessEditorial\Setup\NavConfig($this);
		try {
			if (is_string($navRaw) && trim($navRaw) !== '') {
				$tree = $navConfig->parseAndValidate($navRaw, $templates);
				$values['editorial_nav'] = json_encode($tree, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			} elseif (is_array($navRaw) && $navRaw !== []) {
				$tree = $navConfig->parseAndValidate($navRaw, $templates);
				$values['editorial_nav'] = json_encode($tree, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			} else {
				// Leer = Runtime-Migration aus freigegebenen Templates
				$values['editorial_nav'] = '';
			}
		} catch (\InvalidArgumentException $e) {
			$values['editorial_nav'] = $previous['editorial_nav'] ?? '';
			$this->error($e->getMessage());
		}

		$values['theme_accent'] = $this->normalizeHexColor(
			(string) ($values['theme_accent'] ?? ''),
			(string) ($previous['theme_accent'] ?? '#1f6b4a')
		);
		$values['theme_rail_bg'] = $this->normalizeHexColor(
			(string) ($values['theme_rail_bg'] ?? ''),
			(string) ($previous['theme_rail_bg'] ?? '#1c1f1d')
		);
		$radius = trim((string) ($values['theme_radius'] ?? '8'));
		$values['theme_radius'] = preg_match('/^\d+(\.\d+)?$/', $radius) ? $radius : '8';

		return $values;
	}

	/**
	 * Templates, die der aktuelle Redaktions-User sehen darf.
	 *
	 * @return string[]
	 */
	public function allowedTemplatesForUser(?\ProcessWire\User $user): array {
		$access = new \ProcessWire\BsProcessEditorial\Auth\TemplateAccess($this);
		return $access->allowedTemplates($user);
	}

	/** Hex für HTML-Farbpicker normalisieren (#rgb → #rrggbb). */
	protected function normalizeHexColor(string $value, string $fallback): string {
		$value = trim($value);
		if (preg_match('/^#([0-9a-fA-F]{3})$/', $value, $m)) {
			$s = $m[1];
			return '#' . $s[0] . $s[0] . $s[1] . $s[1] . $s[2] . $s[2];
		}
		if (preg_match('/^#([0-9a-fA-F]{6})$/', $value)) {
			return strtolower($value);
		}
		if (preg_match('/^#([0-9a-fA-F]{8})$/', $value)) {
			return strtolower('#' . substr($value, 1, 6));
		}
		$fallback = trim($fallback);
		if (preg_match('/^#([0-9a-fA-F]{6})$/', $fallback)) {
			return strtolower($fallback);
		}
		return '#1f6b4a';
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
		$auth = new \ProcessWire\BsProcessEditorial\Auth\EditorialAuth($this);
		$auth->ensureAccessInfrastructure();
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

		$navConfig = new \ProcessWire\BsProcessEditorial\Setup\NavConfig($this);
		/** @var InputfieldTextarea $f */
		$f = $modules->get('InputfieldTextarea');
		$f->name = 'editorial_nav';
		$f->label = 'Menühierarchie (JSON)';
		$f->description = 'Sections/Groups/Templates mit Lucide-Icon-Namen. Leer lassen und speichern mit Default-Migration, '
			. 'oder JSON pflegen. Typen: dashboard, section, group, template. Icons u. a.: '
			. implode(', ', array_keys(\ProcessWire\BsProcessEditorial\Setup\NavConfig::iconChoices())) . '.';
		$f->rows = 16;
		$existingNav = $data['editorial_nav'] ?? '';
		if (is_array($existingNav)) {
			$f->value = json_encode($existingNav, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		} elseif (is_string($existingNav) && trim($existingNav) !== '') {
			$decoded = json_decode($existingNav, true);
			$f->value = is_array($decoded)
				? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
				: $existingNav;
		} else {
			$prevTemplates = $this->get('editorial_templates');
			$prevModes = $this->get('editorial_modes');
			$this->set('editorial_templates', $selected);
			$this->set('editorial_modes', $modes);
			$f->value = $navConfig->toJson();
			$this->set('editorial_templates', $prevTemplates);
			$this->set('editorial_modes', $prevModes);
		}
		$fields[] = $f;

		// Kein natives InputfieldColor in PW-Core → HTML5 type=color
		foreach ([
			['theme_accent', 'Theme: Akzentfarbe', '#1f6b4a'],
			['theme_rail_bg', 'Theme: Rail-Hintergrund', '#1c1f1d'],
		] as [$name, $label, $default]) {
			/** @var InputfieldText $f */
			$f = $modules->get('InputfieldText');
			$f->name = $name;
			$f->label = $label;
			$f->description = 'Farbwähler (HTML5). Alternativ Hex-Wert eingeben.';
			$f->attr('type', 'color');
			$f->attr('style', 'width: 3.5rem; height: 2.5rem; padding: 0.15rem; cursor: pointer;');
			$f->value = $this->normalizeHexColor((string) ($data[$name] ?? $default), $default);
			$fields[] = $f;
		}

		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$f->name = 'theme_radius';
		$f->label = 'Theme: Radius (px)';
		$f->value = $data['theme_radius'] ?? '8';
		$fields[] = $f;

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

		// Rolle → Inhaltstypen
		$roleMap = is_array($data['role_templates'] ?? null) ? $data['role_templates'] : [];
		$tplOptions = [];
		foreach ($selected as $name) {
			$name = (string) $name;
			if ($name !== '') {
				$tplOptions[$name] = $name;
			}
		}
		foreach ($this->wire()->roles as $role) {
			if (in_array($role->name, ['guest', 'superuser'], true)) {
				continue;
			}
			/** @var InputfieldAsmSelect $rf */
			$rf = $modules->get('InputfieldAsmSelect');
			$rf->name = 'role__' . $role->name;
			$rf->label = 'Rolle „' . $role->name . '“ sieht';
			$rf->description = 'Leer = alle freigegebenen Inhaltstypen (solange die Rolle editorial-access hat).';
			$rf->setAttribute('size', 6);
			foreach ($tplOptions as $name => $label) {
				$rf->addOption($name, $label);
			}
			$rf->value = $roleMap[$role->name] ?? [];
			$fields[] = $rf;
		}

		/** @var InputfieldCheckbox $f */
		$f = $modules->get('InputfieldCheckbox');
		$f->name = 'allow_demo_login';
		$f->label = 'Demo-Login erlauben (ohne PW-User)';
		$f->description = 'Nur für lokale Tests. Produktiv auslassen.';
		$f->checked = !empty($data['allow_demo_login']);
		$fields[] = $f;

		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$f->name = 'login_user';
		$f->label = 'Demo-Benutzername';
		$f->value = $data['login_user'] ?? 'redaktion';
		$f->showIf = 'allow_demo_login=1';
		$fields[] = $f;

		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$f->name = 'login_pass';
		$f->label = 'Demo-Passwort';
		$f->description = 'Leer lassen = bestehendes Passwort behalten.';
		$f->attr('type', 'password');
		$f->attr('autocomplete', 'new-password');
		$f->value = '';
		$f->showIf = 'allow_demo_login=1';
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
