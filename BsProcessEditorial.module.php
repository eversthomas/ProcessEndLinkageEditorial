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
			'version' => 13,
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
		$this->set('data_source', 'processwire');
		$this->set('editorial_templates', []);
		$this->set('editorial_modes', []);
		$this->set('editorial_datatypes', []);
		$this->set('role_templates', []);
		$this->set('editorial_nav', '');
		$this->set('theme_accent', '#1f6b4a');
		$this->set('theme_rail_bg', '#1c1f1d');
		$this->set('theme_radius', '8');
		$this->set('theme_text_color', '#201e1d');
		$this->set('theme_muted_color', '#6b736e');
		$this->set('theme_spacing', 'normal');
		$this->set('brand_name', '');
		$this->set('brand_logo', '');
		$this->set('allow_demo_login', 0);
	}

	/** @var array<string, string> */
	public const DATATYPE_LABELS = [
		'daten' => 'Daten',
		'blog' => 'Blog',
		'news' => 'News',
		'termine' => 'Termine',
		'seiten' => 'Seiten',
	];

	public function init(): void {
		$this->registerAutoloader();
		parent::init();
		$base = preg_quote(trim((string) $this->get('base_path') ?: self::BASE_PATH, '/'), '!');
		$this->addHook("!^/{$base}(?:/(.*))?/?$!", $this, 'handleRequest');
		$this->addHookBefore('Modules::saveConfig', $this, 'hookBeforeSaveConfig');
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
		if (!$session->getFor('bpe', 'svg_logo_purged_v1')) {
			$this->purgeLegacyBrandLogoSvg();
			$session->setFor('bpe', 'svg_logo_purged_v1', 1);
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
				$values = $this->processBrandLogoUpload($values, $configData);
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

		$out = '<div class="bpe-admin">';
		$out .= $form->render();
		$out .= '</div>';
		return $out;
	}

	/** HTML: Link zur Redaktion + Discovery-Tabelle für Tab „Inhalte & Freigabe“. */
	protected function renderDiscoveryTableHtml(): string {
		$discovery = new \ProcessWire\BsProcessEditorial\Setup\TemplateDiscovery($this);
		$candidates = $discovery->candidates();
		$enabled = $this->editorialTemplateNames();

		$html = '<p><a class="uk-button uk-button-primary" target="_blank" rel="noopener" href="'
			. htmlspecialchars($this->wire()->config->urls->root . trim($this->baseUrl(), '/'), ENT_QUOTES, 'UTF-8')
			. '/">Redaktion öffnen</a></p>';
		$html .= '<p class="description">Kandidaten aus dieser Installation. '
			. '<strong>Datensätze</strong> = Listenansicht, <strong>Einzelseite</strong> = direktes Formular (z. B. Home). '
			. 'Freigabe, Darstellungsmodus und Daten-Art unten festlegen.</p>';
		$html .= '<table class="AdminDataTable AdminDataList"><thead><tr>'
			. '<th>Template</th><th>Label</th><th>Seiten</th><th>Felder</th>'
			. '<th>Ohne Adapter</th><th>Daten-Art</th><th>Vorschlag</th><th>Status</th>'
			. '</tr></thead><tbody>';
		if (!$candidates) {
			$html .= '<tr><td colspan="8">Keine geeigneten Templates gefunden.</td></tr>';
		}
		foreach ($candidates as $item) {
			$active = in_array($item['name'], $enabled, true);
			$mode = $active ? $this->editorialMode($item['name']) : $item['suggestedMode'];
			$modeLabel = $mode === 'single' ? 'Einzelseite' : 'Datensätze (Liste)';
			$datatype = $active ? $this->editorialDatatype($item['name']) : 'daten';
			$datatypeLabel = self::DATATYPE_LABELS[$datatype] ?? 'Daten';
			if ($active && $datatype !== 'daten') {
				$datatypeLabel .= ' <span class="detail">(Ansicht noch generisch)</span>';
			}
			$unsupported = $item['unsupportedFields'] ?? [];
			if ($unsupported) {
				$bits = [];
				foreach ($unsupported as $uf) {
					$bits[] = '<code>' . htmlspecialchars((string) ($uf['name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</code>'
						. ' <span class="detail">(' . htmlspecialchars((string) ($uf['type'] ?? ''), ENT_QUOTES, 'UTF-8') . ')</span>';
				}
				$unsupportedHtml = implode('<br>', $bits);
			} else {
				$unsupportedHtml = '—';
			}
			$html .= '<tr>'
				. '<td><code>' . htmlspecialchars($item['name']) . '</code></td>'
				. '<td>' . htmlspecialchars($item['label']) . '</td>'
				. '<td>' . (int) $item['pages'] . '</td>'
				. '<td>' . (int) $item['fields'] . '</td>'
				. '<td>' . $unsupportedHtml . '</td>'
				. '<td>' . ($active ? $datatypeLabel : '—') . '</td>'
				. '<td>' . htmlspecialchars($item['kind']) . ($active ? ' → <strong>' . htmlspecialchars($modeLabel) . '</strong>' : '') . '</td>'
				. '<td>' . ($active ? '<strong>freigegeben</strong>' : '—') . '</td>'
				. '</tr>';
		}
		$html .= '</tbody></table>';
		return $html;
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
			$raw = [];
		}
		$names = [];
		foreach ($raw as $name) {
			$name = trim((string) $name);
			if ($name !== '' && !in_array($name, $names, true)) {
				$names[] = $name;
			}
		}
		return $names;
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
	 * Daten-Art: daten | blog | news | termine | seiten
	 */
	public function editorialDatatype(string $template): string {
		$types = $this->get('editorial_datatypes');
		if (!is_array($types)) {
			$types = [];
		}
		$raw = (string) ($types[$template] ?? 'daten');
		return array_key_exists($raw, self::DATATYPE_LABELS) ? $raw : 'daten';
	}

	/**
	 * @return array<string, string> template => datatype key
	 */
	public function editorialDatatypes(): array {
		$out = [];
		foreach ($this->editorialTemplateNames() as $name) {
			$out[$name] = $this->editorialDatatype($name);
		}
		return $out;
	}

	/** Ob für diese Daten-Art das Broadsheet-/Daten-Rendering greift. */
	public function hasDatatypeView(string $template): bool {
		return $this->editorialDatatype($template) === 'daten';
	}

	/**
	 * Formularwerte + mode__/datatype__/role__*-Felder zu speicherbarer Config normalisieren.
	 */
	protected function normalizeConfigValues(array $values, array $previous): array {
		$modes = is_array($previous['editorial_modes'] ?? null) ? $previous['editorial_modes'] : [];
		$datatypes = is_array($previous['editorial_datatypes'] ?? null) ? $previous['editorial_datatypes'] : [];
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
			if (str_starts_with($key, 'datatype__')) {
				$tpl = substr($key, 10);
				if ($tpl !== '') {
					$raw = (string) $value;
					$datatypes[$tpl] = array_key_exists($raw, self::DATATYPE_LABELS) ? $raw : 'daten';
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
		$cleanDatatypes = [];
		foreach ($templates as $tpl) {
			if ($tpl === '') {
				continue;
			}
			$cleanModes[$tpl] = $modes[$tpl] ?? $discovery->suggestMode($tpl);
			$dt = (string) ($datatypes[$tpl] ?? 'daten');
			$cleanDatatypes[$tpl] = array_key_exists($dt, self::DATATYPE_LABELS) ? $dt : 'daten';
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
		$values['editorial_datatypes'] = $cleanDatatypes;
		$values['editorial_templates'] = $templates;
		$values['role_templates'] = $cleanRoles;
		$values['allow_demo_login'] = !empty($values['allow_demo_login']) ? 1 : 0;

		$navRaw = $values['editorial_nav'] ?? '';
		$navConfig = new \ProcessWire\BsProcessEditorial\Setup\NavConfig($this);
		try {
			if (is_string($navRaw) && trim($navRaw) !== '') {
				$decoded = json_decode($navRaw, true);
				$tree = is_array($decoded) ? $decoded : [];
			} elseif (is_array($navRaw)) {
				$tree = $navRaw;
			} else {
				$tree = [];
			}
			// Freigabe ist Quelle der Template-Mitgliedschaft; Nav nur Struktur-Overlay
			$tree = $navConfig->reconcileMissingTemplates($tree, $templates);
			$tree = $navConfig->parseAndValidate($tree, $templates);
			$values['editorial_nav'] = json_encode($tree, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
		$values['theme_text_color'] = $this->normalizeHexColor(
			(string) ($values['theme_text_color'] ?? ''),
			(string) ($previous['theme_text_color'] ?? '#201e1d')
		);
		$values['theme_muted_color'] = $this->normalizeHexColor(
			(string) ($values['theme_muted_color'] ?? ''),
			(string) ($previous['theme_muted_color'] ?? '#6b736e')
		);
		$radius = trim((string) ($values['theme_radius'] ?? '8'));
		$values['theme_radius'] = preg_match('/^\d+(\.\d+)?$/', $radius) ? $radius : '8';
		$spacing = (string) ($values['theme_spacing'] ?? 'normal');
		$values['theme_spacing'] = in_array($spacing, ['compact', 'normal', 'generous'], true) ? $spacing : 'normal';

		$values['brand_name'] = trim((string) ($values['brand_name'] ?? ''));
		// Logo-Dateiname bleibt in processBrandLogoUpload erhalten/aktualisiert
		if (!array_key_exists('brand_logo', $values)) {
			$values['brand_logo'] = (string) ($previous['brand_logo'] ?? '');
		}

		return $values;
	}

	/**
	 * Kundenlogo nach site/assets/bs-processEditorial/brand/ speichern.
	 */
	/**
	 * Entfernt Legacy-SVG-Logos (Datei + Config).
	 * Aufruf einmalig pro Session aus ready() (Flag svg_logo_purged_v1).
	 */
	protected function purgeLegacyBrandLogoSvg(): void {
		$logo = basename((string) $this->get('brand_logo'));
		$configHadSvg = $logo !== '' && strtolower(pathinfo($logo, PATHINFO_EXTENSION)) === 'svg';
		$removedFile = false;

		if ($configHadSvg) {
			$this->deleteBrandLogoFile($logo);
			$removedFile = true;
		}

		$dir = $this->brandLogoDir();
		if (is_dir($dir)) {
			foreach (glob($dir . '*.svg') ?: [] as $path) {
				if (is_file($path)) {
					@unlink($path);
					$removedFile = true;
				}
			}
		}

		if (!$configHadSvg && !$removedFile) {
			return;
		}

		if ($configHadSvg) {
			$this->set('brand_logo', '');
			$modules = $this->wire()->modules;
			$data = $modules->getModuleConfigData($this);
			if (!is_array($data)) {
				$data = [];
			}
			if (($data['brand_logo'] ?? '') !== '') {
				$data['brand_logo'] = '';
				$modules->saveModuleConfigData($this, $data);
			}
		}
	}

	protected function processBrandLogoUpload(array $values, array $previous): array {
		$input = $this->wire()->input;
		$existing = (string) ($previous['brand_logo'] ?? '');

		if ($input->post('brand_logo_clear')) {
			$this->deleteBrandLogoFile($existing);
			$values['brand_logo'] = '';
			return $values;
		}

		// Legacy-SVG: Datei und Config-Wert entfernen (Stored-XSS).
		if ($existing !== '' && strtolower(pathinfo(basename($existing), PATHINFO_EXTENSION)) === 'svg') {
			$this->deleteBrandLogoFile($existing);
			$existing = '';
			$values['brand_logo'] = '';
		}

		$file = $_FILES['brand_logo_file'] ?? null;
		if (!is_array($file) || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
			if (!array_key_exists('brand_logo', $values)) {
				$values['brand_logo'] = $existing;
			}
			return $values;
		}

		$ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
		$allowed = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
		if (!in_array($ext, $allowed, true)) {
			$this->error('Logo: erlaubt sind PNG, JPG, GIF und WebP.');
			$values['brand_logo'] = $existing;
			return $values;
		}
		if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
			$this->error('Logo darf maximal 2 MB groß sein.');
			$values['brand_logo'] = $existing;
			return $values;
		}

		// Content-Check: Endung allein reicht nicht (z. B. umbenanntes SVG).
		$imageInfo = @getimagesize($file['tmp_name']);
		$typeToExt = [
			IMAGETYPE_PNG => 'png',
			IMAGETYPE_JPEG => 'jpg',
			IMAGETYPE_GIF => 'gif',
			IMAGETYPE_WEBP => 'webp',
		];
		$detected = is_array($imageInfo) ? ($imageInfo[2] ?? null) : null;
		if ($detected === null || !isset($typeToExt[$detected])) {
			$this->error('Logo: Datei ist kein gültiges PNG-, JPG-, GIF- oder WebP-Bild.');
			$values['brand_logo'] = $existing;
			return $values;
		}
		$canonicalExt = $typeToExt[$detected];
		$extNormalized = $ext === 'jpeg' ? 'jpg' : $ext;
		if ($extNormalized !== $canonicalExt) {
			$this->error('Logo: Dateiendung stimmt nicht mit dem Bildinhalt überein.');
			$values['brand_logo'] = $existing;
			return $values;
		}

		$dir = $this->brandLogoDir();
		if (!is_dir($dir) && !wireMkdir($dir, true)) {
			$this->error('Logo-Verzeichnis konnte nicht angelegt werden.');
			$values['brand_logo'] = $existing;
			return $values;
		}

		$filename = 'logo-' . time() . '.' . $canonicalExt;
		$target = $dir . $filename;
		if (!@move_uploaded_file($file['tmp_name'], $target)) {
			$this->error('Logo konnte nicht gespeichert werden.');
			$values['brand_logo'] = $existing;
			return $values;
		}

		$this->deleteBrandLogoFile($existing);
		$values['brand_logo'] = $filename;
		return $values;
	}

	protected function brandLogoDir(): string {
		return rtrim($this->wire()->config->paths->assets, '/') . '/bs-processEditorial/brand/';
	}

	protected function deleteBrandLogoFile(string $filename): void {
		$filename = basename($filename);
		if ($filename === '') {
			return;
		}
		$path = $this->brandLogoDir() . $filename;
		if (is_file($path)) {
			@unlink($path);
		}
	}

	/** Anzeigename für Kunden-Branding (Fallback: Redaktion). */
	public function brandName(): string {
		$name = trim((string) $this->get('brand_name'));
		return $name !== '' ? $name : 'Redaktion';
	}

	/** Öffentliche URL zum Kundenlogo oder null. */
	public function brandLogoUrl(): ?string {
		$file = basename((string) $this->get('brand_logo'));
		if ($file === '') {
			return null;
		}
		// SVG wird in ready() entfernt; hier nur noch als Absicherung.
		if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'svg') {
			return null;
		}
		$path = $this->brandLogoDir() . $file;
		if (!is_file($path)) {
			return null;
		}
		return rtrim($this->wire()->config->urls->assets, '/') . '/bs-processEditorial/brand/' . rawurlencode($file);
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

	public function hookBeforeSaveConfig(HookEvent $event): void {
		$className = $event->arguments(0);
		if ($className !== $this->className()) {
			return;
		}
		$data = $event->arguments(1);
		if (!is_array($data)) {
			return;
		}
		$previous = $this->wire()->modules->getModuleConfigData($this);
		$data = $this->processBrandLogoUpload($data, is_array($previous) ? $previous : []);
		$event->arguments(1, $data);
	}

	protected function registerAutoloader(): void {
		static $registered = false;
		if ($registered) {
			return;
		}
		$registered = true;
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

	/**
	 * Öffentliche Modul-URL — Ordnername aus dem realen Pfad (Linux case-sensitiv).
	 */
	public function moduleUrl(): string {
		$urls = $this->wire()->config->urls;
		// PW kennt Modul-URLs über paths/urls des Modulverzeichnisses
		$path = $this->modulePath();
		$siteModulesPath = rtrim($this->wire()->config->paths->siteModules, '/');
		if (str_starts_with($path, $siteModulesPath)) {
			$relative = substr($path, strlen($siteModulesPath));
			return rtrim($urls->siteModules, '/') . str_replace('\\', '/', $relative) . '/';
		}
		return rtrim($urls->siteModules, '/') . '/' . basename($path) . '/';
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
		$this->registerAutoloader();
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
		$form->attr('enctype', 'multipart/form-data');

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

		// —— 1. Inhalte & Freigabe ——
		/** @var InputfieldFieldset $fsContent */
		$fsContent = $modules->get('InputfieldFieldset');
		$fsContent->label = 'Freigabe & Darstellung';
		$fsContent->description = 'Welche Templates Redakteure sehen und wie sie dargestellt werden.';
		$fsContent->collapsed = Inputfield::collapsedNo;

		/** @var InputfieldAsmSelect $f */
		$f = $modules->get('InputfieldAsmSelect');
		$f->name = 'editorial_templates';
		$f->label = 'Redaktionelle Templates (Freigabe)';
		$f->description = 'Was Redakteure sehen dürfen. Neu freigegebene Typen erscheinen automatisch unter „Inhalte“ (Default-Gruppe); Reihenfolge/Gruppen im Menü-Builder.';
		$f->setAttribute('size', 10);
		$optionNames = [];
		foreach ($discovery->optionsForSelect() as $name => $label) {
			$f->addOption($name, $label);
			$optionNames[$name] = true;
		}
		$selected = $data['editorial_templates'] ?? [];
		if (!is_array($selected)) {
			$selected = $selected ? [(string) $selected] : [];
		}
		foreach ($selected as $name) {
			$name = (string) $name;
			if ($name !== '' && empty($optionNames[$name])) {
				$f->addOption($name, $name . ' (manuell)');
			}
		}
		$f->value = $selected;
		$fsContent->add($f);

		$modes = is_array($data['editorial_modes'] ?? null) ? $data['editorial_modes'] : [];
		$datatypes = is_array($data['editorial_datatypes'] ?? null) ? $data['editorial_datatypes'] : [];
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
			$fsContent->add($mf);

			/** @var InputfieldSelect $df */
			$df = $modules->get('InputfieldSelect');
			$df->name = 'datatype__' . $name;
			$df->label = 'Daten-Art: ' . $name;
			$df->description = 'Steuert die redaktionelle Ansicht. Nur „Daten“ hat derzeit eine spezialisierte Oberfläche; andere Arten nutzen vorerst die generische Liste/Formular.';
			foreach (self::DATATYPE_LABELS as $key => $label) {
				$df->addOption($key, $label);
			}
			$dt = (string) ($datatypes[$name] ?? 'daten');
			$df->value = array_key_exists($dt, self::DATATYPE_LABELS) ? $dt : 'daten';
			$fsContent->add($df);
		}

		// —— 2. Navigation ——
		$navConfig = new \ProcessWire\BsProcessEditorial\Setup\NavConfig($this);
		$prevTemplates = $this->get('editorial_templates');
		$prevModes = $this->get('editorial_modes');
		$prevNav = $this->get('editorial_nav');
		$this->set('editorial_templates', $selected);
		$this->set('editorial_modes', $modes);
		$existingNav = $data['editorial_nav'] ?? '';
		if (is_array($existingNav)) {
			$this->set('editorial_nav', json_encode($existingNav, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		} elseif (is_string($existingNav)) {
			$this->set('editorial_nav', $existingNav);
		}
		$navTree = $navConfig->tree();
		$navJson = $navConfig->toJson();
		$this->set('editorial_templates', $prevTemplates);
		$this->set('editorial_modes', $prevModes);
		$this->set('editorial_nav', $prevNav);

		$tplLabels = [];
		foreach ($selected as $name) {
			$name = (string) $name;
			if ($name === '') {
				continue;
			}
			$tpl = $this->wire()->templates->get($name);
			$label = $tpl && $tpl->id ? trim((string) $tpl->get('label')) : '';
			$tplLabels[$name] = $label !== '' ? $label . ' (' . $name . ')' : $name;
		}

		$this->wire()->config->scripts->add($this->moduleUrl() . 'assets/js/nav-builder.js?v=8');

		/** @var InputfieldFieldset $fsNav */
		$fsNav = $modules->get('InputfieldFieldset');
		$fsNav->label = 'Navigation';
		$fsNav->collapsed = Inputfield::collapsedNo;

		$builder = new \ProcessWire\BsProcessEditorial\Setup\NavBuilder($this);
		/** @var InputfieldMarkup $f */
		$f = $modules->get('InputfieldMarkup');
		$f->name = 'editorial_nav_builder';
		$f->label = 'Menühierarchie';
		$f->description = 'Visuell Sections, Gruppen, Reihenfolge und Icons. Neu freigegebene Templates erscheinen automatisch in der Default-Gruppe.';
		$f->value = $builder->renderMarkup($navTree, $tplLabels);
		$fsNav->add($f);

		/** @var InputfieldTextarea $f */
		$f = $modules->get('InputfieldTextarea');
		$f->name = 'editorial_nav';
		$f->label = 'Menühierarchie (JSON)';
		$f->description = 'Power-User für Struktur (Reihenfolge, Gruppen, Icons). Template-Mitgliedschaft folgt der Freigabe oben — fehlende werden ergänzt, nicht freigegebene beim Speichern entfernt.';
		$f->rows = 12;
		$f->collapsed = Inputfield::collapsedYes;
		$f->value = $navJson;
		$fsNav->add($f);

		// —— 3. Design/Branding ——
		/** @var InputfieldFieldset $fsDesign */
		$fsDesign = $modules->get('InputfieldFieldset');
		$fsDesign->label = 'Design / Branding';
		$fsDesign->collapsed = Inputfield::collapsedNo;

		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$f->name = 'brand_name';
		$f->label = 'Kunden-Branding: Firmenname';
		$f->description = 'Erscheint im Header und in der Rail der Redaktion. Leer = „Redaktion“.';
		$f->value = $data['brand_name'] ?? '';
		$fsDesign->add($f);

		$logoFile = basename((string) ($data['brand_logo'] ?? ''));
		$logoUrl = null;
		if ($logoFile !== '' && strtolower(pathinfo($logoFile, PATHINFO_EXTENSION)) !== 'svg') {
			$logoPath = $this->brandLogoDir() . $logoFile;
			if (is_file($logoPath)) {
				$logoUrl = rtrim($this->wire()->config->urls->assets, '/')
					. '/bs-processEditorial/brand/' . rawurlencode($logoFile);
			}
		}
		/** @var InputfieldMarkup $f */
		$f = $modules->get('InputfieldMarkup');
		$f->name = 'brand_logo_ui';
		$f->label = 'Kunden-Branding: Logo';
		$f->description = 'PNG, JPG, GIF oder WebP, max. 2 MB. Wird im Header und in der Rail angezeigt.';
		$html = '';
		if ($logoUrl) {
			$html .= '<p class="bpe-admin-logo-preview"><img src="'
				. htmlspecialchars($logoUrl) . '" alt="Logo" style="max-height:64px;max-width:220px;background:#fff;padding:6px;border:1px solid #ddd;border-radius:4px;"></p>';
			$html .= '<p><label><input type="checkbox" name="brand_logo_clear" value="1"> Logo entfernen</label></p>';
		}
		$html .= '<input type="file" name="brand_logo_file" accept="image/png,image/jpeg,image/gif,image/webp">';
		$f->value = $html;
		$fsDesign->add($f);

		foreach ([
			['theme_accent', 'Theme: Akzentfarbe', '#1f6b4a'],
			['theme_rail_bg', 'Theme: Rail-Hintergrund', '#1c1f1d'],
			['theme_text_color', 'Theme: Textfarbe', '#201e1d'],
			['theme_muted_color', 'Theme: Gedämpfte Textfarbe', '#6b736e'],
		] as [$name, $label, $default]) {
			/** @var InputfieldText $f */
			$f = $modules->get('InputfieldText');
			$f->name = $name;
			$f->label = $label;
			$f->description = 'Farbwähler (HTML5). Alternativ Hex-Wert eingeben.';
			$f->attr('type', 'color');
			$f->attr('style', 'width: 3.5rem; height: 2.5rem; padding: 0.15rem; cursor: pointer;');
			$f->value = $this->normalizeHexColor((string) ($data[$name] ?? $default), $default);
			$fsDesign->add($f);
		}

		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$f->name = 'theme_radius';
		$f->label = 'Theme: Radius (px)';
		$f->value = $data['theme_radius'] ?? '8';
		$fsDesign->add($f);

		/** @var InputfieldSelect $f */
		$f = $modules->get('InputfieldSelect');
		$f->name = 'theme_spacing';
		$f->label = 'Theme: Abstände';
		$f->description = 'Wirkt auf die Basis-Abstände der Redaktionsoberfläche (kompakt / normal / großzügig).';
		$f->addOption('compact', 'Kompakt');
		$f->addOption('normal', 'Normal');
		$f->addOption('generous', 'Großzügig');
		$spacing = (string) ($data['theme_spacing'] ?? 'normal');
		$f->value = in_array($spacing, ['compact', 'normal', 'generous'], true) ? $spacing : 'normal';
		$fsDesign->add($f);

		// —— 4. Zugriff/Rollen ——
		/** @var InputfieldFieldset $fsRoles */
		$fsRoles = $modules->get('InputfieldFieldset');
		$fsRoles->label = 'Zugriff / Rollen';
		$fsRoles->collapsed = Inputfield::collapsedNo;

		$roleMap = is_array($data['role_templates'] ?? null) ? $data['role_templates'] : [];
		$tplOptions = [];
		foreach ($selected as $name) {
			$name = (string) $name;
			if ($name !== '') {
				$tplOptions[$name] = $name;
			}
		}
		$roleFieldCount = 0;
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
			$fsRoles->add($rf);
			$roleFieldCount++;
		}

		// —— 5. Erweitert ——
		/** @var InputfieldFieldset $fsAdvanced */
		$fsAdvanced = $modules->get('InputfieldFieldset');
		$fsAdvanced->label = 'Erweitert';
		$fsAdvanced->collapsed = Inputfield::collapsedNo;

		/** @var InputfieldCheckbox $f */
		$f = $modules->get('InputfieldCheckbox');
		$f->name = 'allow_demo_login';
		$f->label = 'Demo-Login erlauben (ohne PW-User)';
		$f->description = 'Nur für lokale Tests. Produktiv auslassen.';
		$f->checked = !empty($data['allow_demo_login']);
		$fsAdvanced->add($f);

		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$f->name = 'login_user';
		$f->label = 'Demo-Benutzername';
		$f->value = $data['login_user'] ?? 'redaktion';
		$f->showIf = 'allow_demo_login=1';
		$fsAdvanced->add($f);

		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$f->name = 'login_pass';
		$f->label = 'Demo-Passwort';
		$f->description = 'Leer lassen = bestehendes Passwort behalten.';
		$f->attr('type', 'password');
		$f->attr('autocomplete', 'new-password');
		$f->value = '';
		$f->showIf = 'allow_demo_login=1';
		$fsAdvanced->add($f);

		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$f->name = 'base_path';
		$f->label = 'URL-Pfad der Redaktion';
		$f->description = 'Ohne führenden Slash, z. B. editorial → /editorial/';
		$f->value = $data['base_path'] ?? self::BASE_PATH;
		$fsAdvanced->add($f);

		/** @var InputfieldWrapper $tabInhalte */
		$tabInhalte = $modules->get('InputfieldWrapper');
		$tabInhalte->attr('class', 'WireTab');
		$tabInhalte->attr('title', 'Inhalte & Freigabe');
		$tabInhalte->attr('id', 'bpe-tab-inhalte');
		/** @var InputfieldMarkup $f */
		$f = $modules->get('InputfieldMarkup');
		$f->label = 'Entdeckte Inhaltstypen';
		$f->value = $this->renderDiscoveryTableHtml();
		$tabInhalte->add($f);
		$tabInhalte->add($fsContent);

		/** @var InputfieldWrapper $tabNav */
		$tabNav = $modules->get('InputfieldWrapper');
		$tabNav->attr('class', 'WireTab');
		$tabNav->attr('title', 'Navigation');
		$tabNav->attr('id', 'bpe-tab-navigation');
		$tabNav->add($fsNav);

		/** @var InputfieldWrapper $tabDesign */
		$tabDesign = $modules->get('InputfieldWrapper');
		$tabDesign->attr('class', 'WireTab');
		$tabDesign->attr('title', 'Design & Branding');
		$tabDesign->attr('id', 'bpe-tab-design');
		$tabDesign->add($fsDesign);

		/** @var InputfieldWrapper $tabSystem */
		$tabSystem = $modules->get('InputfieldWrapper');
		$tabSystem->attr('class', 'WireTab');
		$tabSystem->attr('title', 'Zugriff & System');
		$tabSystem->attr('id', 'bpe-tab-system');
		/** @var InputfieldMarkup $f */
		$f = $modules->get('InputfieldMarkup');
		$f->label = 'Zugang';
		$f->value = '<p class="description">Redakteure brauchen die Permission <code>editorial-access</code> '
			. '(Rolle <code>editorial</code> wird automatisch angelegt). Superuser haben immer Zugang. '
			. 'Login unter <code>/' . htmlspecialchars(trim($this->baseUrl(), '/'), ENT_QUOTES, 'UTF-8')
			. '/</code> ist vom Admin-Login getrennt.</p>';
		$tabSystem->add($f);
		if ($roleFieldCount > 0) {
			$tabSystem->add($fsRoles);
		}
		$tabSystem->add($fsAdvanced);

		return [$tabInhalte, $tabNav, $tabDesign, $tabSystem];
	}

	public static function getModuleConfigInputfields(array $data): InputfieldWrapper {
		$wrapper = new InputfieldWrapper();

		/** @var InputfieldMarkup $info */
		$info = wire('modules')->get('InputfieldMarkup');
		$info->label = 'Konfiguration';
		$setup = wire('pages')->get('template=admin, name=' . self::ADMIN_PAGE_NAME);
		$url = $setup->id ? $setup->url : wire('config')->urls->admin . 'setup/';
		$info->value = '<p>Alle Redaktions-Einstellungen (Templates, Menü, Branding, Theme) '
			. 'werden unter <a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8')
			. '"><strong>Setup → Redaktion</strong></a> gepflegt. '
			. 'Dieser Modul-Bildschirm enthält keine funktionalen Felder mehr.</p>';
		$wrapper->add($info);

		return $wrapper;
	}
}
