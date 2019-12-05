<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Server-side response to Ajax version check requests


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

require_once AS_INCLUDE_DIR . 'app/admin.php';
require_once AS_INCLUDE_DIR . 'app/users.php';

if (as_get_logged_in_level() < AS_USER_LEVEL_ADMIN) {
	echo "AS_AJAX_RESPONSE\n0\n" . as_lang_html('admin/no_privileges');
	return;
}

$uri = as_post_text('uri');
$currentVersion = as_post_text('version');
$isCore = as_post_text('isCore') === "true";

if ($isCore) {
	$contents = as_retrieve_url($uri);

	if (strlen($contents) > 0) {
		if (as_as_version_below($contents)) {
			$response =
				'<a href="https://github.com/aps/song2review/releases" style="color:#d00;">' .
				as_lang_html_sub('admin/version_get_x', as_html('v' . $contents)) .
				'</a>';
		} else {
			$response = as_html($contents); // Output the current version number
		}
	} else {
		$response = as_lang_html('admin/version_latest_unknown');
	}
} else {
	$metadataUtil = new APS_Util_Metadata();
	$metadata = $metadataUtil->fetchFromUrl($uri);

	if (strlen(@$metadata['version']) > 0) {
		if (version_compare($currentVersion, $metadata['version']) < 0) {
			if (as_as_version_below(@$metadata['min_aps'])) {
				$response = strtr(as_lang_html('admin/version_requires_aps'), array(
					'^1' => as_html('v' . $metadata['version']),
					'^2' => as_html($metadata['min_aps']),
				));
			} elseif (as_php_version_below(@$metadata['min_php'])) {
				$response = strtr(as_lang_html('admin/version_requires_php'), array(
					'^1' => as_html('v' . $metadata['version']),
					'^2' => as_html($metadata['min_php']),
				));
			} else {
				$response = as_lang_html_sub('admin/version_get_x', as_html('v' . $metadata['version']));

				if (strlen(@$metadata['uri'])) {
					$response = '<a href="' . as_html($metadata['uri']) . '" style="color:#d00;">' . $response . '</a>';
				}
			}
		} else {
			$response = as_lang_html('admin/version_latest');
		}
	} else {
		$response = as_lang_html('admin/version_latest_unknown');
	}
}

echo "AS_AJAX_RESPONSE\n1\n" . $response;
