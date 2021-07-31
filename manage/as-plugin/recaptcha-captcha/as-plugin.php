<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	File: as-plugin/recaptcha-captcha/as-plugin.php
	Description: Initiates reCAPTCHA plugin


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
	Plugin Name: reCAPTCHA
	Plugin URI:
	Plugin Description: Provides support for reCAPTCHA captchas
	Plugin Version: 2.0
	Plugin Date: 2014-12-20
	Plugin Author: vSongBook
	Plugin Author URI: http://github.com/vsongbook
	Plugin License: GPLv2
	Plugin Minimum vSongBook Version: 1.7
	Plugin Update Check URI:
*/


if (!defined('AS_VERSION')) { // don't allow this page to be requested directly from browser
	header('Location: ../../');
	exit;
}


as_signup_plugin_module('captcha', 'as-recaptcha-captcha.php', 'as_recaptcha_captcha', 'reCAPTCHA');
