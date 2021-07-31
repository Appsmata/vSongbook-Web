<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Controller for admin page listing plugins and showing their options


	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	More about this online
*/

if (!defined('AS_VERSION')) { // don't allow this page to be requested directly from browser
	header('Location: ../../../');
	exit;
}

require_once AS_INCLUDE_DIR . 'app/admin.php';


// Check admin privileges

if (!as_admin_check_privileges($as_content))
	return $as_content;

// Prepare content for theme

$as_content = as_content_prepare();

$as_content['title'] = as_lang_html('admin/admin_title') . ' - ' . as_lang_html('admin/plugins_title');

$as_content['error'] = as_admin_page_error();

$as_content['script_rel'][] = 'as-content/as-admin.js?' . AS_VERSION;


$pluginManager = new APS_Plugin_PluginManager();
$pluginManager->cleanRemovedPlugins();

$enabledPlugins = $pluginManager->getEnabledPlugins();
$fileSystemPlugins = $pluginManager->getFilesystemPlugins();

$pluginHashes = $pluginManager->getHashesForPlugins($fileSystemPlugins);

$showpluginforms = true;
if (as_is_http_post()) {
	if (!as_check_form_security_code('admin/plugins', as_post_text('as_form_security_code'))) {
		$as_content['error'] = as_lang_html('misc/form_security_reload');
		$showpluginforms = false;
	} else {
		if (as_clicked('dosave')) {
			$enabledPluginHashes = as_post_text('enabled_plugins_hashes');
			$enabledPluginHashesArray = explode(';', $enabledPluginHashes);
			$pluginDirectories = array_keys(array_intersect($pluginHashes, $enabledPluginHashesArray));
			$pluginManager->setEnabledPlugins($pluginDirectories);

			as_redirect('admin/plugins');
		}
	}
}

// Map modules with options to their containing plugins

$pluginoptionmodules = array();

$tables = as_db_list_tables();
$moduletypes = as_list_module_types();

foreach ($moduletypes as $type) {
	$modules = as_list_modules($type);

	foreach ($modules as $name) {
		$module = as_load_module($type, $name);

		if (method_exists($module, 'admin_form')) {
			$info = as_get_module_info($type, $name);
			$dir = rtrim($info['directory'], '/');
			$pluginoptionmodules[$dir][] = array(
				'type' => $type,
				'name' => $name,
			);
		}
	}
}

foreach ($moduletypes as $type) {
	$modules = as_load_modules_with($type, 'init_queries');

	foreach ($modules as $name => $module) {
		$queries = $module->init_queries($tables);

		if (!empty($queries)) {
			if (as_is_http_post())
				as_redirect('install');

			else {
				$as_content['error'] = strtr(as_lang_html('admin/module_x_database_init'), array(
					'^1' => as_html($name),
					'^2' => as_html($type),
					'^3' => '<a href="' . as_path_html('install') . '">',
					'^4' => '</a>',
				));
			}
		}
	}
}


