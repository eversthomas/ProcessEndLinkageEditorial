<?php namespace ProcessWire\ProcessEndLinkageEditorial\Adapter;

use ProcessWire\ProcessEndLinkageEditorial;

/**
 * Liefert den ProcessWire-Adapter für die Redaktion.
 */
class AdapterFactory {

	public static function make(ProcessEndLinkageEditorial $module): AdapterInterface {
		return new ProcessWireAdapter($module);
	}
}
