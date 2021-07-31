<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Controller for admin page for editing widgets


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


// Get current list of widgets and determine the state of this admin page

$widgetid = as_post_text('edit');
if (!strlen($widgetid))
	$widgetid = as_get('edit');

list($widgets, $pages) = as_db_select_with_pending(
	as_db_widgets_selectspec(),
	as_db_pages_selectspec()
);

if (isset($widgetid)) {
	$editwidget = null;
	foreach ($widgets as $widget) {
		if ($widget['widgetid'] == $widgetid)
			$editwidget = $widget;
	}

} else {
	$editwidget = array('title' => as_post_text('title'));
	if (!isset($editwidget['title']))
		$editwidget['title'] = as_get('title');
}

$module = as_load_module('widget', @$editwidget['title']);

$widgetfound = isset($module);


// Check admin privileges (do late to allow one DB query)

if (!as_admin_check_privileges($as_content))
	return $as_content;


// Define an array of relevant templates we can use

$templatelangkeys = array(
	'song' => 'admin/song_pages',

	'as' => 'main/recent_qs_as_title',
	'activity' => 'main/recent_activity_title',
	'songs' => 'admin/song_lists',
	'hot' => 'main/hot_qs_title',
	'unreviewed' => 'main/unreviewed_qs_title',

	'tags' => 'main/popular_tags',
	'categories' => 'misc/browse_categories',
	'users' => 'main/highest_users',
	'post' => 'song/post_title',

	'tag' => 'admin/tag_pages',
	'user' => 'admin/user_pages',
	'message' => 'misc/private_message_title',

	'search' => 'main/search_title',
	'feedback' => 'misc/feedback_title',

	'signin' => 'users/signin_title',
	'signup' => 'users/signup_title',
	'account' => 'profile/my_account_title',
	'favorites' => 'misc/my_favorites_title',
	'updates' => 'misc/recent_updates_title',

	'ip' => 'admin/ip_address_pages',
	'admin' => 'admin/admin_title',
);

$templateoptions = array();

if (isset($module) && method_exists($module, 'allow_template')) {
	foreach ($templatelangkeys as $template => $langkey) {
		if ($module->allow_template($template))
			$templateoptions[$template] = as_lang_html($langkey);
	}

	if ($module->allow_template('custom')) {
		$pagemodules = as_load_modules_with('page', 'match_request');
		foreach ($pages as $page) {
			// check if this is a page plugin by fetching all plugin classes and matching requests - currently quite convoluted!
			$isPagePlugin = false;
			foreach ($pagemodules as $pagemodule) {
				if ($pagemodule->match_request($page['tags'])) {
					$isPagePlugin = true;
				}
			}

			if ($isPagePlugin || !($page['flags'] & AS_PAGE_FLAGS_EXTERNAL))
				$templateoptions['custom-' . $page['pageid']] = as_html($page['title']);
		}

	}
}


// Process saving an old or new widget

$securityexpired = false;

if (as_clicked('docancel'))
	as_redirect('admin/layout');

elseif (as_clicked('dosavewidget')) {
	require_once AS_INCLUDE_DIR . 'db/admin.php';

	if (!as_check_form_security_code('admin/widgets', as_post_text('code')))
		$securityexpired = true;

	else {
		if (as_post_text('dodelete')) {
			as_db_widget_delete($editwidget['widgetid']);
			as_redirect('admin/layout');

		} else {
			if ($widgetfound) {
				$intitle = as_post_text('title');
				$inposition = as_post_text('position');
				$intemplates = array();

				if (as_post_text('template_all'))
					$intemplates[] = 'all';

				foreach (array_keys($templateoptions) as $template) {
					if (as_post_text('template_' . $template))
						$intemplates[] = $template;
				}

				$intags = implode(',', $intemplates);

				// Perform appropriate database action

				if (isset($editwidget['widgetid'])) { // changing existing widget
					$widgetid = $editwidget['widgetid'];
					as_db_widget_set_fields($widgetid, $intags);

				} else
					$widgetid = as_db_widget_create($intitle, $intags);

				as_db_widget_move($widgetid, substr($inposition, 0, 2), substr($inposition, 2));
			}

			as_redirect('admin/layout');
		}
	}
}


// Prepare content for theme

$as_content = as_content_prepare();

$as_content['title'] = as_lang_html('admin/admin_title') . ' - ' . as_lang_html('admin/layout_title');
$as_content['error'] = $securityexpired ? as_lang_html('admin/form_security_expired') : as_admin_page_error();

$positionoptions = array();

$placeoptionhtml = as_admin_place_options();

$regioncodes = array(
	'F' => 'full',
	'M' => 'main',
	'S' => 'side',
);

