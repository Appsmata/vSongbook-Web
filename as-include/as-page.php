<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Initialization for page requests


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

if (!defined('AS_VERSION')) { // don't allow this page to be requested directly from browser
	header('Location: ../');
	exit;
}

require_once AS_INCLUDE_DIR . 'app/page.php';


// Below are the steps that actually execute for this file - all the above are function definitions

global $as_usage;

as_report_process_stage('init_page');
as_db_connect('as_page_db_fail_handler');
as_initialize_postdb_plugins();

as_page_queue_pending();
as_load_state();
as_check_signin_modules();

if (AS_DEBUG_PERFORMANCE)
	$as_usage->mark('setup');

as_check_page_clicks();

$as_content = as_get_request_content();

if (is_array($as_content)) {
	if (AS_DEBUG_PERFORMANCE)
		$as_usage->mark('view');

	as_output_content($as_content);

	if (AS_DEBUG_PERFORMANCE)
		$as_usage->mark('theme');

	if (as_do_content_stats($as_content) && AS_DEBUG_PERFORMANCE)
		$as_usage->mark('stats');

	if (AS_DEBUG_PERFORMANCE)
		$as_usage->output();
}

as_db_disconnect();
