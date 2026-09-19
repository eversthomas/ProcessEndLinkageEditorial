<?php namespace ProcessWire\ProcessEndLinkageEditorial\Auth;

use ProcessWire\ProcessEndLinkageEditorial;
use ProcessWire\User;

/**
 * Grobe Rechte: Rolle → sichtbare Inhaltstypen (Schnittmenge mit globaler Freigabe).
 */
class TemplateAccess {

	protected ProcessEndLinkageEditorial $module;

	public function __construct(ProcessEndLinkageEditorial $module) {
		$this->module = $module;
	}

	/**
	 * Templates, die der User in der Redaktion sehen darf.
	 *
	 * @return string[]
	 */
	public function allowedTemplates(?User $user): array {
		$all = $this->module->editorialTemplateNames();
		if ($user === null) {
			// Demo-Login: alle freigegebenen
			return $all;
		}
		if ($user->isSuperuser()) {
			return $all;
		}

		$map = $this->roleTemplateMap();
		if ($map === []) {
			// Keine Rollen-Zuordnung konfiguriert → kein Zugriff (fail-closed).
			// Explizit in Setup → Zugriff & System zuordnen, sonst sieht der User nichts.
			return [];
		}

		$allowed = [];
		foreach ($user->roles as $role) {
			if ($role->name === 'guest' || $role->name === 'superuser') {
				continue;
			}
			$tpls = $map[$role->name] ?? null;
			if (!is_array($tpls)) {
				continue;
			}
			foreach ($tpls as $tpl) {
				$tpl = (string) $tpl;
				if ($tpl !== '' && in_array($tpl, $all, true)) {
					$allowed[] = $tpl;
				}
			}
		}

		// Keine Übereinstimmung für die Rollen dieses Users → kein Zugriff (fail-closed),
		// nicht mehr "dann eben alle". Unklare Zuordnung darf nie zu vollem Zugriff führen.
		return array_values(array_unique($allowed));
	}

	/**
	 * Erlaubte Aktionen ('create', 'edit', 'publish') eines Users für ein Template.
	 * Fehlt für eine Rolle/Template-Kombination eine explizite Einstellung, gelten alle drei
	 * Aktionen als erlaubt — bestehende Installationen (vor Einführung dieser Feinsteuerung)
	 * verlieren dadurch keinen heutigen Zugriff. Sichtbarkeit (allowedTemplates()) bleibt die
	 * äußere, fail-closed Schranke: ohne Sichtbarkeit gibt es nie eine Aktion.
	 *
	 * @return string[]
	 */
	public function allowedActions(?User $user, string $template): array {
		if (!in_array($template, $this->allowedTemplates($user), true)) {
			return [];
		}
		if ($user === null || $user->isSuperuser()) {
			return ['create', 'edit', 'publish'];
		}

		$actionMap = $this->roleTemplateActionMap();
		$actions = [];
		foreach ($user->roles as $role) {
			if ($role->name === 'guest' || $role->name === 'superuser') {
				continue;
			}
			if (!in_array($template, $this->roleTemplateMap()[$role->name] ?? [], true)) {
				continue;
			}
			$explicit = $actionMap[$role->name][$template] ?? null;
			$actions = array_merge($actions, is_array($explicit) ? $explicit : ['create', 'edit', 'publish']);
		}
		return array_values(array_unique($actions));
	}

	/**
	 * @return array<string, array<string, string[]>> roleName => templateName => actions
	 */
	protected function roleTemplateActionMap(): array {
		$raw = $this->module->get('role_template_actions');
		if (!is_array($raw)) {
			return [];
		}
		$out = [];
		foreach ($raw as $role => $templates) {
			$role = (string) $role;
			if ($role === '' || !is_array($templates)) {
				continue;
			}
			foreach ($templates as $tpl => $actions) {
				$tpl = (string) $tpl;
				if ($tpl === '' || !is_array($actions)) {
					continue;
				}
				$out[$role][$tpl] = array_values(array_intersect(
					array_map('strval', $actions),
					['create', 'edit', 'publish']
				));
			}
		}
		return $out;
	}

	/**
	 * @return array<string, string[]> roleName => template names
	 */
	public function roleTemplateMap(): array {
		$raw = $this->module->get('role_templates');
		if (!is_array($raw)) {
			return [];
		}
		$out = [];
		foreach ($raw as $role => $templates) {
			$role = (string) $role;
			if ($role === '' || $role === 'guest' || $role === 'superuser') {
				continue;
			}
			if (!is_array($templates)) {
				continue;
			}
			$out[$role] = array_values(array_filter(array_map('strval', $templates)));
		}
		return $out;
	}
}
