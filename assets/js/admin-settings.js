/**
 * Setup → Redaktion: WireTabs für die Einstellungsformular-Tabs.
 */
jQuery(document).ready(function () {
	var $form = jQuery('#bpe-settings-form');
	if (!$form.length) return;
	var $tabs = $form.find('.Inputfields > li.WireTab');
	if ($tabs.length < 2) return;
	$form.WireTabs({
		items: $tabs,
		id: 'BpeSettingsTabs',
		rememberTabs: true
	});
});
