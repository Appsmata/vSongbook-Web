<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Outputs image for a specific blob at a specific size, caching as appropriate


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


// Ensure no PHP errors are shown in the image data

@ini_set('display_errors', 0);

function as_image_db_fail_handler()
{
	header('HTTP/1.1 500 Internal Server Error');
	as_exit('error');
}


// Load the APS base file which sets up a bunch of crucial stuff

$as_autoconnect = false;
require 'as-base.php';

as_report_process_stage('init_image');


// Retrieve the scaled image from the cache if available

require_once AS_INCLUDE_DIR . 'db/cache.php';

as_db_connect('as_image_db_fail_handler');
as_initialize_postdb_plugins();

$blobid = as_get('as_blobid');
$size = (int)as_get('as_size');
$cachetype = 'i_' . $size;

$content = as_db_cache_get($cachetype, $blobid); // see if we've cached the scaled down version

header('Cache-Control: max-age=2592000, public'); // allows browsers and proxies to cache images too

if (isset($content)) {
	header('Content-Type: image/jpeg');
	echo $content;

} else {
	require_once AS_INCLUDE_DIR . 'app/options.php';
	require_once AS_INCLUDE_DIR . 'app/blobs.php';
	require_once AS_INCLUDE_DIR . 'util/image.php';


	// Otherwise retrieve the raw image and scale as appropriate

	$blob = as_read_blob($blobid);

	if (isset($blob)) {
		if ($size > 0)
			$content = as_image_constrain_data($blob['content'], $width, $height, $size);
		else
			$content = $blob['content'];

		if (isset($content)) {
			header('Content-Type: image/jpeg');
			echo $content;

			if (strlen($content) && ($size > 0)) {
				// to prevent cache being filled with inappropriate sizes
				$cachesizes = as_get_options(array('avatar_profile_size', 'avatar_users_size', 'avatar_q_page_q_size', 'avatar_q_page_a_size', 'avatar_q_page_c_size', 'avatar_s_list_size'));

				if (array_search($size, $cachesizes))
					as_db_cache_set($cachetype, $blobid, $content);
			}
		}
	}
}

as_db_disconnect();
