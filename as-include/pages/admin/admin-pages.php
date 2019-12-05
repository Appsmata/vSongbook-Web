<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Controller for admin page for editing custom pages and external links


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
require_once AS_INCLUDE_DIR . 'app/format.php';
require_once AS_INCLUDE_DIR . 'db/selects.php';


// Get current list of pages and determine the state of this admin page

$pageid = as_post_text('edit');
if (!isset($pageid))
	$pageid = as_get('edit');

list($pages, $editpage) = as_db_select_with_pending(
	as_db_pages_selectspec(),
	isset($pageid) ? as_db_page_full_selectspec($pageid, true) : null
);

if ((as_clicked('doaddpage') || as_clicked('doaddlink') || as_get('doaddlink') || as_clicked('dosavepage')) && !isset($editpage)) {
	$editpage = array('title' => as_get('text'), 'tags' => as_get('url'), 'nav' => as_get('nav'), 'position' => 1);
	$isexternal = as_clicked('doaddlink') || as_get('doaddlink') || as_post_text('external');

} elseif (isset($editpage))
	$isexternal = $editpage['flags'] & AS_PAGE_FLAGS_EXTERNAL;


// Check admin privileges (do late to allow one DB query)

if (!as_admin_check_privileges($as_content))
	return $as_content;


// Define an array of navigation settings we can change, option name => language key

$hascustomhome = as_has_custom_home();

$navoptions = array(
	'nav_home' => 'main/nav_home',
	'nav_activity' => 'main/nav_activity',
	$hascustomhome ? 'nav_as_not_home' : 'nav_as_is_home' => $hascustomhome ? 'main/nav_qa' : 'admin/nav_as_is_home',
	'nav_songs' => 'main/nav_qs',
	'nav_hot' => 'main/nav_hot',
	'nav_unreviewed' => 'main/nav_unreviewed',
	'nav_tags' => 'main/nav_tags',
	'nav_categories' => 'main/nav_categories',
	'nav_users' => 'main/nav_users',
	'nav_post' => 'main/nav_post',
);

$navpaths = array(
	'nav_home' => '',
	'nav_activity' => 'activity',
	'nav_as_not_home' => 'as',
	'nav_as_is_home' => '',
	'nav_songs' => 'songs',
	'nav_hot' => 'hot',
	'nav_unreviewed' => 'unreviewed',
	'nav_tags' => 'tags',
	'nav_categories' => 'categories',
	'nav_users' => 'users',
	'nav_post' => 'post',
);

if (!as_opt('show_custom_home'))
	unset($navoptions['nav_home']);

if (!as_using_categories())
	unset($navoptions['nav_categories']);

if (!as_using_tags())
	unset($navoptions['nav_tags']);


// Process saving an old or new page

$securityexpired = false;

if (as_clicked('docancel'))
	$editpage = null;

