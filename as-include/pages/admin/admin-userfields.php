<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Controller for admin page for editing custom user fields


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


// Get current list of user fields and determine the state of this admin page

$fieldid = as_post_text('edit');
if (!isset($fieldid))
	$fieldid = as_get('edit');

$userfields = as_db_select_with_pending(as_db_userfields_selectspec());

$editfield = null;
foreach ($userfields as $userfield) {
	if ($userfield['fieldid'] == $fieldid)
		$editfield = $userfield;
}


// Check admin privileges (do late to allow one DB query)

if (!as_admin_check_privileges($as_content))
	return $as_content;


// Process saving an old or new user field

$securityexpired = false;

if (as_clicked('docancel'))
	as_redirect('admin/users');

elseif (as_clicked('dosavefield')) {
	require_once AS_INCLUDE_DIR . 'db/admin.php';
	require_once AS_INCLUDE_DIR . 'util/string.php';

	if (!as_check_form_security_code('admin/userfields', as_post_text('code')))
		$securityexpired = true;

	else {
		if (as_post_text('dodelete')) {
			as_db_userfield_delete($editfield['fieldid']);
			as_redirect('admin/users');

		} else {
			$inname = as_post_text('name');
			$intype = as_post_text('type');
			$inonsignup = (int)as_post_text('onsignup');
			$inflags = $intype | ($inonsignup ? AS_FIELD_FLAGS_ON_REGISTER : 0);
			$inposition = as_post_text('position');
			$inpermit = (int)as_post_text('permit');

			$errors = array();

			// Verify the name is legitimate

			if (as_strlen($inname) > AS_DB_MAX_PROFILE_TITLE_LENGTH)
				$errors['name'] = as_lang_sub('main/max_length_x', AS_DB_MAX_PROFILE_TITLE_LENGTH);

			// Perform appropriate database action

			if (isset($editfield['fieldid'])) { // changing existing user field
				as_db_userfield_set_fields($editfield['fieldid'], isset($errors['name']) ? $editfield['content'] : $inname, $inflags, $inpermit);
				as_db_userfield_move($editfield['fieldid'], $inposition);

				if (empty($errors))
					as_redirect('admin/users');

				else {
					$userfields = as_db_select_with_pending(as_db_userfields_selectspec()); // reload after changes
					foreach ($userfields as $userfield)
						if ($userfield['fieldid'] == $editfield['fieldid'])
							$editfield = $userfield;
				}

			} elseif (empty($errors)) { // creating a new user field
				for ($attempt = 0; $attempt < 1000; $attempt++) {
					$suffix = $attempt ? ('-' . (1 + $attempt)) : '';
					$newtag = as_substr(implode('-', as_string_to_words($inname)), 0, AS_DB_MAX_PROFILE_TITLE_LENGTH - strlen($suffix)) . $suffix;
					$uniquetag = true;

					foreach ($userfields as $userfield) {
						if (as_strtolower(trim($newtag)) == as_strtolower(trim($userfield['title'])))
							$uniquetag = false;
					}

					if ($uniquetag) {
						$fieldid = as_db_userfield_create($newtag, $inname, $inflags, $inpermit);
						as_db_userfield_move($fieldid, $inposition);
						as_redirect('admin/users');
					}
				}

				as_fatal_error('Could not create a unique database tag');
			}
		}
	}
}


// Prepare content for theme

$as_content = as_content_prepare();

$as_content['title'] = as_lang_html('admin/admin_title') . ' - ' . as_lang_html('admin/users_title');
$as_content['error'] = $securityexpired ? as_lang_html('admin/form_security_expired') : as_admin_page_error();

$positionoptions = array();
$previous = null;
$passedself = false;

foreach ($userfields as $userfield) {
	if (isset($previous))
		$positionhtml = as_lang_html_sub('admin/after_x', as_html(as_user_userfield_label($passedself ? $userfield : $previous)));
	else
		$positionhtml = as_lang_html('admin/first');

	$positionoptions[$userfield['position']] = $positionhtml;

	if ($userfield['fieldid'] == @$editfield['fieldid'])
		$passedself = true;

	$previous = $userfield;
}

