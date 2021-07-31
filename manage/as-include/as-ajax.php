<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Front line of response to Ajax requests, routing as appropriate


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

// Output this header as early as possible

header('Content-Type: text/plain; charset=utf-8');


// Ensure no PHP errors are shown in the Ajax response

@ini_set('display_errors', 0);


// Load the APS base file which sets up a bunch of crucial functions

$as_autoconnect = false;
require 'as-base.php';

as_report_process_stage('init_ajax');


// Get general Ajax parameters from the POST payload, and clear $_GET

as_set_request(as_post_text('as_request'), as_post_text('as_root'));

$_GET = array(); // for as_self_html()


// Database failure handler

function as_ajax_db_fail_handler()
{
	echo "AS_AJAX_RESPONSE\n0\nA database error occurred.";
	as_exit('error');
}


// Perform the appropriate Ajax operation

$routing = array(
	'notice' => 'notice.php',
	'favorite' => 'favorite.php',
	'searchsong' => 'search-song.php',
	'selectbook' => 'select-book.php',
	'thumb' => 'thumb.php',
	'recalc' => 'recalc.php',
	'mailing' => 'mailing.php',
	'version' => 'version.php',
	'category' => 'category.php',
	'posttitle' => 'posttitle.php',
	'review' => 'review.php',
	'comment' => 'comment.php',
	'click_a' => 'click-review.php',
	'click_c' => 'click-comment.php',
	'click_admin' => 'click-admin.php',
	'show_cs' => 'show-comments.php',
	'wallpost' => 'wallpost.php',
	'click_wall' => 'click-wall.php',
	'click_pm' => 'click-pm.php',
);

$operation = as_post_text('as_operation');

if (isset($routing[$operation])) {
	as_db_connect('as_ajax_db_fail_handler');
	as_initialize_postdb_plugins();

	as_initialize_buffering();
	require AS_INCLUDE_DIR . 'ajax/' . $routing[$operation];

	as_db_disconnect();
}
