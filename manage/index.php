<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	File: index.php
	Description: A stub that only sets up the APS root and includes as-index.php


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

// Set base path here so this works with symbolic links for multiple installations

define('AS_BASE_DIR', dirname(empty($_SERVER['SCRIPT_FILENAME']) ? __FILE__ : $_SERVER['SCRIPT_FILENAME']) . '/');

require 'as-include/as-index.php';
