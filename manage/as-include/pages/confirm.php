<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Controller for email confirmation page (can also request a new code)


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

// Check we're not using single-sign on integration, that we're not already confirmed, and that we're not blocked

if (AS_FINAL_EXTERNAL_USERS) {
	as_fatal_error('User signin is handled by external code');
}

// Check if we've been posted to send a new link or have a successful email confirmation

// Fetch the handle from POST or GET
$handle = as_post_text('username');
if (!isset($handle)) {
	$handle = as_get('u');
}
$handle = trim($handle); // if $handle is null, trim returns an empty string

// Fetch the code from POST or GET
$code = as_post_text('code');
if (!isset($code)) {
	$code = as_get('c');
}
$code = trim($code); // if $code is null, trim returns an empty string

$loggedInUserId = as_get_logged_in_userid();
$emailConfirmationSent = false;
$userConfirmed = false;

$pageError = null;

if (isset($loggedInUserId) && as_clicked('dosendconfirm')) { // A logged in user requested to be sent a confirmation link
	if (!as_check_form_security_code('confirm', as_post_text('formcode'))) {
		$pageError = as_lang_html('misc/form_security_again');
	} else {
		// For as_send_new_confirm
		require_once AS_INCLUDE_DIR . 'app/users-edit.php';

		as_send_new_confirm($loggedInUserId);
		$emailConfirmationSent = true;
	}
} elseif (strlen($code) > 0) { // If there is a code present in the URL
	// For as_db_select_with_pending, as_db_user_account_selectspec
	require_once AS_INCLUDE_DIR . 'db/selects.php';

	// For as_complete_confirm
	require_once AS_INCLUDE_DIR . 'app/users-edit.php';

	if (strlen($handle) > 0) { // If there is a handle present in the URL
		$userInfo = as_db_select_with_pending(as_db_user_account_selectspec($handle, false));

		if (strtolower(trim($userInfo['emailcode'])) == strtolower($code)) {
			as_complete_confirm($userInfo['userid'], $userInfo['email'], $userInfo['handle']);
			$userConfirmed = true;
		}
	}

	if (!$userConfirmed && isset($loggedInUserId)) { // As a backup, also match code on URL against logged in user
		$userInfo = as_db_select_with_pending(as_db_user_account_selectspec($loggedInUserId, true));
		$flags = $userInfo['flags'];

		if (($flags & AS_USER_FLAGS_EMAIL_CONFIRMED) > 0 && ($flags & AS_USER_FLAGS_MUST_CONFIRM) == 0) {
			$userConfirmed = true; // if they confirmed before, just show message as if it happened now
		} elseif (strtolower(trim($userInfo['emailcode'])) == strtolower($code)) {
			as_complete_confirm($userInfo['userid'], $userInfo['email'], $userInfo['handle']);
			$userConfirmed = true;
		}
	}
}

// Prepare content for theme

$as_content = as_content_prepare();

$as_content['title'] = as_lang_html('users/confirm_title');
$as_content['error'] = $pageError;

if ($emailConfirmationSent) {
	$as_content['success'] = as_lang_html('users/confirm_emailed');

	$email = as_get_logged_in_email();
	$handle = as_get_logged_in_handle();

	$as_content['form'] = array(
		'tags' => 'method="post" action="' . as_self_html() . '"',

		'style' => 'tall',

		'fields' => array(
			'email' => array(
				'label' => as_lang_html('users/email_label'),
				'value' => as_html($email) . strtr(as_lang_html('users/change_email_link'), array(
						'^1' => '<a href="' . as_path_html('account') . '">',
						'^2' => '</a>',
					)),
				'type' => 'static',
			),
			'code' => array(
				'label' => as_lang_html('users/email_code_label'),
				'tags' => 'name="code" id="code"',
				'value' => isset($code) ? as_html($code) : null,
				'note' => as_lang_html('users/email_code_emailed') . ' - ' .
					'<a href="' . as_path_html('confirm') . '">' . as_lang_html('users/email_code_another') . '</a>',
			),
		),

		'buttons' => array(
			'confirm' => array( // This button does not actually need a name attribute
				'label' => as_lang_html('users/confirm_button'),
			),
		),

		'hidden' => array(
			'formcode' => as_get_form_security_code('confirm'),
			'username' => as_html($handle),
		),
	);

	$as_content['focusid'] = 'code';
} elseif ($userConfirmed) {
	$as_content['success'] = as_lang_html('users/confirm_complete');

	if (!isset($loggedInUserId)) {
		$as_content['suggest_next'] = strtr(
			as_lang_html('users/log_in_to_access'),
			array(
				'^1' => '<a href="' . as_path_html('signin', array('e' => $handle)) . '">',
				'^2' => '</a>',
			)
		);
	}
} elseif (isset($loggedInUserId)) { // if logged in, allow sending a fresh link
	require_once AS_INCLUDE_DIR . 'util/string.php';

	if (strlen($code) > 0) {
		$as_content['error'] = as_lang_html('users/confirm_wrong_resend');
	}

	$email = as_get_logged_in_email();

	$as_content['form'] = array(
		'tags' => 'method="post" action="' . as_path_html('confirm') . '"',

		'style' => 'tall',

		'fields' => array(
			'email' => array(
				'label' => as_lang_html('users/email_label'),
				'value' => as_html($email) . strtr(as_lang_html('users/change_email_link'), array(
						'^1' => '<a href="' . as_path_html('account') . '">',
						'^2' => '</a>',
					)),
				'type' => 'static',
			),
		),

		'buttons' => array(
			'send' => array(
				'tags' => 'name="dosendconfirm"',
				'label' => as_lang_html('users/send_confirm_button'),
			),
		),

		'hidden' => array(
			'formcode' => as_get_form_security_code('confirm'),
		),
	);

	if (!as_email_validate($email)) {
		$as_content['error'] = as_lang_html('users/email_invalid');
		unset($as_content['form']['buttons']['send']);
	}
} else { // User is not logged in
	$as_content['error'] = as_insert_signin_links(as_lang_html('users/confirm_wrong_log_in'), 'confirm');
}

return $as_content;
