<?php namespace ProcessWire\BsProcessEditorial\Adapter;

use ProcessWire\BsProcessEditorial;

/**
 * Wählt Mock- oder ProcessWire-Adapter (auto: PW wenn Template existiert).
 */
class AdapterFactory {

	public static function make(BsProcessEditorial $module): AdapterInterface {
		$mode = (string) ($module->get('data_source') ?: 'auto');

		if ($mode === 'mock') {
			return new MockAdapter();
		}

		$pw = new ProcessWireAdapter($module);
		if ($mode === 'processwire') {
			return $pw;
		}

		// auto
		if ($pw->supportsTemplate('einrichtung')) {
			return $pw;
		}
		return new MockAdapter();
	}
}
