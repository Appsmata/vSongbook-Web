<?php

/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	File: as-include/APS/Plugin/PluginManager.php
	Description: Keeps track of the installed plugins


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

class APS_Plugin_PluginManager
{
	const PLUGIN_DELIMITER = ';';
	const OPT_ENABLED_PLUGINS = 'enabled_plugins';

	private $loadBeforeDbInit = array();
	private $loadAfterDbInit = array();

	public function readAllPluginMetadatas()
	{
		$pluginDirectories = $this->getFilesystemPlugins(true);

		foreach ($pluginDirectories as $pluginDirectory) {
			$pluginFile = $pluginDirectory . 'as-plugin.php';

			if (!file_exists($pluginFile)) {
				continue;
			}

			$metadataUtil = new APS_Util_Metadata();
			$metadata = $metadataUtil->fetchFromAddonPath($pluginDirectory);
			if (empty($metadata)) {
				// limit plugin parsing to first 8kB
				$contents = file_get_contents($pluginFile, false, null, 0, 8192);
				$metadata = as_addon_metadata($contents, 'Plugin', true);
			}

			// skip plugin which requires a later version of APS
			if (isset($metadata['min_aps']) && as_as_version_below($metadata['min_aps'])) {
				continue;
			}
			// skip plugin which requires a later version of PHP
			if (isset($metadata['min_php']) && as_php_version_below($metadata['min_php'])) {
				continue;
			}

			$pluginInfoKey = basename($pluginDirectory);
			$pluginInfo = array(
				'pluginfile' => $pluginFile,
				'directory' => $pluginDirectory,
				'urltoroot' => substr($pluginDirectory, strlen(AS_BASE_DIR)),
			);

			if (isset($metadata['load_order'])) {
				switch ($metadata['load_order']) {
					case 'after_db_init':
						$this->loadAfterDbInit[$pluginInfoKey] = $pluginInfo;
						break;
					case 'before_db_init':
						$this->loadBeforeDbInit[$pluginInfoKey] = $pluginInfo;
						break;
					default:
				}
			} else {
				$this->loadBeforeDbInit[$pluginInfoKey] = $pluginInfo;
			}
		}
	}

	private function loadPlugins($pluginInfos)
	{
		global $as_plugin_directory, $as_plugin_urltoroot;

		foreach ($pluginInfos as $pluginInfo) {
			$as_plugin_directory = $pluginInfo['directory'];
			$as_plugin_urltoroot = $pluginInfo['urltoroot'];

			require_once $pluginInfo['pluginfile'];
		}

		$as_plugin_directory = null;
		$as_plugin_urltoroot = null;
	}

	public function loadPluginsBeforeDbInit()
	{
		$this->loadPlugins($this->loadBeforeDbInit);
	}

	public function loadPluginsAfterDbInit()
	{
		$enabledPlugins = $this->getEnabledPlugins(false);
		$enabledForAfterDbInit = array();

		foreach ($enabledPlugins as $enabledPluginDirectory) {
			if (isset($this->loadAfterDbInit[$enabledPluginDirectory])) {
				$enabledForAfterDbInit[$enabledPluginDirectory] = $this->loadAfterDbInit[$enabledPluginDirectory];
			}
		}

		$this->loadPlugins($enabledForAfterDbInit);
	}

	public function getEnabledPlugins($fullPath = false)
	{
		$pluginDirectories = $this->getEnabledPluginsOption();

		if ($fullPath) {
			foreach ($pluginDirectories as $key => &$pluginDirectory) {
				$pluginDirectory = AS_PLUGIN_DIR . $pluginDirectory . '/';
			}
		}

		return $pluginDirectories;
	}

	public function setEnabledPlugins($array)
	{
		$this->setEnabledPluginsOption($array);
	}

	public function getFilesystemPlugins($fullPath = false)
	{
		$result = array();

		$fileSystemPluginFiles = glob(AS_PLUGIN_DIR . '*/as-plugin.php');

		foreach ($fileSystemPluginFiles as $pluginFile) {
			$directory = dirname($pluginFile) . '/';

			if (!$fullPath) {
				$directory = basename($directory);
			}
			$result[] = $directory;
		}

		return $result;
	}

	public function getHashesForPlugins($pluginDirectories)
	{
		$result = array();

		foreach ($pluginDirectories as $pluginDirectory) {
			$result[$pluginDirectory] = md5($pluginDirectory);
		}

		return $result;
	}

	private function getEnabledPluginsOption()
	{
		return explode(self::PLUGIN_DELIMITER, as_opt(self::OPT_ENABLED_PLUGINS));
	}

	private function setEnabledPluginsOption($array)
	{
		as_opt(self::OPT_ENABLED_PLUGINS, implode(self::PLUGIN_DELIMITER, $array));
	}

	public function cleanRemovedPlugins()
	{
		$finalEnabledPlugins = array_intersect(
			$this->getFilesystemPlugins(),
			$this->getEnabledPlugins()
		);

		$this->setEnabledPluginsOption($finalEnabledPlugins);
	}
}
