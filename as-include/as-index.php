<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: The Grand Central of APS - most requests come through here


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

// Try our best to set base path here just in case it wasn't set in index.php (pre version 1.0.1)

if (!defined('AS_BASE_DIR')) {
	define('AS_BASE_DIR', dirname(empty($_SERVER['SCRIPT_FILENAME']) ? dirname(__FILE__) : $_SERVER['SCRIPT_FILENAME']) . '/');
}


// If this is an special non-page request, branch off here

if (isset($_POST['as']) && $_POST['as'] == 'ajax') {
	require 'as-ajax.php';
}

elseif (isset($_GET['as']) && $_GET['as'] == 'image') {
	require 'as-image.php';
}

elseif (isset($_GET['as']) && $_GET['as'] == 'blob') {
	require 'as-blob.php';
}

else {
	// Otherwise, load the APS base file which sets up a bunch of crucial stuff
	$as_autoconnect = false;
	require 'as-base.php';

	/**
	 * Determine the request and root of the installation, and the requested start position used by many pages.
	 *
	 * Apache and Nginx behave slightly differently:
	 *   Apache as-rewrite unescapes characters, converts `+` to ` `, cuts off at `#` or `&`
	 *   Nginx as-rewrite unescapes characters, retains `+`, contains true path
	 */
	function as_index_set_request()
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

		$relativedepth = 0;

		if (isset($_GET['as-rewrite'])) { // URLs rewritten by .htaccess or Nginx
			$urlformat = AS_URL_FORMAT_NEAT;
			$as_rewrite = strtr(as_gpc_to_string($_GET['as-rewrite']), '+', ' '); // strtr required by Nginx
			$requestparts = explode('/', $as_rewrite);
			unset($_GET['as-rewrite']);

			if (!empty($_SERVER['REQUEST_URI'])) { // workaround for the fact that Apache unescapes characters while rewriting
				$origpath = $_SERVER['REQUEST_URI'];
				$_GET = array();

				$songpos = strpos($origpath, '?');
				if (is_numeric($songpos)) {
					$params = explode('&', substr($origpath, $songpos + 1));

					foreach ($params as $param) {
						if (preg_match('/^([^\=]*)(\=(.*))?$/', $param, $matches)) {
							$argument = strtr(urldecode($matches[1]), '.', '_'); // simulate PHP's $_GET behavior
							$_GET[$argument] = as_string_to_gpc(urldecode(@$matches[3]));
						}
					}

					$origpath = substr($origpath, 0, $songpos);
				}

				// Generally we assume that $_GET['as-rewrite'] has the right path depth, but this won't be the case if there's
				// a & or # somewhere in the middle of the path, due to Apache unescaping. So we make a special case for that.
				// If 'REQUEST_URI' and 'as-rewrite' already match (as on Nginx), we can skip this.
				$normalizedpath = urldecode($origpath);
				if (substr($normalizedpath, -strlen($as_rewrite)) !== $as_rewrite) {
					$keepparts = count($requestparts);
					$requestparts = explode('/', urldecode($origpath)); // new request calculated from $_SERVER['REQUEST_URI']

					// loop forwards so we capture all parts
					for ($part = 0, $max = count($requestparts); $part < $max; $part++) {
						if (is_numeric(strpos($requestparts[$part], '&')) || is_numeric(strpos($requestparts[$part], '#'))) {
							$keepparts += count($requestparts) - $part - 1; // this is how many parts remain
							break;
						}
					}

					$requestparts = array_slice($requestparts, -$keepparts); // remove any irrelevant parts from the beginning
				}
			}

			$relativedepth = count($requestparts);
		} elseif (isset($_GET['as'])) {
			if (strpos($_GET['as'], '/') === false) {
				$urlformat = (empty($_SERVER['REQUEST_URI']) || strpos($_SERVER['REQUEST_URI'], '/index.php') !== false)
					? AS_URL_FORMAT_SAFEST : AS_URL_FORMAT_PARAMS;
				$requestparts = array(as_gpc_to_string($_GET['as']));

				for ($part = 1; $part < 10; $part++) {
					if (isset($_GET['as_' . $part])) {
						$requestparts[] = as_gpc_to_string($_GET['as_' . $part]);
						unset($_GET['as_' . $part]);
					}
				}
			} else {
				$urlformat = AS_URL_FORMAT_PARAM;
				$requestparts = explode('/', as_gpc_to_string($_GET['as']));
			}

			unset($_GET['as']);
		} else {
			$normalizedpath = strtr($_SERVER['PHP_SELF'], '+', ' '); // seems necessary, and plus does not work with this scheme
			$indexpath = '/index.php/';
			$indexpos = strpos($normalizedpath, $indexpath);

			if (!empty($_SERVER['REQUEST_URI'])) { // workaround for the fact that Apache unescapes characters
				$origpath = $_SERVER['REQUEST_URI'];
				$songpos = strpos($origpath, '?');
				if ($songpos !== false) {
					$origpath = substr($origpath, 0, $songpos);
				}

				$normalizedpath = urldecode($origpath);
				$indexpos = strpos($normalizedpath, $indexpath);
			}

			if (is_numeric($indexpos)) {
				$urlformat = AS_URL_FORMAT_INDEX;
				$requestparts = explode('/', substr($normalizedpath, $indexpos + strlen($indexpath)));
				$relativedepth = 1 + count($requestparts);
			} else {
				$urlformat = null; // at home page so can't identify path type
				$requestparts = array();
			}
		}

		foreach ($requestparts as $part => $requestpart) { // remove any blank parts
			if (!strlen($requestpart))
				unset($requestparts[$part]);
		}

		reset($requestparts);
		$key = key($requestparts);

		$requestkey = isset($requestparts[$key]) ? $requestparts[$key] : '';
		$replacement = array_search($requestkey, as_get_request_map());
		if ($replacement !== false)
			$requestparts[$key] = $replacement;

		as_set_request(
			implode('/', $requestparts),
			($relativedepth > 1 ? str_repeat('../', $relativedepth - 1) : './'),
			$urlformat
		);
	}

	as_index_set_request();


	// Branch off to appropriate file for further handling

	$requestlower = strtolower(as_request());

	if ($requestlower == 'install') {
		require AS_INCLUDE_DIR . 'as-install.php';
	} elseif ($requestlower == 'url/test/' . AS_URL_TEST_STRING) {
		require AS_INCLUDE_DIR . 'as-url-test.php';
	} else {
		// enable gzip compression for output (needs to come early)
		as_initialize_buffering($requestlower);

		if (substr($requestlower, 0, 5) == 'feed/') {
			require AS_INCLUDE_DIR . 'as-feed.php';
		} else {
			require AS_INCLUDE_DIR . 'as-page.php';
		}
	}
}

as_report_process_stage('shutdown');
