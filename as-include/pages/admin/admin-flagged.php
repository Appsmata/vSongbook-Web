<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Controller for admin page showing posts with the most flags


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


// Find most flagged songs, reviews, comments

$userid = as_get_logged_in_userid();

$songs = as_db_select_with_pending(
	as_db_flagged_post_qs_selectspec($userid, 0, true)
);


// Check admin privileges (do late to allow one DB query)

if (as_user_maximum_permit_error('permit_hide_show')) {
	$as_content = as_content_prepare();
	$as_content['error'] = as_lang_html('users/no_permission');
	return $as_content;
}


// Check to see if any were cleared or hidden here

$pageerror = as_admin_check_clicks();


// Remove songs the user has no permission to hide/show

if (as_user_permit_error('permit_hide_show')) { // if user not allowed to show/hide all posts
	foreach ($songs as $index => $song) {
		if (as_user_post_permit_error('permit_hide_show', $song)) {
			unset($songs[$index]);
		}
	}
}


// Get information for users

$usershtml = as_userids_handles_html(as_any_get_userids_handles($songs));


// Prepare content for theme

$as_content = as_content_prepare();

$as_content['title'] = as_lang_html('admin/most_flagged_title');
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
		$htmloptions['tagsview'] = ($song['obasetype'] == 'S');
		$htmloptions['reviewsview'] = false;
		$htmloptions['viewsview'] = false;
		$htmloptions['contentview'] = true;
		$htmloptions['flagsview'] = true;
		$htmloptions['elementid'] = $elementid;

		$htmlfields = as_any_to_q_html_fields($song, $userid, as_cookie_get(), $usershtml, null, $htmloptions);

		if (isset($htmlfields['what_url'])) // link directly to relevant content
			$htmlfields['url'] = $htmlfields['what_url'];

		$htmlfields['form'] = array(
			'style' => 'light',

			'buttons' => array(
				'clearflags' => array(
					'tags' => 'name="admin_' . $postid . '_clearflags" onclick="return as_admin_click(this);"',
					'label' => as_lang_html('song/clear_flags_button'),
				),

				'hide' => array(
					'tags' => 'name="admin_' . $postid . '_hide" onclick="return as_admin_click(this);"',
					'label' => as_lang_html('song/hide_button'),
				),
			),
		);

		$as_content['s_list']['qs'][] = $htmlfields;
	}

} else
	$as_content['title'] = as_lang_html('admin/no_flagged_found');


$as_content['navigation']['sub'] = as_admin_sub_navigation();
$as_content['script_rel'][] = 'as-content/as-admin.js?' . AS_VERSION;


return $as_content;
