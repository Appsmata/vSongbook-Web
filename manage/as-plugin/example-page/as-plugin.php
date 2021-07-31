<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	File: as-plugin/example-page/as-plugin.php
	Description: Initiates example page plugin


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
	Plugin Name: Example Page
	Plugin URI:
	Plugin Description: Example of page plugin
	Plugin Version: 1.1
	Plugin Date: 2011-12-06
	Plugin Author: vSongBook
	Plugin Author URI: http://github.com/vsongbook
	Plugin License: GPLv2
	Plugin Minimum vSongBook Version: 1.5
	Plugin Update Check URI:
*/


if (!defined('AS_VERSION')) { // don't allow this page to be requested directly from browser
	header('Location: ../../');
	exit;
}


as_signup_plugin_module('page', 'as-example-page.php', 'as_example_page', 'Example Page');
as_signup_plugin_phrases('as-example-lang-*.php', 'example_page');