elseif (as_clicked('dosaveoptions') || as_clicked('doaddpage') || as_clicked('doaddlink')) {
	if (!as_check_form_security_code('admin/pages', as_post_text('code')))
		$securityexpired = true;
	else foreach ($navoptions as $optionname => $langkey)
		as_set_option($optionname, (int)as_post_text('option_' . $optionname));

} elseif (as_clicked('dosavepage')) {
	require_once AS_INCLUDE_DIR . 'db/admin.php';
	require_once AS_INCLUDE_DIR . 'util/string.php';

	if (!as_check_form_security_code('admin/pages', as_post_text('code')))
		$securityexpired = true;
	else {
		$reloadpages = false;

		if (as_post_text('dodelete')) {
			as_db_page_delete($editpage['pageid']);

			$searchmodules = as_load_modules_with('search', 'unindex_page');
			foreach ($searchmodules as $searchmodule)
				$searchmodule->unindex_page($editpage['pageid']);

			$editpage = null;
			$reloadpages = true;

		} else {
			$inname = as_post_text('name');
			$inposition = as_post_text('position');
			$inpermit = (int)as_post_text('permit');
			$inurl = as_post_text('url');
			$innewwindow = as_post_text('newwindow');
			$inheading = as_post_text('heading');
			$incontent = as_post_text('content');

			$errors = array();

			// Verify the name (navigation link) is legitimate

			if (empty($inname))
				$errors['name'] = as_lang('main/field_required');
			elseif (as_strlen($inname) > AS_DB_MAX_CAT_PAGE_TITLE_LENGTH)
				$errors['name'] = as_lang_sub('main/max_length_x', AS_DB_MAX_CAT_PAGE_TITLE_LENGTH);

			if ($isexternal) {
				// Verify the url is legitimate (vaguely)

				if (empty($inurl))
					$errors['url'] = as_lang('main/field_required');
				elseif (as_strlen($inurl) > AS_DB_MAX_CAT_PAGE_TAGS_LENGTH)
					$errors['url'] = as_lang_sub('main/max_length_x', AS_DB_MAX_CAT_PAGE_TAGS_LENGTH);

			} else {
				// Verify the heading is legitimate

				if (as_strlen($inheading) > AS_DB_MAX_TITLE_LENGTH)
					$errors['heading'] = as_lang_sub('main/max_length_x', AS_DB_MAX_TITLE_LENGTH);

				// Verify the slug is legitimate (and try some defaults if we're creating a new page, and it's not)

				for ($attempt = 0; $attempt < 100; $attempt++) {
					switch ($attempt) {
						case 0:
							$inslug = as_post_text('slug');
							if (!isset($inslug))
								$inslug = implode('-', as_string_to_words($inname));
							break;

						case 1:
							$inslug = as_lang_sub('admin/page_default_slug', $inslug);
							break;

						default:
							$inslug = as_lang_sub('admin/page_default_slug', $attempt - 1);
							break;
					}

					list($matchcategoryid, $matchpage) = as_db_select_with_pending(
						as_db_slugs_to_category_id_selectspec($inslug),
						as_db_page_full_selectspec($inslug, false)
					);

					if (empty($inslug))
						$errors['slug'] = as_lang('main/field_required');
					elseif (as_strlen($inslug) > AS_DB_MAX_CAT_PAGE_TAGS_LENGTH)
						$errors['slug'] = as_lang_sub('main/max_length_x', AS_DB_MAX_CAT_PAGE_TAGS_LENGTH);
					elseif (preg_match('/[\\+\\/]/', $inslug))
						$errors['slug'] = as_lang_sub('admin/slug_bad_chars', '+ /');
					elseif (as_admin_is_slug_reserved($inslug))
						$errors['slug'] = as_lang('admin/slug_reserved');
					elseif (isset($matchpage) && ($matchpage['pageid'] != @$editpage['pageid']))
						$errors['slug'] = as_lang('admin/page_already_used');
					elseif (isset($matchcategoryid))
						$errors['slug'] = as_lang('admin/category_already_used');
					else
						unset($errors['slug']);

					if (isset($editpage['pageid']) || !isset($errors['slug'])) // don't try other options if editing existing page
						break;
				}
			}

			// Perform appropriate database action

			if (isset($editpage['pageid'])) { // changing existing page
				if ($isexternal) {
					as_db_page_set_fields($editpage['pageid'],
						isset($errors['name']) ? $editpage['title'] : $inname,
						AS_PAGE_FLAGS_EXTERNAL | ($innewwindow ? AS_PAGE_FLAGS_NEW_WINDOW : 0),
						isset($errors['url']) ? $editpage['tags'] : $inurl,
						null, null, $inpermit);

				} else {
					$setheading = isset($errors['heading']) ? $editpage['heading'] : $inheading;
					$setslug = isset($errors['slug']) ? $editpage['tags'] : $inslug;
					$setcontent = isset($errors['content']) ? $editpage['content'] : $incontent;

					as_db_page_set_fields($editpage['pageid'],
						isset($errors['name']) ? $editpage['title'] : $inname,
						0,
						$setslug, $setheading, $setcontent, $inpermit);

					$searchmodules = as_load_modules_with('search', 'unindex_page');
					foreach ($searchmodules as $searchmodule)
						$searchmodule->unindex_page($editpage['pageid']);

					$indextext = as_viewer_text($setcontent, 'html');

					$searchmodules = as_load_modules_with('search', 'index_page');
					foreach ($searchmodules as $searchmodule)
						$searchmodule->index_page($editpage['pageid'], $setslug, $setheading, $setcontent, 'html', $indextext);
				}

				as_db_page_move($editpage['pageid'], substr($inposition, 0, 1), substr($inposition, 1));

				$reloadpages = true;

				if (empty($errors))
					$editpage = null;
				else
					$editpage = @$pages[$editpage['pageid']];

			} else { // creating a new one
				if (empty($errors)) {
					if ($isexternal) {
						$pageid = as_db_page_create($inname, AS_PAGE_FLAGS_EXTERNAL | ($innewwindow ? AS_PAGE_FLAGS_NEW_WINDOW : 0), $inurl, null, null, $inpermit);
					} else {
						$pageid = as_db_page_create($inname, 0, $inslug, $inheading, $incontent, $inpermit);

						$indextext = as_viewer_text($incontent, 'html');

						$searchmodules = as_load_modules_with('search', 'index_page');
						foreach ($searchmodules as $searchmodule)
							$searchmodule->index_page($pageid, $inslug, $inheading, $incontent, 'html', $indextext);
					}

					as_db_page_move($pageid, substr($inposition, 0, 1), substr($inposition, 1));

					$editpage = null;
					$reloadpages = true;
				}
			}

			if (as_clicked('dosaveview') && empty($errors) && !$isexternal)
				as_redirect($inslug);
		}

		if ($reloadpages) {
			as_db_flush_pending_result('navpages');
			$pages = as_db_select_with_pending(as_db_pages_selectspec());
		}
	}
}


