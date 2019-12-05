<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Controller for page showing recent activity for an IP address


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
	header('Location: ../../');
	exit;
}

require_once AS_INCLUDE_DIR . 'db/selects.php';
require_once AS_INCLUDE_DIR . 'app/format.php';


$ip = as_request_part(1); // picked up from as-page.php
if (filter_var($ip, FILTER_VALIDATE_IP) === false)
	return include AS_INCLUDE_DIR . 'as-page-not-found.php';


// Find recently (hidden, queued or not) songs, reviews, comments and edits for this IP

$userid = as_get_logged_in_userid();

list($qs, $qs_queued, $qs_hidden, $a_qs, $a_queued_qs, $a_hidden_qs, $c_qs, $c_queued_qs, $c_hidden_qs, $edit_qs) =
	as_db_select_with_pending(
		as_db_qs_selectspec($userid, 'created', 0, null, $ip, false),
		as_db_qs_selectspec($userid, 'created', 0, null, $ip, 'S_QUEUED'),
		as_db_qs_selectspec($userid, 'created', 0, null, $ip, 'S_HIDDEN', true),
		as_db_recent_a_qs_selectspec($userid, 0, null, $ip, false),
		as_db_recent_a_qs_selectspec($userid, 0, null, $ip, 'R_QUEUED'),
		as_db_recent_a_qs_selectspec($userid, 0, null, $ip, 'R_HIDDEN', true),
		as_db_recent_c_qs_selectspec($userid, 0, null, $ip, false),
		as_db_recent_c_qs_selectspec($userid, 0, null, $ip, 'C_QUEUED'),
		as_db_recent_c_qs_selectspec($userid, 0, null, $ip, 'C_HIDDEN', true),
		as_db_recent_edit_qs_selectspec($userid, 0, null, $ip, false)
	);


// Check we have permission to view this page, and whether we can block or unblock IPs

if (as_user_maximum_permit_error('permit_anon_view_ips')) {
	$as_content = as_content_prepare();
	$as_content['error'] = as_lang_html('users/no_permission');
	return $as_content;
}

$blockable = as_user_level_maximum() >= AS_USER_LEVEL_MODERATOR; // allow moderator in one category to block across all categories


// Perform blocking or unblocking operations as appropriate

if (as_clicked('doblock') || as_clicked('dounblock') || as_clicked('dohideall')) {
	if (!as_check_form_security_code('ip-' . $ip, as_post_text('code')))
		$pageerror = as_lang_html('misc/form_security_again');

	elseif ($blockable) {
		if (as_clicked('doblock')) {
			$oldblocked = as_opt('block_ips_write');
			as_set_option('block_ips_write', (strlen($oldblocked) ? ($oldblocked . ' , ') : '') . $ip);

			as_report_event('ip_block', $userid, as_get_logged_in_handle(), as_cookie_get(), array(
				'ip' => $ip,
			));

			as_redirect(as_request());
		}

		if (as_clicked('dounblock')) {
			require_once AS_INCLUDE_DIR . 'app/limits.php';

			$blockipclauses = as_block_ips_explode(as_opt('block_ips_write'));

			foreach ($blockipclauses as $key => $blockipclause) {
				if (as_block_ip_match($ip, $blockipclause))
					unset($blockipclauses[$key]);
			}

			as_set_option('block_ips_write', implode(' , ', $blockipclauses));

			as_report_event('ip_unblock', $userid, as_get_logged_in_handle(), as_cookie_get(), array(
				'ip' => $ip,
			));

			as_redirect(as_request());
		}

		if (as_clicked('dohideall') && !as_user_maximum_permit_error('permit_hide_show')) {
			// allow moderator in one category to hide posts across all categories if they are identified via IP page

			require_once AS_INCLUDE_DIR . 'db/admin.php';
			require_once AS_INCLUDE_DIR . 'app/posts.php';

			$postids = as_db_get_ip_visible_postids($ip);

			foreach ($postids as $postid)
				as_post_set_status($postid, AS_POST_STATUS_HIDDEN, $userid);

			as_redirect(as_request());
		}
	}
}


