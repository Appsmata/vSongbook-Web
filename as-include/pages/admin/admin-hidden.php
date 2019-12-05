<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Controller for admin page showing hidden songs, reviews and comments


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
require_once AS_INCLUDE_DIR . 'db/admin.php';
require_once AS_INCLUDE_DIR . 'db/selects.php';
require_once AS_INCLUDE_DIR . 'app/format.php';


// Find recently hidden songs, reviews, comments

$userid = as_get_logged_in_userid();

list($hiddensongs, $hiddenreviews, $hiddencomments) = as_db_select_with_pending(
	as_db_qs_selectspec($userid, 'created', 0, null, null, 'S_HIDDEN', true),
	as_db_recent_a_qs_selectspec($userid, 0, null, null, 'R_HIDDEN', true),
	as_db_recent_c_qs_selectspec($userid, 0, null, null, 'C_HIDDEN', true)
);


// Check admin privileges (do late to allow one DB query)

if (as_user_maximum_permit_error('permit_hide_show') && as_user_maximum_permit_error('permit_delete_hidden')) {
	$as_content = as_content_prepare();
	$as_content['error'] = as_lang_html('users/no_permission');
	return $as_content;
}


// Check to see if any have been reshown or deleted

$pageerror = as_admin_check_clicks();


// Combine sets of songs and remove those this user has no permissions for

$songs = as_any_sort_by_date(array_merge($hiddensongs, $hiddenreviews, $hiddencomments));

if (as_user_permit_error('permit_hide_show') && as_user_permit_error('permit_delete_hidden')) { // not allowed to see all hidden posts
	foreach ($songs as $index => $song) {
		if (as_user_post_permit_error('permit_hide_show', $song) && as_user_post_permit_error('permit_delete_hidden', $song)) {
			unset($songs[$index]);
		}
	}
}


// Get information for users

$usershtml = as_userids_handles_html(as_any_get_userids_handles($songs));


// Create list of actual hidden postids and see which ones have dependents

$qhiddenpostid = array();
foreach ($songs as $key => $song)
	$qhiddenpostid[$key] = isset($song['opostid']) ? $song['opostid'] : $song['postid'];

$dependcounts = as_db_postids_count_dependents($qhiddenpostid);


// Prepare content for theme

$as_content = as_content_prepare();

$as_content['title'] = as_lang_html('admin/recent_hidden_title');
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
	foreach ($songs as $key => $song) {
		$elementid = 'p' . $qhiddenpostid[$key];

		$htmloptions = as_post_html_options($song);
		$htmloptions['thumbview'] = false;
		$htmloptions['tagsview'] = !isset($song['opostid']);
		$htmloptions['reviewsview'] = false;
		$htmloptions['viewsview'] = false;
		$htmloptions['updateview'] = false;
		$htmloptions['contentview'] = true;
		$htmloptions['flagsview'] = true;
		$htmloptions['elementid'] = $elementid;

		$htmlfields = as_any_to_q_html_fields($song, $userid, as_cookie_get(), $usershtml, null, $htmloptions);

		if (isset($htmlfields['what_url'])) // link directly to relevant content
			$htmlfields['url'] = $htmlfields['what_url'];

		$htmlfields['what_2'] = as_lang_html('main/hidden');

		if (@$htmloptions['whenview']) {
			$updated = @$song[isset($song['opostid']) ? 'oupdated' : 'updated'];
			if (isset($updated))
				$htmlfields['when_2'] = as_when_to_html($updated, @$htmloptions['fulldatedays']);
		}

		$buttons = array();

		$posttype = as_strtolower(isset($song['obasetype']) ? $song['obasetype'] : $song['basetype']);

		if (!as_user_post_permit_error('permit_hide_show', $song)) {
			// Possible values for popup: reshow_q_popup, reshow_a_popup, reshow_c_popup
			$buttons['reshow'] = array(
				'tags' => 'name="admin_' . as_html($qhiddenpostid[$key]) . '_reshow" onclick="return as_admin_click(this);"',
				'label' => as_lang_html('song/reshow_button'),
				'popup' => as_lang_html(sprintf('song/reshow_%s_popup', $posttype)),
			);
		}

		if (!as_user_post_permit_error('permit_delete_hidden', $song) && !$dependcounts[$qhiddenpostid[$key]]) {
			// Possible values for popup: delete_q_popup, delete_a_popup, delete_c_popup
			$buttons['delete'] = array(
				'tags' => 'name="admin_' . as_html($qhiddenpostid[$key]) . '_delete" onclick="return as_admin_click(this);"',
				'label' => as_lang_html('song/delete_button'),
				'popup' => as_lang_html(sprintf('song/delete_%s_popup', $posttype)),
			);
		}

		if (count($buttons)) {
			$htmlfields['form'] = array(
				'style' => 'light',
				'buttons' => $buttons,
			);
		}

		$as_content['s_list']['qs'][] = $htmlfields;
	}

} else
	$as_content['title'] = as_lang_html('admin/no_hidden_found');


$as_content['navigation']['sub'] = as_admin_sub_navigation();
$as_content['script_rel'][] = 'as-content/as-admin.js?' . AS_VERSION;


return $as_content;
