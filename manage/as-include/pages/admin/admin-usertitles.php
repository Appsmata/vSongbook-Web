<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Controller for admin page for editing custom user titles


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


// Get current list of user titles and determine the state of this admin page

$oldpoints = as_post_text('edit');
if (!isset($oldpoints))
	$oldpoints = as_get('edit');

$pointstitle = as_get_points_to_titles();


// Check admin privileges (do late to allow one DB query)

if (!as_admin_check_privileges($as_content))
	return $as_content;


// Process saving an old or new user title

$securityexpired = false;

if (as_clicked('docancel'))
	as_redirect('admin/users');

elseif (as_clicked('dosavetitle')) {
	require_once AS_INCLUDE_DIR . 'util/string.php';

	if (!as_check_form_security_code('admin/usertitles', as_post_text('code')))
		$securityexpired = true;

	else {
		if (as_post_text('dodelete')) {
			unset($pointstitle[$oldpoints]);

		} else {
			$intitle = as_post_text('title');
			$inpoints = as_post_text('points');

			$errors = array();

			// Verify the title and points are legitimate

			if (!strlen($intitle))
				$errors['title'] = as_lang('main/field_required');

			if (!is_numeric($inpoints))
				$errors['points'] = as_lang('main/field_required');
			else {
				$inpoints = (int)$inpoints;

				if (isset($pointstitle[$inpoints]) && ((!strlen(@$oldpoints)) || ($inpoints != $oldpoints)))
					$errors['points'] = as_lang('admin/title_already_used');
			}

			// Perform appropriate action

			if (isset($pointstitle[$oldpoints])) { // changing existing user title
				$newpoints = isset($errors['points']) ? $oldpoints : $inpoints;
				$newtitle = isset($errors['title']) ? $pointstitle[$oldpoints] : $intitle;

				unset($pointstitle[$oldpoints]);
				$pointstitle[$newpoints] = $newtitle;

			} elseif (empty($errors)) // creating a new user title
				$pointstitle[$inpoints] = $intitle;
		}

		// Save the new option value

		krsort($pointstitle, SORT_NUMERIC);

		$option = '';
		foreach ($pointstitle as $points => $title)
			$option .= (strlen($option) ? ',' : '') . $points . ' ' . $title;

		as_set_option('points_to_titles', $option);

		if (empty($errors))
			as_redirect('admin/users');
	}
}


// Prepare content for theme

$as_content = as_content_prepare();

$as_content['title'] = as_lang_html('admin/admin_title') . ' - ' . as_lang_html('admin/users_title');
$as_content['error'] = $securityexpired ? as_lang_html('admin/form_security_expired') : as_admin_page_error();

$as_content['form'] = array(
	'tags' => 'method="post" action="' . as_path_html(as_request()) . '"',

	'style' => 'tall',

	'fields' => array(
		'title' => array(
			'tags' => 'name="title" id="title"',
			'label' => as_lang_html('admin/user_title'),
			'value' => as_html(isset($intitle) ? $intitle : @$pointstitle[$oldpoints]),
			'error' => as_html(@$errors['title']),
		),

		'delete' => array(
			'tags' => 'name="dodelete" id="dodelete"',
			'label' => as_lang_html('admin/delete_title'),
			'value' => 0,
			'type' => 'checkbox',
		),

		'points' => array(
			'id' => 'points_display',
			'tags' => 'name="points"',
			'label' => as_lang_html('admin/points_required'),
			'type' => 'number',
			'value' => as_html(isset($inpoints) ? $inpoints : @$oldpoints),
			'error' => as_html(@$errors['points']),
		),
	),

	'buttons' => array(
		'save' => array(
			'label' => as_lang_html(isset($pointstitle[$oldpoints]) ? 'main/save_button' : ('admin/add_title_button')),
		),

		'cancel' => array(
			'tags' => 'name="docancel"',
			'label' => as_lang_html('main/cancel_button'),
		),
	),

	'hidden' => array(
		'dosavetitle' => '1', // for IE
		'edit' => @$oldpoints,
		'code' => as_get_form_security_code('admin/usertitles'),
	),
);

if (isset($pointstitle[$oldpoints])) {
	as_set_display_rules($as_content, array(
		'points_display' => '!dodelete',
	));
} else {
	unset($as_content['form']['fields']['delete']);
}

$as_content['focusid'] = 'title';

$as_content['navigation']['sub'] = as_admin_sub_navigation();


return $as_content;