// Prepare content for theme

$as_content = as_content_prepare();

$as_content['title'] = as_lang_html('admin/admin_title') . ' - ' . as_lang_html('admin/pages_title');
$as_content['error'] = $securityexpired ? as_lang_html('admin/form_security_expired') : as_admin_page_error();

if (isset($editpage)) {
	$positionoptions = array();

	if (!$isexternal)
		$positionoptions['_' . max(1, @$editpage['position'])] = as_lang_html('admin/no_link');

	$navlangkey = array(
		'B' => 'admin/before_main_menu',
		'M' => 'admin/after_main_menu',
		'O' => 'admin/opposite_main_menu',
		'F' => 'admin/after_footer',
	);

	foreach ($navlangkey as $nav => $langkey) {
		$previous = null;
		$passedself = false;
		$maxposition = 0;

		foreach ($pages as $key => $page) {
			if ($page['nav'] == $nav) {
				if (isset($previous))
					$positionhtml = as_lang_html_sub('admin/after_x_tab', as_html($passedself ? $page['title'] : $previous['title']));
				else
					$positionhtml = as_lang_html($langkey);

				if ($page['pageid'] == @$editpage['pageid'])
					$passedself = true;

				$maxposition = max($maxposition, $page['position']);
				$positionoptions[$nav . $page['position']] = $positionhtml;

				$previous = $page;
			}
		}

		if (!isset($editpage['pageid']) || $nav != @$editpage['nav']) {
			$positionvalue = isset($previous) ? as_lang_html_sub('admin/after_x_tab', as_html($previous['title'])) : as_lang_html($langkey);
			$positionoptions[$nav . (isset($previous) ? (1 + $maxposition) : 1)] = $positionvalue;
		}
	}

	$positionvalue = @$positionoptions[$editpage['nav'] . $editpage['position']];

	$permitoptions = as_admin_permit_options(AS_PERMIT_ALL, AS_PERMIT_ADMINS, false, false);
	$permitvalue = @$permitoptions[isset($inpermit) ? $inpermit : $editpage['permit']];

	$as_content['form'] = array(
		'tags' => 'method="post" action="' . as_path_html(as_request()) . '"',

		'style' => 'tall',

		'fields' => array(
			'name' => array(
				'tags' => 'name="name" id="name"',
				'label' => as_lang_html($isexternal ? 'admin/link_name' : 'admin/page_name'),
				'value' => as_html(isset($inname) ? $inname : @$editpage['title']),
				'error' => as_html(@$errors['name']),
			),

			'delete' => array(
				'tags' => 'name="dodelete" id="dodelete"',
				'label' => as_lang_html($isexternal ? 'admin/delete_link' : 'admin/delete_page'),
				'value' => 0,
				'type' => 'checkbox',
			),

			'position' => array(
				'id' => 'position_display',
				'tags' => 'name="position"',
				'label' => as_lang_html('admin/position'),
				'type' => 'select',
				'options' => $positionoptions,
				'value' => $positionvalue,
			),

			'permit' => array(
				'id' => 'permit_display',
				'tags' => 'name="permit"',
				'label' => as_lang_html('admin/permit_to_view'),
				'type' => 'select',
				'options' => $permitoptions,
				'value' => $permitvalue,
			),

			'slug' => array(
				'id' => 'slug_display',
				'tags' => 'name="slug"',
				'label' => as_lang_html('admin/page_slug'),
				'value' => as_html(isset($inslug) ? $inslug : @$editpage['tags']),
				'error' => as_html(@$errors['slug']),
			),

			'url' => array(
				'id' => 'url_display',
				'tags' => 'name="url"',
				'label' => as_lang_html('admin/link_url'),
				'value' => as_html(isset($inurl) ? $inurl : @$editpage['tags']),
				'error' => as_html(@$errors['url']),
			),

			'newwindow' => array(
				'id' => 'newwindow_display',
				'tags' => 'name="newwindow"',
				'label' => as_lang_html('admin/link_new_window'),
				'value' => (isset($innewwindow) ? $innewwindow : (@$editpage['flags'] & AS_PAGE_FLAGS_NEW_WINDOW)) ? 1 : 0,
				'type' => 'checkbox',
			),

			'heading' => array(
				'id' => 'heading_display',
				'tags' => 'name="heading"',
				'label' => as_lang_html('admin/page_heading'),
				'value' => as_html(isset($inheading) ? $inheading : @$editpage['heading']),
				'error' => as_html(@$errors['heading']),
			),

			'content' => array(
				'id' => 'content_display',
				'tags' => 'name="content"',
				'label' => as_lang_html('admin/page_content_html'),
				'value' => as_html(isset($incontent) ? $incontent : @$editpage['content']),
				'error' => as_html(@$errors['content']),
				'rows' => 16,
			),
		),

		'buttons' => array(
			'save' => array(
				'label' => as_lang_html(isset($editpage['pageid']) ? 'main/save_button' : ($isexternal ? 'admin/add_link_button' : 'admin/add_page_button')),
			),

			'saveview' => array(
				'tags' => 'name="dosaveview"',
				'label' => as_lang_html('admin/save_view_button'),
			),

			'cancel' => array(
				'tags' => 'name="docancel"',
				'label' => as_lang_html('main/cancel_button'),
			),
		),

		'hidden' => array(
			'dosavepage' => '1', // for IE
			'edit' => @$editpage['pageid'],
			'external' => (int)$isexternal,
			'code' => as_get_form_security_code('admin/pages'),
		),
	);

	if ($isexternal) {
		unset($as_content['form']['fields']['slug']);
		unset($as_content['form']['fields']['heading']);
		unset($as_content['form']['fields']['content']);

	} else {
		unset($as_content['form']['fields']['url']);
		unset($as_content['form']['fields']['newwindow']);
	}

	if (isset($editpage['pageid'])) {
		as_set_display_rules($as_content, array(
			'position_display' => '!dodelete',
			'permit_display' => '!dodelete',
			($isexternal ? 'url_display' : 'slug_display') => '!dodelete',
			($isexternal ? 'newwindow_display' : 'heading_display') => '!dodelete',
			'content_display' => '!dodelete',
		));

	} else {
		unset($as_content['form']['fields']['slug']);
		unset($as_content['form']['fields']['delete']);
	}

	if ($isexternal || !isset($editpage['pageid']))
		unset($as_content['form']['buttons']['saveview']);

	$as_content['focusid'] = 'name';

} else {
	// List of standard navigation links
	$as_content['form'] = array(
		'tags' => 'method="post" action="' . as_self_html() . '"',

		'style' => 'tall',

		'fields' => array(),

		'buttons' => array(
			'save' => array(
				'tags' => 'name="dosaveoptions"',
				'label' => as_lang_html('main/save_button'),
			),

			'addpage' => array(
				'tags' => 'name="doaddpage"',
				'label' => as_lang_html('admin/add_page_button'),
			),

			'addlink' => array(
				'tags' => 'name="doaddlink"',
				'label' => as_lang_html('admin/add_link_button'),
			),
		),

		'hidden' => array(
			'code' => as_get_form_security_code('admin/pages'),
		),
	);

	$as_content['form']['fields']['navlinks'] = array(
		'label' => as_lang_html('admin/nav_links_explanation'),
		'type' => 'static',
		'tight' => true,
	);

	foreach ($navoptions as $optionname => $langkey) {
		$as_content['form']['fields'][$optionname] = array(
			'label' => '<a href="' . as_path_html($navpaths[$optionname]) . '">' . as_lang_html($langkey) . '</a>',
			'tags' => 'name="option_' . $optionname . '"',
			'type' => 'checkbox',
			'value' => as_opt($optionname),
		);
	}

	$as_content['form']['fields'][] = array(
		'type' => 'blank'
	);

	// List of suggested plugin pages

	$listhtml = '';

	$pagemodules = as_load_modules_with('page', 'suggest_requests');

	foreach ($pagemodules as $tryname => $trypage) {
		$suggestrequests = $trypage->suggest_requests();

		foreach ($suggestrequests as $suggestrequest) {
			$listhtml .= '<li><b><a href="' . as_path_html($suggestrequest['request']) . '">' . as_html($suggestrequest['title']) . '</a></b>';

			$listhtml .= as_lang_html_sub('admin/plugin_module', as_html($tryname));

			$listhtml .= strtr(as_lang_html('admin/add_link_link'), array(
				'^1' => '<a href="' . as_path_html(as_request(), array('doaddlink' => 1, 'text' => $suggestrequest['title'], 'url' => $suggestrequest['request'], 'nav' => @$suggestrequest['nav'])) . '">',
				'^2' => '</a>',
			));

			if (method_exists($trypage, 'admin_form'))
				$listhtml .= ' - <a href="' . as_admin_module_options_path('page', $tryname) . '">' . as_lang_html('admin/options') . '</a>';

			$listhtml .= '</li>';
		}
	}

	if (strlen($listhtml)) {
		$as_content['form']['fields']['plugins'] = array(
			'label' => as_lang_html('admin/plugin_pages_explanation'),
			'type' => 'custom',
			'html' => '<ul style="margin-bottom:0;">' . $listhtml . '</ul>',
		);
	}

	// List of custom pages or links

	$listhtml = '';

	foreach ($pages as $page) {
		$listhtml .= '<li><b><a href="' . as_custom_page_url($page) . '">' . as_html($page['title']) . '</a></b>';

		$listhtml .= strtr(as_lang_html(($page['flags'] & AS_PAGE_FLAGS_EXTERNAL) ? 'admin/edit_link' : 'admin/edit_page'), array(
			'^1' => '<a href="' . as_path_html('admin/pages', array('edit' => $page['pageid'])) . '">',
			'^2' => '</a>',
		));

		$listhtml .= '</li>';
	}

	$as_content['form']['fields']['pages'] = array(
		'label' => strlen($listhtml) ? as_lang_html('admin/click_name_edit') : as_lang_html('admin/pages_explanation'),
		'type' => 'custom',
		'html' => strlen($listhtml) ? '<ul style="margin-bottom:0;">' . $listhtml . '</ul>' : null,
	);
}

$as_content['navigation']['sub'] = as_admin_sub_navigation();


return $as_content;
