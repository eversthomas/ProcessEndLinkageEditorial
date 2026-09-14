<?php namespace ProcessWire\BsProcessEditorial\Auth;

use ProcessWire\BsProcessEditorial;
use ProcessWire\User;

/**
 * Grobe Rechte: Rolle → sichtbare Inhaltstypen (Schnittmenge mit globaler Freigabe).
 */
class TemplateAccess {

	protected BsProcessEditorial $module;

	public function __construct(BsProcessEditorial $module) {
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