if (isset($editfield['position']))
	$positionvalue = $positionoptions[$editfield['position']];
else {
	$positionvalue = isset($previous) ? as_lang_html_sub('admin/after_x', as_html(as_user_userfield_label($previous))) : as_lang_html('admin/first');
	$positionoptions[1 + @max(array_keys($positionoptions))] = $positionvalue;
}

$typeoptions = array(
	0 => as_lang_html('admin/field_single_line'),
	AS_FIELD_FLAGS_MULTI_LINE => as_lang_html('admin/field_multi_line'),
	AS_FIELD_FLAGS_LINK_URL => as_lang_html('admin/field_link_url'),
);

$permitoptions = as_admin_permit_options(AS_PERMIT_ALL, AS_PERMIT_ADMINS, false, false);
$permitvalue = @$permitoptions[isset($inpermit) ? $inpermit : $editfield['permit']];

$as_content['form'] = array(
	'tags' => 'method="post" action="' . as_path_html(as_request()) . '"',

	'style' => 'tall',

	'fields' => array(
		'name' => array(
			'tags' => 'name="name" id="name"',
			'label' => as_lang_html('admin/field_name'),
			'value' => as_html(isset($inname) ? $inname : as_user_userfield_label($editfield)),
			'error' => as_html(@$errors['name']),
		),

		'delete' => array(
			'tags' => 'name="dodelete" id="dodelete"',
			'label' => as_lang_html('admin/delete_field'),
			'value' => 0,
			'type' => 'checkbox',
		),

		'type' => array(
			'id' => 'type_display',
			'tags' => 'name="type"',
			'label' => as_lang_html('admin/field_type'),
			'type' => 'select',
			'options' => $typeoptions,
			'value' => @$typeoptions[isset($intype) ? $intype : (@$editfield['flags'] & (AS_FIELD_FLAGS_MULTI_LINE | AS_FIELD_FLAGS_LINK_URL))],
		),

		'permit' => array(
			'id' => 'permit_display',
			'tags' => 'name="permit"',
			'label' => as_lang_html('admin/permit_to_view'),
			'type' => 'select',
			'options' => $permitoptions,
			'value' => $permitvalue,
		),

		'position' => array(
			'id' => 'position_display',
			'tags' => 'name="position"',
			'label' => as_lang_html('admin/position'),
			'type' => 'select',
			'options' => $positionoptions,
			'value' => $positionvalue,
		),

		'onsignup' => array(
			'id' => 'signup_display',
			'tags' => 'name="onsignup"',
			'label' => as_lang_html('admin/show_on_signup_form'),
			'type' => 'checkbox',
			'value' => isset($inonsignup) ? $inonsignup : (@$editfield['flags'] & AS_FIELD_FLAGS_ON_REGISTER),
		),
	),

	'buttons' => array(
		'save' => array(
			'label' => as_lang_html(isset($editfield['fieldid']) ? 'main/save_button' : ('admin/add_field_button')),
		),

		'cancel' => array(
			'tags' => 'name="docancel"',
			'label' => as_lang_html('main/cancel_button'),
		),
	),

	'hidden' => array(
		'dosavefield' => '1', // for IE
		'edit' => @$editfield['fieldid'],
		'code' => as_get_form_security_code('admin/userfields'),
	),
);

if (isset($editfield['fieldid'])) {
	as_set_display_rules($as_content, array(
		'type_display' => '!dodelete',
		'position_display' => '!dodelete',
		'signup_display' => '!dodelete',
		'permit_display' => '!dodelete',
	));
} else {
	unset($as_content['form']['fields']['delete']);
}

$as_content['focusid'] = 'name';

$as_content['navigation']['sub'] = as_admin_sub_navigation();


return $as_content;
