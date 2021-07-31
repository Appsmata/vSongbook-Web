<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Controller for unsubscribe page (unsubscribe link is sent in mass mailings)


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

require_once AS_INCLUDE_DIR . 'db/users.php';


// Check we're not using single-sign on integration

if (AS_FINAL_EXTERNAL_USERS)
	as_fatal_error('User signin is handled by external code');


// Check the code and unsubscribe the user if appropriate

// check if already unsubscribed
$unsubscribed = (bool) (as_get_logged_in_flags() & AS_USER_FLAGS_NO_MAILINGS);
$loggedInUserId = as_get_logged_in_userid();
$isLoggedIn = $loggedInUserId !== null;

if (as_clicked('dounsubscribe')) {
	if (!as_check_form_security_code('unsubscribe', as_post_text('formcode'))) {
		$pageError = as_lang_html('misc/form_security_again');

	} else {
		if ($isLoggedIn) {
			// logged in users can unsubscribe right away
			as_db_user_set_flag($loggedInUserId, AS_USER_FLAGS_NO_MAILINGS, true);
			$unsubscribed = true;

		} else {
			// logged out users require valid code (from email link)
			$incode = trim(as_post_text('code'));
			$inhandle = as_post_text('handle');

			if (!empty($inhandle)) {
				$userinfo = as_db_select_with_pending(as_db_user_account_selectspec($inhandle, false));

				if (strtolower(trim(@$userinfo['emailcode'])) == strtolower($incode)) {
					as_db_user_set_flag($userinfo['userid'], AS_USER_FLAGS_NO_MAILINGS, true);
					$unsubscribed = true;
				}
			}

			if (!$unsubscribed) {
				$pageError = as_insert_signin_links(as_lang_html('users/unsubscribe_wrong_log_in'), 'unsubscribe');
			}
		}
	}
}


// Prepare content for theme

$as_content = as_content_prepare();

$as_content['title'] = as_lang_html('users/unsubscribe_title');

if ($unsubscribed) {
	$as_content['success'] = strtr(as_lang_html('users/unsubscribe_complete'), array(
		'^0' => as_html(as_opt('site_title')),
		'^1' => '<a href="' . as_path_html('account') . '">',
		'^2' => '</a>',
	));

} elseif (!empty($pageError)) {
	$as_content['error'] = $pageError;

} else {
	$contentForm = array(
		'tags' => 'method="post" action="' . as_path_html('unsubscribe') . '"',

		'style' => 'wide',

		'fields' => array(),

		'buttons' => array(
			'send' => array(
				'tags' => 'name="dounsubscribe"',
				'label' => as_lang_html('users/unsubscribe_title'),
			),
		),

		'hidden' => array(
			'formcode' => as_get_form_security_code('unsubscribe'),
		),
	);

	if ($isLoggedIn) {
		// user is logged in: show button to confirm unsubscribe
		$contentForm['fields']['email'] = array(
			'type' => 'static',
			'label' => as_lang_html('users/email_label'),
			'value' => as_html(as_get_logged_in_email()),
		);

	} else {
		// user is not logged in: show form with email address
		$incode = trim(as_get('c'));
		$inhandle = as_get('u');

		if (empty($incode) || empty($inhandle)) {
			$as_content['error'] = as_insert_signin_links(as_lang_html('users/unsubscribe_wrong_log_in'), 'account');
			$contentForm = null;
		} else {
			$contentForm['fields']['handle'] = array(
				'type' => 'static',
				'label' => as_lang_html('users/handle_label'),
				'value' => as_html($inhandle),
			);
			$contentForm['hidden']['code'] = as_html($incode);
			$contentForm['hidden']['handle'] = as_html($inhandle);
		}
	}

	if ($contentForm) {
		$as_content['form'] = $contentForm;
	}
}

return $as_content;
