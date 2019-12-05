<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Controller for feedback page


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

require_once AS_INCLUDE_DIR . 'app/captcha.php';
require_once AS_INCLUDE_DIR . 'db/selects.php';


// Get useful information on the logged in user

$userid = as_get_logged_in_userid();

if (isset($userid) && !AS_FINAL_EXTERNAL_USERS) {
	list($useraccount, $userprofile) = as_db_select_with_pending(
		as_db_user_account_selectspec($userid, true),
		as_db_user_profile_selectspec($userid, true)
	);
}

$usecaptcha = as_opt('captcha_on_feedback') && as_user_use_captcha();


// Check feedback is enabled and the person isn't blocked

if (!as_opt('feedback_enabled'))
	return include AS_INCLUDE_DIR . 'as-page-not-found.php';

if (as_user_permit_error()) {
	$as_content = as_content_prepare();
	$as_content['error'] = as_lang_html('users/no_permission');
	return $as_content;
}


// Send the feedback form


$feedbacksent = false;

if (as_clicked('dofeedback')) {
	require_once AS_INCLUDE_DIR . 'app/emails.php';
	require_once AS_INCLUDE_DIR . 'util/string.php';

	$inmessage = as_post_text('message');
	$inname = as_post_text('name');
	$inemail = as_post_text('email');
	$inreferer = as_post_text('referer');

	if (!as_check_form_security_code('feedback', as_post_text('code')))
		$pageerror = as_lang_html('misc/form_security_again');

	else {
		if (empty($inmessage))
			$errors['message'] = as_lang('misc/feedback_empty');

		if ($usecaptcha)
			as_captcha_validate_post($errors);

		if (empty($errors)) {
			$subs = array(
				'^message' => $inmessage,
				'^name' => empty($inname) ? '-' : $inname,
				'^email' => empty($inemail) ? '-' : $inemail,
				'^previous' => empty($inreferer) ? '-' : $inreferer,
				'^url' => isset($userid) ? as_path_absolute('user/' . as_get_logged_in_handle()) : '-',
				'^ip' => as_remote_ip_address(),
				'^browser' => @$_SERVER['HTTP_USER_AGENT'],
			);

			if (as_send_email(array(
				'fromemail' => as_opt('from_email'),
				'fromname' => $inname,
				'replytoemail' => as_email_validate(@$inemail) ? $inemail : null,
				'replytoname' => $inname,
				'toemail' => as_opt('feedback_email'),
				'toname' => as_opt('site_title'),
				'subject' => as_lang_sub('emails/feedback_subject', as_opt('site_title')),
				'body' => strtr(as_lang('emails/feedback_body'), $subs),
				'html' => false,
			))) {
				$feedbacksent = true;
			} else {
				$pageerror = as_lang_html('main/general_error');
			}

			as_report_event('feedback', $userid, as_get_logged_in_handle(), as_cookie_get(), array(
				'email' => $inemail,
				'name' => $inname,
				'message' => $inmessage,
				'previous' => $inreferer,
				'browser' => @$_SERVER['HTTP_USER_AGENT'],
			));
		}
	}
}


// Prepare content for theme

$as_content = as_content_prepare();

$as_content['title'] = as_lang_html('misc/feedback_title');

$as_content['error'] = @$pageerror;

$as_content['form'] = array(
	'tags' => 'method="post" action="' . as_self_html() . '"',

	'style' => 'tall',

	'fields' => array(
		'message' => array(
			'type' => $feedbacksent ? 'static' : '',
			'label' => as_lang_html_sub('misc/feedback_message', as_opt('site_title')),
			'tags' => 'name="message" id="message"',
			'value' => as_html(@$inmessage),
			'rows' => 8,
			'error' => as_html(@$errors['message']),
		),

		'name' => array(
			'type' => $feedbacksent ? 'static' : '',
			'label' => as_lang_html('misc/feedback_name'),
			'tags' => 'name="name"',
			'value' => as_html(isset($inname) ? $inname : @$userprofile['name']),
		),

		'email' => array(
			'type' => $feedbacksent ? 'static' : '',
			'label' => as_lang_html('misc/feedback_email'),
			'tags' => 'name="email"',
			'value' => as_html(isset($inemail) ? $inemail : as_get_logged_in_email()),
			'note' => $feedbacksent ? null : as_opt('email_privacy'),
		),
	),

	'buttons' => array(
		'send' => array(
			'label' => as_lang_html('main/send_button'),
		),
	),

	'hidden' => array(
		'dofeedback' => '1',
		'code' => as_get_form_security_code('feedback'),
		'referer' => as_html(isset($inreferer) ? $inreferer : @$_SERVER['HTTP_REFERER']),
	),
);

if ($usecaptcha && !$feedbacksent)
	as_set_up_captcha_field($as_content, $as_content['form']['fields'], @$errors);


$as_content['focusid'] = 'message';

if ($feedbacksent) {
	$as_content['form']['ok'] = as_lang_html('misc/feedback_sent');
	unset($as_content['form']['buttons']);
}


return $as_content;