if (!empty($fileSystemPlugins)) {
	$metadataUtil = new APS_Util_Metadata();
	$sortedPluginFiles = array();

	foreach ($fileSystemPlugins as $pluginDirectory) {
		$pluginDirectoryPath = AS_PLUGIN_DIR . $pluginDirectory;
		$metadata = $metadataUtil->fetchFromAddonPath($pluginDirectoryPath);
		if (empty($metadata)) {
			$pluginFile = $pluginDirectoryPath . '/as-plugin.php';

			// limit plugin parsing to first 8kB
			$contents = file_get_contents($pluginFile, false, null, 0, 8192);
			$metadata = as_addon_metadata($contents, 'Plugin');
		}

		$metadata['name'] = isset($metadata['name']) && !empty($metadata['name'])
			? as_html($metadata['name'])
			: as_lang_html('admin/unnamed_plugin');
		$sortedPluginFiles[$pluginDirectory] = $metadata;
	}

	as_sort_by($sortedPluginFiles, 'name');

	$pluginIndex = -1;
	foreach ($sortedPluginFiles as $pluginDirectory => $metadata) {
		$pluginIndex++;

		$pluginDirectoryPath = AS_PLUGIN_DIR . $pluginDirectory;
		$hash = $pluginHashes[$pluginDirectory];
		$showthisform = $showpluginforms && (as_get('show') == $hash);

		$namehtml = $metadata['name'];

		if (isset($metadata['uri']) && strlen($metadata['uri']))
			$namehtml = '<a href="' . as_html($metadata['uri']) . '">' . $namehtml . '</a>';

		$namehtml = '<b>' . $namehtml . '</b>';

		$metaver = isset($metadata['version']) && strlen($metadata['version']);
		if ($metaver)
			$namehtml .= ' v' . as_html($metadata['version']);

		if (isset($metadata['author']) && strlen($metadata['author'])) {
			$authorhtml = as_html($metadata['author']);

			if (isset($metadata['author_uri']) && strlen($metadata['author_uri']))
				$authorhtml = '<a href="' . as_html($metadata['author_uri']) . '">' . $authorhtml . '</a>';

			$authorhtml = as_lang_html_sub('main/by_x', $authorhtml);

		} else
			$authorhtml = '';

		if ($metaver && isset($metadata['update_uri']) && strlen($metadata['update_uri'])) {
			$elementid = 'version_check_' . md5($pluginDirectory);

			$updatehtml = '(<span id="' . $elementid . '">...</span>)';

			$as_content['script_onloads'][] = array(
				"as_version_check(" . as_js($metadata['update_uri']) . ", " . as_js($metadata['version'], true) . ", " . as_js($elementid) . ", false);"
			);
		}
		else
			$updatehtml = '';

		if (isset($metadata['description']))
			$deschtml = as_html($metadata['description']);
		else
			$deschtml = '';

		if (isset($pluginoptionmodules[$pluginDirectoryPath]) && !$showthisform) {
			$deschtml .= (strlen($deschtml) ? ' - ' : '') . '<a href="' . as_admin_plugin_options_path($pluginDirectory) . '">' .
				as_lang_html('admin/options') . '</a>';
		}

		$allowDisable = isset($metadata['load_order']) && $metadata['load_order'] === 'after_db_init';
		$beforeDbInit = isset($metadata['load_order']) && $metadata['load_order'] === 'before_db_init';
		$enabled = $beforeDbInit || !$allowDisable || in_array($pluginDirectory, $enabledPlugins);

		$pluginhtml = $namehtml . ' ' . $authorhtml . ' ' . $updatehtml . '<br>';
		$pluginhtml .= $deschtml . (strlen($deschtml) > 0 ? '<br>' : '');
		$pluginhtml .= '<small style="color:#666">' . as_html($pluginDirectoryPath) . '/</small>';

		if (as_as_version_below(@$metadata['min_aps']))
			$pluginhtml = '<s style="color:#999">'.$pluginhtml.'</s><br><span style="color:#f00">'.
				as_lang_html_sub('admin/requires_aps_version', as_html($metadata['min_aps'])).'</span>';

		elseif (as_php_version_below(@$metadata['min_php']))
			$pluginhtml = '<s style="color:#999">'.$pluginhtml.'</s><br><span style="color:#f00">'.
				as_lang_html_sub('admin/requires_php_version', as_html($metadata['min_php'])).'</span>';

		$as_content['form_plugin_'.$pluginIndex] = array(
			'tags' => 'id="'.as_html($hash).'"',
			'style' => 'tall',
			'fields' => array(
				array(
					'type' => 'checkbox',
					'label' => as_lang_html('admin/enabled'),
					'value' => $enabled,
					'tags' => sprintf('id="plugin_enabled_%s"%s', $hash, $allowDisable ? '' : ' disabled'),
				),
				array(
					'type' => 'custom',
					'html' => $pluginhtml,
				),
			),
		);

		if ($showthisform && isset($pluginoptionmodules[$pluginDirectoryPath])) {
			foreach ($pluginoptionmodules[$pluginDirectoryPath] as $pluginoptionmodule) {
				$type = $pluginoptionmodule['type'];
				$name = $pluginoptionmodule['name'];

				$module = as_load_module($type, $name);

				$form = $module->admin_form($as_content);

				if (!isset($form['tags']))
					$form['tags'] = 'method="post" action="' . as_admin_plugin_options_path($pluginDirectory) . '"';

				if (!isset($form['style']))
					$form['style'] = 'tall';

				$form['boxed'] = true;

				$form['hidden']['as_form_security_code'] = as_get_form_security_code('admin/plugins');

				$as_content['form_plugin_options'] = $form;
			}
		}
	}
}

$as_content['navigation']['sub'] = as_admin_sub_navigation();

$as_content['form'] = array(
	'tags' => 'method="post" action="' . as_self_html() . '" name="plugins_form" onsubmit="as_get_enabled_plugins_hashes(); return true;"',

	'style' => 'wide',

	'buttons' => array(
		'dosave' => array(
			'tags' => 'name="dosave"',
			'label' => as_lang_html('admin/save_options_button'),
		),
	),

	'hidden' => array(
		'as_form_security_code' => as_get_form_security_code('admin/plugins'),
		'enabled_plugins_hashes' => '',
	),
);


return $as_content;
