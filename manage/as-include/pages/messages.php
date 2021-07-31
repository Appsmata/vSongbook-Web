<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Controller for private messaging page


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
require_once AS_INCLUDE_DIR . 'app/users.php';
require_once AS_INCLUDE_DIR . 'app/format.php';
require_once AS_INCLUDE_DIR . 'app/limits.php';

$signinUserId = as_get_logged_in_userid();
$signinUserHandle = as_get_logged_in_handle();


// Check which box we're showing (inbox/sent), we're not using APS's single-sign on integration and that we're logged in

$req = as_request_part(1);
if ($req === null)
	$showOutbox = false;
elseif ($req === 'sent')
	$showOutbox = true;
else
	return include AS_INCLUDE_DIR . 'as-page-not-found.php';

if (AS_FINAL_EXTERNAL_USERS)
	as_fatal_error('User accounts are handled by external code');

if (!isset($signinUserId)) {
	$as_content = as_content_prepare();
	$as_content['error'] = as_insert_signin_links(as_lang_html('misc/message_must_signin'), as_request());
	return $as_content;
}

if (!as_opt('allow_private_messages') || !as_opt('show_message_history'))
	return include AS_INCLUDE_DIR . 'as-page-not-found.php';


// Find the messages for this user

$start = as_get_start();
$pagesize = as_opt('page_size_pms');

// get number of messages then actual messages for this page
$func = $showOutbox ? 'as_db_messages_outbox_selectspec' : 'as_db_messages_inbox_selectspec';
$pmSpecCount = as_db_selectspec_count($func('private', $signinUserId, true));
$pmSpec = $func('private', $signinUserId, true, $start, $pagesize);

list($numMessages, $userMessages) = as_db_select_with_pending($pmSpecCount, $pmSpec);
$count = $numMessages['count'];


// Prepare content for theme

$as_content = as_content_prepare();
$as_content['title'] = as_lang_html($showOutbox ? 'misc/pm_outbox_title' : 'misc/pm_inbox_title');

$as_content['custom'] =
	'<div style="text-align:center">' .
		($showOutbox ? '<a href="' . as_path_html('messages') . '">' . as_lang_html('misc/inbox') . '</a>' : as_lang_html('misc/inbox')) .
		' - ' .
		($showOutbox ? as_lang_html('misc/outbox') : '<a href="' . as_path_html('messages/sent') . '">' . as_lang_html('misc/outbox') . '</a>') .
	'</div>';

$as_content['message_list'] = array(
	'tags' => 'id="privatemessages"',
	'messages' => array(),
	'form' => array(
		'tags' => 'name="pmessage" method="post" action="' . as_self_html() . '"',
		'style' => 'tall',
		'hidden' => array(
			'as_click' => '', // for simulating clicks in Javascript
			'handle' => as_html($signinUserHandle),
			'start' => as_html($start),
			'code' => as_get_form_security_code('pm-' . $signinUserHandle),
		),
	),
);

$htmlDefaults = as_message_html_defaults();
if ($showOutbox)
	$htmlDefaults['towhomview'] = true;

foreach ($userMessages as $message) {
	$msgFormat = as_message_html_fields($message, $htmlDefaults);
	$replyHandle = $showOutbox ? $message['tohandle'] : $message['fromhandle'];
	$replyId = $showOutbox ? $message['touserid'] : $message['fromuserid'];

	$msgFormat['form'] = array(
		'style' => 'light',
		'buttons' => array(),
	);

	if (!empty($replyHandle) && $replyId != $signinUserId) {
		$msgFormat['form']['buttons']['reply'] = array(
			'tags' => 'onclick="window.location.href=\'' . as_path_html('message/' . $replyHandle) . '\';return false"',
			'label' => as_lang_html('song/reply_button'),
		);
	}

	$msgFormat['form']['buttons']['delete'] = array(
		'tags' => 'name="m' . as_html($message['messageid']) . '_dodelete" onclick="return as_pm_click(' . as_js($message['messageid']) . ', this, ' . as_js($showOutbox ? 'outbox' : 'inbox') . ');"',
		'label' => as_lang_html('song/delete_button'),
		'popup' => as_lang_html('profile/delete_pm_popup'),
	);

	$as_content['message_list']['messages'][] = $msgFormat;
}

$as_content['page_links'] = as_html_page_links(as_request(), $start, $pagesize, $count, as_opt('pages_prev_next'));

$fullname = as_db_name_find_by_handle($signinUserHandle);
$as_content['navigation']['sub'] = as_user_sub_navigation($fullname, $signinUserHandle, 'messages', true);

return $as_content;
