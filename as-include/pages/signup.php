<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/appsmata/

	Description: Controller for signup page


	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	More about this license: http://github.com/appsmata/license.php
*/

if (!defined('AS_VERSION')) { // don't allow this page to be requested directly from browser
	header('Location: ../../');
	exit;
}

require_once AS_INCLUDE_DIR . 'app/captcha.php';
require_once AS_INCLUDE_DIR . 'db/users.php';


if (as_is_logged_in()) {
	as_redirect('');
}

// Check we're not using single-sign on integration, that we're not logged in, and we're not blocked
if (AS_FINAL_EXTERNAL_USERS) {
	$request = as_request();
	$topath = as_get('to'); // lets user switch between signin and signup without losing destination page
	$userlinks = as_get_signin_links(as_path_to_root(), isset($topath) ? $topath : as_path($request, $_GET, ''));

	if (!empty($userlinks['signup'])) {
		as_redirect_raw($userlinks['signup']);
	}
	as_fatal_error('User registration should be handled by external code');
}


// Get information about possible additional fields

$show_terms = as_opt('show_signup_terms');

$userfields = as_db_select_with_pending(
	as_db_userfields_selectspec()
);

foreach ($userfields as $index => $userfield) {
	if (!($userfield['flags'] & AS_FIELD_FLAGS_ON_REGISTER))
		unset($userfields[$index]);
}


// Check we haven't suspended registration, and this IP isn't blocked

if (as_opt('suspend_signup_users')) {
	$as_content = as_content_prepare();
	$as_content['error'] = as_lang_html('users/signup_suspended');
	return $as_content;
}

if (as_user_permit_error()) {
	$as_content = as_content_prepare();
	$as_content['error'] = as_lang_html('users/no_permission');
	return $as_content;
}


// Process submitted form

if (as_clicked('dosignup')) {
	require_once AS_INCLUDE_DIR . 'app/limits.php';

	if (as_user_limits_remaining(AS_LIMIT_REGISTRATIONS)) {
		require_once AS_INCLUDE_DIR . 'app/users-edit.php';

		$inemail = as_post_text('email');
		$inpassword = as_post_text('password');
		$inhandle = as_post_text('handle');
		$interms = (int)as_post_text('terms');

		$inprofile = array();
		foreach ($userfields as $userfield)
			$inprofile[$userfield['fieldid']] = as_post_text('field_' . $userfield['fieldid']);

		if (!as_check_form_security_code('signup', as_post_text('code'))) {
			$pageerror = as_lang_html('misc/form_security_again');
		} else {
			// core validation
			$errors = array_merge(
				as_handle_email_filter($inhandle, $inemail),
				as_password_validate($inpassword)
			);

			// T&Cs validation
			if ($show_terms && !$interms)
				$errors['terms'] = as_lang_html('users/terms_not_accepted');

			// filter module validation
			if (count($inprofile)) {
				$filtermodules = as_load_modules_with('filter', 'filter_profile');
				foreach ($filtermodules as $filtermodule)
					$filtermodule->filter_profile($inprofile, $errors, null, null);
			}

			if (as_opt('captcha_on_signup'))
				as_captcha_validate_post($errors);

			if (empty($errors)) {
				// signup and redirect
				as_limits_increment(null, AS_LIMIT_REGISTRATIONS);

				$userid = as_create_new_user($infirstname, $inlastname, $incountry, $inmobile, $ingender, $incity, $inchurch, $inhandle, $inemail, $inpassword);

				foreach ($userfields as $userfield)
					as_db_user_profile_set($userid, $userfield['title'], $inprofile[$userfield['fieldid']]);

				as_set_logged_in_user($userid, $inhandle);

				$topath = as_get('to');

				if (isset($topath))
					as_redirect_raw(as_path_to_root() . $topath); // path already provided as URL fragment
				else
					as_redirect('');
			}
		}

	} else
		$pageerror = as_lang('users/signup_limit');
}


// Prepare content for theme

$as_content = as_content_prepare();

$as_content['title'] = as_lang_html('users/signup_title');

$as_content['error'] = @$pageerror;

$as_content['form'] = array(
	'tags' => 'method="post" action="' . as_self_html() . '"',

	'style' => 'tall',

	'fields' => array(
		'handle' => array(
			'label' => as_lang_html('users/handle_label'),
			'tags' => 'name="handle" id="handle" dir="auto"',
			'value' => as_html(@$inhandle),
			'error' => as_html(@$errors['handle']),
		),

		'password' => array(
			'type' => 'password',
			'label' => as_lang_html('users/password_label'),
			'tags' => 'name="password" id="password" dir="auto"',
			'value' => as_html(@$inpassword),
			'error' => as_html(@$errors['password']),
		),

		'email' => array(
			'label' => as_lang_html('users/email_label'),
			'tags' => 'name="email" id="email" dir="auto"',
			'value' => as_html(@$inemail),
			'note' => as_opt('email_privacy'),
			'error' => as_html(@$errors['email']),
		),
	),

	'buttons' => array(
		'signup' => array(
			'tags' => 'onclick="as_show_waiting_after(this, false);"',
			'label' => as_lang_html('users/signup_button'),
		),
	),

	'hidden' => array(
		'dosignup' => '1',
		'code' => as_get_form_security_code('signup'),
	),
);

// prepend custom message
$custom = as_opt('show_custom_signup') ? trim(as_opt('custom_signup')) : '';
if (strlen($custom)) {
	array_unshift($as_content['form']['fields'], array(
		'type' => 'custom',
		'note' => $custom,
	));
}

foreach ($userfields as $userfield) {
	$value = @$inprofile[$userfield['fieldid']];

	$label = trim(as_user_userfield_label($userfield), ':');
	if (strlen($label))
		$label .= ':';

	$as_content['form']['fields'][$userfield['title']] = array(
		'label' => as_html($label),
		'tags' => 'name="field_' . $userfield['fieldid'] . '"',
		'value' => as_html($value),
		'error' => as_html(@$errors[$userfield['fieldid']]),
		'rows' => ($userfield['flags'] & AS_FIELD_FLAGS_MULTI_LINE) ? 8 : null,
	);
}

if (as_opt('captcha_on_signup'))
	as_set_up_captcha_field($as_content, $as_content['form']['fields'], @$errors);

// show T&Cs checkbox
if ($show_terms) {
	$as_content['form']['fields']['terms'] = array(
		'type' => 'checkbox',
		'label' => trim(as_opt('signup_terms')),
		'tags' => 'name="terms" id="terms"',
		'value' => as_html(@$interms),
		'error' => as_html(@$errors['terms']),
	);
}

$signinmodules = as_load_modules_with('signin', 'signin_html');

foreach ($signinmodules as $module) {
	ob_start();
	$module->signin_html(as_opt('site_url') . as_get('to'), 'signup');
	$html = ob_get_clean();

	if (strlen($html))
		@$as_content['custom'] .= '<br>' . $html . '<br>';
}

// prioritize 'handle' for keyboard focus
$as_content['focusid'] = isset($errors['handle']) ? 'handle'
	: (isset($errors['password']) ? 'password'
		: (isset($errors['email']) ? 'email' : 'handle'));


return $as_content;
