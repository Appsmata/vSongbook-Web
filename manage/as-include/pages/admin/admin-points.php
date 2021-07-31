<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Controller for admin page for settings about user points


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

require_once AS_INCLUDE_DIR . 'db/recalc.php';
require_once AS_INCLUDE_DIR . 'db/points.php';
require_once AS_INCLUDE_DIR . 'app/options.php';
require_once AS_INCLUDE_DIR . 'app/admin.php';
require_once AS_INCLUDE_DIR . 'util/sort.php';


// Check admin privileges

if (!as_admin_check_privileges($as_content)) {
	return $as_content;
}


// Process user actions

$securityexpired = false;
$recalculate = false;
$optionnames = as_db_points_option_names();

if (as_clicked('doshowdefaults')) {
	$options = array();

	foreach ($optionnames as $optionname) {
		$options[$optionname] = as_default_option($optionname);
	}
} else {
	if (as_clicked('dosaverecalc')) {
		if (!as_check_form_security_code('admin/points', as_post_text('code'))) {
			$securityexpired = true;
		} else {
			foreach ($optionnames as $optionname) {
				as_set_option($optionname, (int)as_post_text('option_' . $optionname));
			}

			if (!as_post_text('has_js')) {
				as_redirect('admin/recalc', array('dorecalcpoints' => 1));
			} else {
				$recalculate = true;
			}
		}
	}

	$options = as_get_options($optionnames);
}


// Prepare content for theme

$as_content = as_content_prepare();

$as_content['title'] = as_lang_html('admin/admin_title') . ' - ' . as_lang_html('admin/points_title');
$as_content['error'] = $securityexpired ? as_lang_html('admin/form_security_expired') : as_admin_page_error();

$as_content['form'] = array(
	'tags' => 'method="post" action="' . as_self_html() . '" name="points_form" onsubmit="document.forms.points_form.has_js.value=1; return true;"',

	'style' => 'wide',

	'buttons' => array(
		'saverecalc' => array(
			'tags' => 'id="dosaverecalc"',
			'label' => as_lang_html('admin/save_recalc_button'),
		),
	),

	'hidden' => array(
		'dosaverecalc' => '1',
		'has_js' => '0',
		'code' => as_get_form_security_code('admin/points'),
	),
);


if (as_clicked('doshowdefaults')) {
	$as_content['form']['ok'] = as_lang_html('admin/points_defaults_shown');

	$as_content['form']['buttons']['cancel'] = array(
		'tags' => 'name="docancel"',
		'label' => as_lang_html('main/cancel_button'),
	);
} else {
	if ($recalculate) {
		$as_content['form']['ok'] = '<span id="recalc_ok"></span>';
		$as_content['form']['hidden']['code_recalc'] = as_get_form_security_code('admin/recalc');

		$as_content['script_rel'][] = 'as-content/as-admin.js?' . AS_VERSION;
		$as_content['script_var']['as_warning_recalc'] = as_lang('admin/stop_recalc_warning');

		$as_content['script_onloads'][] = array(
			"as_recalc_click('dorecalcpoints', document.getElementById('dosaverecalc'), null, 'recalc_ok');"
		);
	}

	$as_content['form']['buttons']['showdefaults'] = array(
		'tags' => 'name="doshowdefaults"',
		'label' => as_lang_html('admin/show_defaults_button'),
	);
}


foreach ($optionnames as $optionname) {
	$optionfield = array(
		'label' => as_lang_html('options/' . $optionname),
		'tags' => 'name="option_' . $optionname . '"',
		'value' => as_html($options[$optionname]),
		'type' => 'number',
		'note' => as_lang_html('admin/points'),
	);

	switch ($optionname) {
		case 'points_multiple':
			$prefix = '&#215;';
			unset($optionfield['note']);
			break;

		case 'points_per_q_thumbd_up':
		case 'points_per_a_thumbd_up':
		case 'points_per_c_thumbd_up':
		case 'points_q_thumbd_max_gain':
		case 'points_a_thumbd_max_gain':
		case 'points_c_thumbd_max_gain':
			$prefix = '+';
			break;

		case 'points_per_q_thumbd_down':
		case 'points_per_a_thumbd_down':
		case 'points_per_c_thumbd_down':
		case 'points_q_thumbd_max_loss':
		case 'points_a_thumbd_max_loss':
		case 'points_c_thumbd_max_loss':
			$prefix = '&ndash;';
			break;

		case 'points_base':
			$prefix = '+';
			break;

		default:
			$prefix = '<span style="visibility:hidden;">+</span>'; // for even alignment
			break;
	}

	$optionfield['prefix'] = '<span style="width:1em; display:inline-block; display:-moz-inline-stack;">' . $prefix . '</span>';

	$as_content['form']['fields'][$optionname] = $optionfield;
}

as_array_insert($as_content['form']['fields'], 'points_post_a', array('blank0' => array('type' => 'blank')));
as_array_insert($as_content['form']['fields'], 'points_per_c_thumbd_up', array('blank1' => array('type' => 'blank')));
as_array_insert($as_content['form']['fields'], 'points_thumb_up_q', array('blank2' => array('type' => 'blank')));
as_array_insert($as_content['form']['fields'], 'points_multiple', array('blank3' => array('type' => 'blank')));


$as_content['navigation']['sub'] = as_admin_sub_navigation();


return $as_content;
