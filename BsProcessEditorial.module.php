<?php namespace ProcessWire;

/**
 * BsProcessEditorial — generische Redaktionsoberfläche für ProcessWire.
 *
 * Eigene Frontend-App unter /editorial/ (nicht PW-Admin).
 */
class BsProcessEditorial extends WireData implements Module, ConfigurableModule {

	const BASE_PATH = 'editorial';
	const SESSION_KEY = 'bpe_auth';

	public static function getModuleInfo(): array {
		return [
			'title' => 'bs-processEditorial',
			'version' => 3,
			'summary' => 'Redaktionsoberfläche mit eigenem Login — baut sich aus dem Datenmodell auf.',
			'author' => 'BezugsSysteme',
			'icon' => 'edit',
			'autoload' => true,
			'singular' => true,
			'requires' => 'ProcessWire>=3.0.173, PHP>=8.0.0',
		];
	}

	public function __construct() {
		parent::__construct();
		$this->set('login_user', 'redaktion');
		$this->set('login_pass', 'redaktion');
		$this->set('base_path', self::BASE_PATH);
		$this->set('data_source', 'auto');
		$this->set('editorial_templates', ['ansprechpartner']);
		$this->set('setup_mvp', 0);
	}

	public function init(): void {
		$this->registerAutoloader();
		$base = preg_quote(trim((string) $this->get('base_path') ?: self::BASE_PATH, '/'), '!');
		$this->addHook("!^/{$base}(?:/(.*))?/?$!", $this, 'handleRequest');
		$this->addHookAfter('Modules::saveConfig', $this, 'hookAfterSaveConfig');
	}

	/**
	 * Konfigurierte Template-Namen für die Redaktion (Reihenfolge = Nav-Reihenfolge).
	 *
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
		// optional: Testdatenmodell über Modulkonfiguration
	}

	public static function getModuleConfigInputfields(array $data): InputfieldWrapper {
		$modules = wire('modules');
		$templates = wire('templates');
		$wrapper = new InputfieldWrapper();

		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$f->name = 'base_path';
		$f->label = 'URL-Pfad der Redaktion';
		$f->description = 'Ohne führenden Slash, z. B. editorial → /editorial/';
		$f->value = $data['base_path'] ?? self::BASE_PATH;
		$wrapper->add($f);

		/** @var InputfieldAsmSelect $f */
		$f = $modules->get('InputfieldAsmSelect');
		$f->name = 'editorial_templates';
		$f->label = 'Redaktionelle Templates';
		$f->description = 'Diese Inhaltstypen erscheinen in der Redaktions-Navigation. Reihenfolge = Anzeige-Reihenfolge.';
		$f->setAttribute('size', 8);
		$skip = ['admin', 'user', 'role', 'permission', 'language', 'basic-page'];
		foreach ($templates as $tpl) {
			/** @var Template $tpl */
			if ($tpl->flags & Template::flagSystem) {
				continue;
			}
			if (in_array($tpl->name, $skip, true)) {
				continue;
			}
			$label = trim((string) $tpl->get('label'));
			$f->addOption($tpl->name, ($label !== '' ? $label : $tpl->name) . ' (' . $tpl->name . ')');
		}
		$f->value = $data['editorial_templates'] ?? ['ansprechpartner'];
		$wrapper->add($f);

		/** @var InputfieldSelect $f */
		$f = $modules->get('InputfieldSelect');
		$f->name = 'data_source';
		$f->label = 'Datenquelle';
		$f->description = 'auto = ProcessWire, sobald mindestens ein konfiguriertes Template existiert, sonst Mock.';
		$f->addOption('auto', 'Automatisch');
		$f->addOption('mock', 'Mock (schema-mock.json)');
		$f->addOption('processwire', 'ProcessWire');
		$f->value = $data['data_source'] ?? 'auto';
		$wrapper->add($f);

		/** @var InputfieldCheckbox $f */
		$f = $modules->get('InputfieldCheckbox');
		$f->name = 'setup_mvp';
		$f->label = 'Legacy: MVP-Testdatenmodell „einrichtung“ anlegen';
		$f->description = 'Optional. Für den aktuellen Workflow mit Ansprechpartner nicht nötig.';
		$f->checked = false;
		$wrapper->add($f);

		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$f->name = 'login_user';
		$f->label = 'Demo-Benutzer (Mock-Login)';
		$f->value = $data['login_user'] ?? 'redaktion';
		$wrapper->add($f);

		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$f->name = 'login_pass';
		$f->label = 'Demo-Passwort (Mock-Login)';
		$f->attr('type', 'password');
		$f->value = $data['login_pass'] ?? 'redaktion';
		$wrapper->add($f);

		return $wrapper;
	}
}
