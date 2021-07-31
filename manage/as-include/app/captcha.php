<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Wrapper functions and utilities for captcha modules


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


/**
 * Return whether a captcha module has been selected and it indicates that it is fully set up to go.
 */
function as_captcha_available()
{
	$module = as_load_module('captcha', as_opt('captcha_module'));

	return isset($module) && (!method_exists($module, 'allow_captcha') || $module->allow_captcha());
}


/**
 * Return an HTML string explaining $captchareason (from as_user_captcha_reason()) to the user about why they are seeing a captcha
 * @param $captchareason
 * @return mixed|null|string
 */
function as_captcha_reason_note($captchareason)
{
	$notehtml = null;

	switch ($captchareason) {
		case 'signin':
			$notehtml = as_insert_signin_links(as_lang_html('misc/captcha_signin_fix'));
			break;

		case 'confirm':
			$notehtml = as_insert_signin_links(as_lang_html('misc/captcha_confirm_fix'));
			break;

		case 'approve':
			$notehtml = as_lang_html('misc/captcha_approve_fix');
			break;
	}

	return $notehtml;
}


/**
 * Prepare $as_content for showing a captcha, adding the element to $fields, given previous $errors, and a $note to display.
 * Returns JavaScript required to load CAPTCHA when field is shown by user (e.g. clicking comment button).
 * @param $as_content
 * @param $fields
 * @param $errors
 * @param $note
 * @return string
 */
function as_set_up_captcha_field(&$as_content, &$fields, $errors, $note = null)
{
	if (!as_captcha_available())
		return '';

	$captcha = as_load_module('captcha', as_opt('captcha_module'));

	// workaround for reCAPTCHA, to load multiple instances via JS
	$count = @++$as_content['as_captcha_count'];

	if ($count > 1) {
		// use blank captcha in order to load via JS
		$html = '';
	} else {
		// first captcha is always loaded explicitly
		$as_content['script_var']['as_captcha_in'] = 'as_captcha_div_1';
		$html = $captcha->form_html($as_content, @$errors['captcha']);
	}

	$fields['captcha'] = array(
		'type' => 'custom',
		'label' => as_lang_html('misc/captcha_label'),
		'html' => '<div id="as_captcha_div_' . $count . '">' . $html . '</div>',
		'error' => @array_key_exists('captcha', $errors) ? as_lang_html('misc/captcha_error') : null,
		'note' => $note,
	);

	return "if (!document.getElementById('as_captcha_div_" . $count . "').hasChildNodes()) { recaptcha_load('as_captcha_div_" . $count . "'); }";
}


/**
 * Check if captcha is submitted correctly, and if not, set $errors['captcha'] to a descriptive string.
 * @param $errors
 * @return bool
 */
function as_captcha_validate_post(&$errors)
{
	if (as_captcha_available()) {
		$captcha = as_load_module('captcha', as_opt('captcha_module'));

		if (!$captcha->validate_post($error)) {
			$errors['captcha'] = $error;
			return false;
		}
	}

	return true;
}
