<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Response to blob requests, outputting blob from the database


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


// Ensure no PHP errors are shown in the blob response

@ini_set('display_errors', 0);

function as_blob_db_fail_handler()
{
	header('HTTP/1.1 500 Internal Server Error');
	as_exit('error');
}


// Load the APS base file which sets up a bunch of crucial stuff

$as_autoconnect = false;
require 'as-base.php';

as_report_process_stage('init_blob');


// Output the blob in song

require_once AS_INCLUDE_DIR . 'app/blobs.php';

as_db_connect('as_blob_db_fail_handler');
as_initialize_postdb_plugins();

$blob = as_read_blob(as_get('as_blobid'));

if (isset($blob) && isset($blob['content'])) {
	// allows browsers and proxies to cache the blob (30 days)
	header('Cache-Control: max-age=2592000, public');

	$disposition = 'inline';

	switch ($blob['format']) {
		case 'jpeg':
		case 'jpg':
			header('Content-Type: image/jpeg');
			break;

		case 'gif':
			header('Content-Type: image/gif');
			break;

		case 'png':
			header('Content-Type: image/png');
			break;

		case 'pdf':
			header('Content-Type: application/pdf');
			break;

		case 'swf':
			header('Content-Type: application/x-shockwave-flash');
			break;

		default:
			header('Content-Type: application/octet-stream');
			$disposition = 'attachment';
			break;
	}

	// for compatibility with HTTP headers and all browsers
	$filename = preg_replace('/[^A-Za-z0-9 \\._-]+/', '', $blob['filename']);
	header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');

	echo $blob['content'];

} else {
	header('HTTP/1.0 404 Not Found');
}

as_db_disconnect();
