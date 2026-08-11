<?php namespace ProcessWire\BsProcessEditorial\Adapter;

use ProcessWire\BsProcessEditorial;

/**
 * Liefert den ProcessWire-Adapter für die Redaktion.
 */
class AdapterFactory {

	public static function make(BsProcessEditorial $module): AdapterInterface {
		return new ProcessWireAdapter($module);
	}
}