foreach ($placeoptionhtml as $place => $optionhtml) {
	$region = $regioncodes[substr($place, 0, 1)];

	$widgetallowed = method_exists($module, 'allow_region') && $module->allow_region($region);

	if ($widgetallowed) {
		foreach ($widgets as $widget) {
			if ($widget['place'] == $place && $widget['title'] == $editwidget['title'] && $widget['widgetid'] !== @$editwidget['widgetid'])
				$widgetallowed = false; // don't allow two instances of same widget in same place
		}
	}

	if ($widgetallowed) {
		$previous = null;
		$passedself = false;
		$maxposition = 0;

		foreach ($widgets as $widget) {
			if ($widget['place'] == $place) {
				$positionhtml = $optionhtml;

				if (isset($previous))
					$positionhtml .= ' - ' . as_lang_html_sub('admin/after_x', as_html($passedself ? $widget['title'] : $previous['title']));

				if ($widget['widgetid'] == @$editwidget['widgetid'])
					$passedself = true;

				$maxposition = max($maxposition, $widget['position']);
				$positionoptions[$place . $widget['position']] = $positionhtml;

				$previous = $widget;
			}
		}

		if (!isset($editwidget['widgetid']) || $place != @$editwidget['place']) {
			$positionhtml = $optionhtml;

			if (isset($previous))
				$positionhtml .= ' - ' . as_lang_html_sub('admin/after_x', $previous['title']);

			$positionoptions[$place . (isset($previous) ? (1 + $maxposition) : 1)] = $positionhtml;
		}
	}
}

$positionvalue = @$positionoptions[$editwidget['place'] . $editwidget['position']];

$as_content['form'] = array(
	'tags' => 'method="post" action="' . as_path_html(as_request()) . '"',

	'style' => 'tall',

	'fields' => array(
		'title' => array(
			'label' => as_lang_html('admin/widget_name') . ' &nbsp; ' . as_html($editwidget['title']),
			'type' => 'static',
			'tight' => true,
		),

		'position' => array(
			'id' => 'position_display',
			'tags' => 'name="position"',
			'label' => as_lang_html('admin/position'),
			'type' => 'select',
			'options' => $positionoptions,
			'value' => $positionvalue,
		),

		'delete' => array(
			'tags' => 'name="dodelete" id="dodelete"',
			'label' => as_lang_html('admin/delete_widget_position'),
			'value' => 0,
			'type' => 'checkbox',
		),

		'all' => array(
			'id' => 'all_display',
			'label' => as_lang_html('admin/widget_all_pages'),
			'type' => 'checkbox',
			'tags' => 'name="template_all" id="template_all"',
			'value' => is_numeric(strpos(',' . @$editwidget['tags'] . ',', ',all,')),
		),

		'templates' => array(
			'id' => 'templates_display',
			'label' => as_lang_html('admin/widget_pages_explanation'),
			'type' => 'custom',
			'html' => '',
		),
	),

	'buttons' => array(
		'save' => array(
			'label' => as_lang_html(isset($editwidget['widgetid']) ? 'main/save_button' : ('admin/add_widget_button')),
		),

		'cancel' => array(
			'tags' => 'name="docancel"',
			'label' => as_lang_html('main/cancel_button'),
		),
	),

	'hidden' => array(
		'dosavewidget' => '1', // for IE
		'edit' => @$editwidget['widgetid'],
		'title' => @$editwidget['title'],
		'code' => as_get_form_security_code('admin/widgets'),
	),
);

foreach ($templateoptions as $template => $optionhtml) {
	$as_content['form']['fields']['templates']['html'] .=
		'<input type="checkbox" name="template_' . as_html($template) . '"' .
		(is_numeric(strpos(',' . @$editwidget['tags'] . ',', ',' . $template . ',')) ? ' checked' : '') .
		'/> ' . $optionhtml . '<br/>';
}

if (isset($editwidget['widgetid'])) {
	as_set_display_rules($as_content, array(
		'templates_display' => '!(dodelete||template_all)',
		'all_display' => '!dodelete',
	));

} else {
	unset($as_content['form']['fields']['delete']);
	as_set_display_rules($as_content, array(
		'templates_display' => '!template_all',
	));
}

if (!$widgetfound) {
	unset($as_content['form']['fields']['title']['tight']);
	$as_content['form']['fields']['title']['error'] = as_lang_html('admin/widget_not_available');
	unset($as_content['form']['fields']['position']);
	unset($as_content['form']['fields']['all']);
	unset($as_content['form']['fields']['templates']);
	if (!isset($editwidget['widgetid']))
		unset($as_content['form']['buttons']['save']);

} elseif (!count($positionoptions)) {
	unset($as_content['form']['fields']['title']['tight']);
	$as_content['form']['fields']['title']['error'] = as_lang_html('admin/widget_no_positions');
	unset($as_content['form']['fields']['position']);
	unset($as_content['form']['fields']['all']);
	unset($as_content['form']['fields']['templates']);
	unset($as_content['form']['buttons']['save']);
}

$as_content['navigation']['sub'] = as_admin_sub_navigation();


return $as_content;
