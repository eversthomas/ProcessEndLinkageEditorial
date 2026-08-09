<?php namespace ProcessWire\BsProcessEditorial\Adapter;

use ProcessWire\BsProcessEditorial;

/**
 * Wählt Mock- oder ProcessWire-Adapter.
 * auto = PW, sobald mindestens ein konfigurierter Inhaltstyp als Template existiert.
 */
class AdapterFactory {

	public static function make(BsProcessEditorial $module): AdapterInterface {
		$mode = (string) ($module->get('data_source') ?: 'auto');

		if ($mode === 'mock') {
			return new MockAdapter($module);
		}

		$pw = new ProcessWireAdapter($module);
		if ($mode === 'processwire') {
			return $pw;
		}

		foreach ($module->editorialTemplateNames() as $name) {
			if ($pw->supportsTemplate($name)) {
				return $pw;
			}
		}
		return new MockAdapter($module);
	}
}
