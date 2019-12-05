<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Controller for admin page showing songs, reviews and comments waiting for approval


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
	header('Location: ../../../');
	exit;
}

require_once AS_INCLUDE_DIR . 'app/admin.php';
require_once AS_INCLUDE_DIR . 'db/selects.php';
require_once AS_INCLUDE_DIR . 'app/format.php';


// Find queued songs, reviews, comments

$userid = as_get_logged_in_userid();

list($queuedsongs, $queuedreviews, $queuedcomments) = as_db_select_with_pending(
	as_db_qs_selectspec($userid, 'created', 0, null, null, 'S_QUEUED', true),
	as_db_recent_a_qs_selectspec($userid, 0, null, null, 'R_QUEUED', true),
	as_db_recent_c_qs_selectspec($userid, 0, null, null, 'C_QUEUED', true)
);


// Check admin privileges (do late to allow one DB query)

if (as_user_maximum_permit_error('permit_moderate')) {
	$as_content = as_content_prepare();
	$as_content['error'] = as_lang_html('users/no_permission');
	return $as_content;
}


// Check to see if any were approved/rejected here

$pageerror = as_admin_check_clicks();


// Combine sets of songs and remove those this user has no permission to moderate

$songs = as_any_sort_by_date(array_merge($queuedsongs, $queuedreviews, $queuedcomments));

if (as_user_permit_error('permit_moderate')) { // if user not allowed to moderate all posts
	foreach ($songs as $index => $song) {
		if (as_user_post_permit_error('permit_moderate', $song))
			unset($songs[$index]);
	}
}


// Get information for users

$usershtml = as_userids_handles_html(as_any_get_userids_handles($songs));


// Prepare content for theme

$as_content = as_content_prepare();

$as_content['title'] = as_lang_html('admin/recent_approve_title');
$as_content['error'] = isset($pageerror) ? $pageerror : as_admin_page_error();

$as_content['s_list'] = array(
	'form' => array(
		'tags' => 'method="post" action="' . as_self_html() . '"',

		'hidden' => array(
			'code' => as_get_form_security_code('admin/click'),
		),
	),

	'qs' => array(),
);

if (count($songs)) {
	foreach ($songs as $song) {
		$postid = as_html(isset($song['opostid']) ? $song['opostid'] : $song['postid']);
		$elementid = 'p' . $postid;

		$htmloptions = as_post_html_options($song);
		$htmloptions['thumbview'] = false;
		$htmloptions['tagsview'] = !isset($song['opostid']);
		$htmloptions['reviewsview'] = false;
		$htmloptions['viewsview'] = false;
		$htmloptions['contentview'] = true;
		$htmloptions['elementid'] = $elementid;

		$htmlfields = as_any_to_q_html_fields($song, $userid, as_cookie_get(), $usershtml, null, $htmloptions);

		if (isset($htmlfields['what_url'])) // link directly to relevant content
			$htmlfields['url'] = $htmlfields['what_url'];

		$posttype = as_strtolower(isset($song['obasetype']) ? $song['obasetype'] : $song['basetype']);
		switch ($posttype) {
			case 'q':
			default:
				$approveKey = 'song/approve_q_popup';
				$rejectKey = 'song/reject_q_popup';
				break;
			case 'a':
				$approveKey = 'song/approve_a_popup';
				$rejectKey = 'song/reject_a_popup';
				break;
			case 'c':
				$approveKey = 'song/approve_c_popup';
				$rejectKey = 'song/reject_c_popup';
				break;
		}

		$htmlfields['form'] = array(
			'style' => 'light',

			'buttons' => array(
				// Possible values for popup: approve_q_popup, approve_a_popup, approve_c_popup
				'approve' => array(
					'tags' => 'name="admin_' . $postid . '_approve" onclick="return as_admin_click(this);"',
					'label' => as_lang_html('song/approve_button'),
					'popup' => as_lang_html($approveKey),
				),

				// Possible values for popup: reject_q_popup, reject_a_popup, reject_c_popup
				'reject' => array(
					'tags' => 'name="admin_' . $postid . '_reject" onclick="return as_admin_click(this);"',
					'label' => as_lang_html('song/reject_button'),
					'popup' => as_lang_html($rejectKey),
				),
			),
		);

		$as_content['s_list']['qs'][] = $htmlfields;
	}

} else
	$as_content['title'] = as_lang_html('admin/no_approve_found');


$as_content['navigation']['sub'] = as_admin_sub_navigation();
$as_content['script_rel'][] = 'as-content/as-admin.js?' . AS_VERSION;


return $as_content;
