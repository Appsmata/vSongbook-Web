<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Controller for signin page


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


if (as_is_logged_in()) {
	as_redirect('');
}

// Check we're not using APS's single-sign on integration and that we're not logged in
if (AS_FINAL_EXTERNAL_USERS) {
	$request = as_request();
	$topath = as_get('to'); // lets user switch between signin and signup without losing destination page
	$userlinks = as_get_signin_links(as_path_to_root(), isset($topath) ? $topath : as_path($request, $_GET, ''));

	if (!empty($userlinks['signin'])) {
		as_redirect_raw($userlinks['signin']);
	}
	as_fatal_error('User signin should be handled by external code');
}


// Process submitted form after checking we haven't reached rate limit

$passwordsent = as_get('ps');
$emailexists = as_get('ee');

$inemailhandle = as_post_text('emailhandle');
$inpassword = as_post_text('password');
$inremember = as_post_text('remember');

if (as_clicked('dosignin') && (strlen($inemailhandle) || strlen($inpassword))) {
	require_once AS_INCLUDE_DIR . 'app/limits.php';

	if (as_user_limits_remaining(AS_LIMIT_LOGINS)) {
		require_once AS_INCLUDE_DIR . 'db/users.php';
		require_once AS_INCLUDE_DIR . 'db/selects.php';

		if (!as_check_form_security_code('signin', as_post_text('code'))) {
			$pageerror = as_lang_html('misc/form_security_again');
		}
		else {
			as_limits_increment(null, AS_LIMIT_LOGINS);

			$errors = array();

			if (as_opt('allow_signin_email_only') || strpos($inemailhandle, '@') !== false) { // handles can't contain @ symbols
				$matchusers = as_db_user_find_by_email($inemailhandle);
			} else {
				$matchusers = as_db_user_find_by_handle($inemailhandle);
			}

			if (count($matchusers) == 1) { // if matches more than one (should be impossible), don't log in
				$inuserid = $matchusers[0];
				$userinfo = as_db_select_with_pending(as_db_user_account_selectspec($inuserid, true));

				$legacyPassOk = hash_equals(strtolower($userinfo['passcheck']), strtolower(as_db_calc_passcheck($inpassword, $userinfo['passsalt'])));

				if (AS_PASSWORD_HASH) {
					$haspassword = isset($userinfo['passhash']);
					$haspasswordold = isset($userinfo['passsalt']) && isset($userinfo['passcheck']);
					$passOk = password_verify($inpassword, $userinfo['passhash']);

					if (($haspasswordold && $legacyPassOk) || ($haspassword && $passOk)) {
						// upgrade password or rehash, when options like the cost parameter changed
						if ($haspasswordold || password_needs_rehash($userinfo['passhash'], PASSWORD_BCRYPT)) {
							as_db_user_set_password($inuserid, $inpassword);
						}
					} else {
						$errors['password'] = as_lang('users/password_wrong');
					}
				} else {
					if (!$legacyPassOk) {
						$errors['password'] = as_lang('users/password_wrong');
					}
				}

				if (!isset($errors['password'])) {
					// signin and redirect
					require_once AS_INCLUDE_DIR . 'app/users.php';
					as_set_logged_in_user($inuserid, $userinfo['handle'], !empty($inremember));

					$topath = as_get('to');

					if (isset($topath))
						as_redirect_raw(as_path_to_root() . $topath); // path already provided as URL fragment
					elseif ($passwordsent)
						as_redirect('account');
					else
						as_redirect('');
				}

			} else {
				$errors['emailhandle'] = as_lang('users/user_not_found');
			}
		}

	} else {
		$pageerror = as_lang('users/signin_limit');
	}

} else {
	$inemailhandle = as_get('e');
}


// Prepare content for theme

$as_content = as_content_prepare();

$as_content['title'] = as_lang_html('users/signin_title');

$as_content['error'] = @$pageerror;

if (empty($inemailhandle) || isset($errors['emailhandle']))
	$forgotpath = as_path('forgot');
else
	$forgotpath = as_path('forgot', array('e' => $inemailhandle));

$forgothtml = '<a href="' . as_html($forgotpath) . '">' . as_lang_html('users/forgot_link') . '</a>';

$as_content['form'] = array(
	'tags' => 'method="post" action="' . as_self_html() . '"',

	'style' => 'tall',

	'ok' => $passwordsent ? as_lang_html('users/password_sent') : ($emailexists ? as_lang_html('users/email_exists') : null),

	'fields' => array(
		'email_handle' => array(
			'label' => as_opt('allow_signin_email_only') ? as_lang_html('users/email_label') : as_lang_html('users/email_handle_label'),
			'tags' => 'name="emailhandle" id="emailhandle" dir="auto"',
			'value' => as_html(@$inemailhandle),
			'error' => as_html(@$errors['emailhandle']),
		),

		'email_handle' => array(
			'label' => as_opt('allow_signin_email_only') ? as_lang_html('users/email_label') : as_lang_html('users/email_handle_label'),
			'tags' => 'name="emailhandle" id="emailhandle" dir="auto"',
			'value' => as_html(@$inemailhandle),
			'error' => as_html(@$errors['emailhandle']),
		),

		'email_handle' => array(
			'label' => as_opt('allow_signin_email_only') ? as_lang_html('users/email_label') : as_lang_html('users/email_handle_label'),
			'tags' => 'name="emailhandle" id="emailhandle" dir="auto"',
			'value' => as_html(@$inemailhandle),
			'error' => as_html(@$errors['emailhandle']),
		),
		
		'email_handle' => array(
			'label' => as_opt('allow_signin_email_only') ? as_lang_html('users/email_label') : as_lang_html('users/email_handle_label'),
			'tags' => 'name="emailhandle" id="emailhandle" dir="auto"',
			'value' => as_html(@$inemailhandle),
			'error' => as_html(@$errors['emailhandle']),
		),

		'password' => array(
			'type' => 'password',
			'label' => as_lang_html('users/password_label'),
			'tags' => 'name="password" id="password" dir="auto"',
			'value' => as_html(@$inpassword),
			'error' => empty($errors['password']) ? '' : (as_html(@$errors['password']) . ' - ' . $forgothtml),
			'note' => $passwordsent ? as_lang_html('users/password_sent') : $forgothtml,
		),

		'remember' => array(
			'type' => 'checkbox',
			'label' => as_lang_html('users/remember_label'),
			'tags' => 'name="remember"',
			'value' => !empty($inremember),
		),
	),

	'buttons' => array(
		'signin' => array(
			'label' => as_lang_html('users/signin_button'),
		),
	),

	'hidden' => array(
		'dosignin' => '1',
		'code' => as_get_form_security_code('signin'),
	),

	'links' => array(		
		'signup' => array(
			'url' => 'signup',
			'label' => as_lang_html('users/signup_title'),
		),
	)
);

$signinmodules = as_load_modules_with('signin', 'signin_html');

foreach ($signinmodules as $module) {
	ob_start();
	$module->signin_html(as_opt('site_url') . as_get('to'), 'signin');
	$html = ob_get_clean();

	if (strlen($html))
		@$as_content['custom'] .= '<br>' . $html . '<br>';
}

$as_content['focusid'] = (isset($inemailhandle) && !isset($errors['emailhandle'])) ? 'password' : 'emailhandle';


return $as_content;