// Combine sets of songs and get information for users

$songs = as_any_sort_by_date(array_merge($qs, $qs_queued, $qs_hidden, $a_qs, $a_queued_qs, $a_hidden_qs, $c_qs, $c_queued_qs, $c_hidden_qs, $edit_qs));

$usershtml = as_userids_handles_html(as_any_get_userids_handles($songs));

$hostname = gethostbyaddr($ip);


// Prepare content for theme

$as_content = as_content_prepare();

$as_content['title'] = as_lang_html_sub('main/ip_address_x', as_html($ip));
$as_content['error'] = @$pageerror;

$as_content['form'] = array(
	'tags' => 'method="post" action="' . as_self_html() . '"',

	'style' => 'wide',

	'fields' => array(
		'host' => array(
			'type' => 'static',
			'label' => as_lang_html('misc/host_name'),
			'value' => as_html($hostname),
		),
	),

	'hidden' => array(
		'code' => as_get_form_security_code('ip-' . $ip),
	),
);


if ($blockable) {
	require_once AS_INCLUDE_DIR . 'app/limits.php';

	$blockipclauses = as_block_ips_explode(as_opt('block_ips_write'));
	$matchclauses = array();

	foreach ($blockipclauses as $blockipclause) {
		if (as_block_ip_match($ip, $blockipclause))
			$matchclauses[] = $blockipclause;
	}

	if (count($matchclauses)) {
		$as_content['form']['fields']['status'] = array(
			'type' => 'static',
			'label' => as_lang_html('misc/matches_blocked_ips'),
			'value' => as_html(implode("\n", $matchclauses), true),
		);

		$as_content['form']['buttons']['unblock'] = array(
			'tags' => 'name="dounblock"',
			'label' => as_lang_html('misc/unblock_ip_button'),
		);

		if (count($songs) && !as_user_maximum_permit_error('permit_hide_show'))
			$as_content['form']['buttons']['hideall'] = array(
				'tags' => 'name="dohideall" onclick="as_show_waiting_after(this, false);"',
				'label' => as_lang_html('misc/hide_all_ip_button'),
			);

	} else {
		$as_content['form']['buttons']['block'] = array(
			'tags' => 'name="doblock"',
			'label' => as_lang_html('misc/block_ip_button'),
		);
	}
}


$as_content['s_list']['qs'] = array();

if (count($songs)) {
	$as_content['s_list']['title'] = as_lang_html_sub('misc/recent_activity_from_x', as_html($ip));

	foreach ($songs as $song) {
		$htmloptions = as_post_html_options($song);
		$htmloptions['tagsview'] = false;
		$htmloptions['thumbview'] = false;
		$htmloptions['ipview'] = false;
		$htmloptions['reviewsview'] = false;
		$htmloptions['viewsview'] = false;
		$htmloptions['updateview'] = false;

		$htmlfields = as_any_to_q_html_fields($song, $userid, as_cookie_get(), $usershtml, null, $htmloptions);

		if (isset($htmlfields['what_url'])) // link directly to relevant content
			$htmlfields['url'] = $htmlfields['what_url'];

		$hasother = isset($song['opostid']);

		if ($song[$hasother ? 'ohidden' : 'hidden'] && !isset($song[$hasother ? 'oupdatetype' : 'updatetype'])) {
			$htmlfields['what_2'] = as_lang_html('main/hidden');

			if (@$htmloptions['whenview']) {
				$updated = @$song[$hasother ? 'oupdated' : 'updated'];
				if (isset($updated))
					$htmlfields['when_2'] = as_when_to_html($updated, @$htmloptions['fulldatedays']);
			}
		}

		$as_content['s_list']['qs'][] = $htmlfields;
	}

} else
	$as_content['s_list']['title'] = as_lang_html_sub('misc/no_activity_from_x', as_html($ip));


return $as_content;
