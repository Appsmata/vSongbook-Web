<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	File: as-plugin/tag-cloud-widget/as-plugin.php
	Description: Initiates tag cloud widget plugin


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

/*
	Plugin Name: Tag Cloud Widget
	Plugin URI:
	Plugin Description: Provides a list of tags with size indicating popularity
	Plugin Version: 1.0.1
	Plugin Date: 2011-12-06
	Plugin Author: vSongBook
	Plugin Author URI: http://github.com/vsongbook
	Plugin License: GPLv2
	Plugin Minimum vSongBook Version: 1.4
	Plugin Update Check URI:
*/


if (!defined('AS_VERSION')) { // don't allow this page to be requested directly from browser
	header('Location: ../../');
	exit;
}


as_signup_plugin_module('widget', 'as-tag-cloud.php', 'as_tag_cloud', 'Tag Cloud');
