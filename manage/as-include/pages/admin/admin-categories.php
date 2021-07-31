<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Controller for admin page for editing categories


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
require_once AS_INCLUDE_DIR . 'db/admin.php';
require_once AS_INCLUDE_DIR . 'app/format.php';


// Get relevant list of categories

$editcategoryid = as_post_text('edit');
if (!isset($editcategoryid))
	$editcategoryid = as_get('edit');
if (!isset($editcategoryid))
	$editcategoryid = as_get('addsub');

$categories = as_db_select_with_pending(as_db_category_nav_selectspec($editcategoryid, true, false, true));


// Check admin privileges (do late to allow one DB query)

if (!as_admin_check_privileges($as_content))
	return $as_content;


// Work out the appropriate state for the page

$editcategory = @$categories[$editcategoryid];

if (isset($editcategory)) {
	$parentid = as_get('addsub');
	if (isset($parentid))
		$editcategory = array('parentid' => $parentid);

} else {
	if (as_clicked('doaddcategory'))
		$editcategory = array();

	elseif (as_clicked('dosavecategory')) {
		$parentid = as_post_text('parent');
		$editcategory = array('parentid' => strlen($parentid) ? $parentid : null);
	}
}

$setmissing = as_post_text('missing') || as_get('missing');

$setparent = !$setmissing && (as_post_text('setparent') || as_get('setparent')) && isset($editcategory['categoryid']);

$hassubcategory = false;
foreach ($categories as $category) {
	if (!strcmp($category['parentid'], $editcategoryid))
		$hassubcategory = true;
}


// Process saving options

$savedoptions = false;
$securityexpired = false;

if (as_clicked('dosaveoptions')) {
	if (!as_check_form_security_code('admin/categories', as_post_text('code')))
		$securityexpired = true;

	else {
		as_set_option('allow_no_category', (int)as_post_text('option_allow_no_category'));
		as_set_option('allow_no_sub_category', (int)as_post_text('option_allow_no_sub_category'));
		$savedoptions = true;
	}
}


// Process saving an old or new category

if (as_clicked('docancel')) {
	if ($setmissing || $setparent)
		as_redirect(as_request(), array('edit' => $editcategory['categoryid']));
	elseif (isset($editcategory['categoryid']))
		as_redirect(as_request());
	else
		as_redirect(as_request(), array('edit' => @$editcategory['parentid']));

} elseif (as_clicked('dosetmissing')) {
	if (!as_check_form_security_code('admin/categories', as_post_text('code')))
		$securityexpired = true;

	else {
		$inreassign = as_get_category_field_value('reassign');
		as_db_category_reassign($editcategory['categoryid'], $inreassign);
		as_redirect(as_request(), array('recalc' => 1, 'edit' => $editcategory['categoryid']));
	}

} elseif (as_clicked('dosavecategory')) {
	if (!as_check_form_security_code('admin/categories', as_post_text('code')))
		$securityexpired = true;

	elseif (as_post_text('dodelete')) {
		if (!$hassubcategory) {
			$inreassign = as_get_category_field_value('reassign');
			as_db_category_reassign($editcategory['categoryid'], $inreassign);
			as_db_category_delete($editcategory['categoryid']);
			as_redirect(as_request(), array('recalc' => 1, 'edit' => $editcategory['parentid']));
		}

	} else {
		require_once AS_INCLUDE_DIR . 'util/string.php';

		$inname = as_post_text('name');
		$incontent = as_post_text('content');
		$inparentid = $setparent ? as_get_category_field_value('parent') : $editcategory['parentid'];
		$inposition = as_post_text('position');
		$errors = array();

		// Check the parent ID

		$incategories = as_db_select_with_pending(as_db_category_nav_selectspec($inparentid, true));

		// Verify the name is legitimate for that parent ID

		if (empty($inname))
			$errors['name'] = as_lang('main/field_required');
		elseif (as_strlen($inname) > AS_DB_MAX_CAT_PAGE_TITLE_LENGTH)
			$errors['name'] = as_lang_sub('main/max_length_x', AS_DB_MAX_CAT_PAGE_TITLE_LENGTH);
		else {
			foreach ($incategories as $category) {
				if (!strcmp($category['parentid'], $inparentid) &&
					strcmp($category['categoryid'], @$editcategory['categoryid']) &&
					as_strtolower($category['title']) == as_strtolower($inname)
				) {
					$errors['name'] = as_lang('admin/category_already_used');
				}
			}
		}

		// Verify the slug is legitimate for that parent ID

		for ($attempt = 0; $attempt < 100; $attempt++) {
			switch ($attempt) {
				case 0:
					$inslug = as_post_text('slug');
					if (!isset($inslug))
						$inslug = implode('-', as_string_to_words($inname));
					break;

				case 1:
					$inslug = as_lang_sub('admin/category_default_slug', $inslug);
					break;

				default:
					$inslug = as_lang_sub('admin/category_default_slug', $attempt - 1);
					break;
			}

			$matchcategoryid = as_db_category_slug_to_id($inparentid, $inslug); // query against DB since MySQL ignores accents, etc...

			if (!isset($inparentid))
				$matchpage = as_db_single_select(as_db_page_full_selectspec($inslug, false));
			else
				$matchpage = null;

			if (empty($inslug))
				$errors['slug'] = as_lang('main/field_required');
			elseif (as_strlen($inslug) > AS_DB_MAX_CAT_PAGE_TAGS_LENGTH)
				$errors['slug'] = as_lang_sub('main/max_length_x', AS_DB_MAX_CAT_PAGE_TAGS_LENGTH);
			elseif (preg_match('/[\\+\\/]/', $inslug))
				$errors['slug'] = as_lang_sub('admin/slug_bad_chars', '+ /');
			elseif (!isset($inparentid) && as_admin_is_slug_reserved($inslug)) // only top level is a problem
				$errors['slug'] = as_lang('admin/slug_reserved');
			elseif (isset($matchcategoryid) && strcmp($matchcategoryid, @$editcategory['categoryid']))
				$errors['slug'] = as_lang('admin/category_already_used');
			elseif (isset($matchpage))
				$errors['slug'] = as_lang('admin/page_already_used');
			else
				unset($errors['slug']);

			if (isset($editcategory['categoryid']) || !isset($errors['slug'])) // don't try other options if editing existing category
				break;
		}

		// Perform appropriate database action

		if (empty($errors)) {
			if (isset($editcategory['categoryid'])) { // changing existing category
				as_db_category_rename($editcategory['categoryid'], $inname, $inslug);

				$recalc = false;

				if ($setparent) {
					as_db_category_set_parent($editcategory['categoryid'], $inparentid);
					$recalc = true;
				} else {
					as_db_category_set_content($editcategory['categoryid'], $incontent);
					as_db_category_set_position($editcategory['categoryid'], $inposition);
					$recalc = $hassubcategory && $inslug !== $editcategory['tags'];
				}

				as_redirect(as_request(), array('edit' => $editcategory['categoryid'], 'saved' => true, 'recalc' => (int)$recalc));

			} else { // creating a new one
				$categoryid = as_db_category_create($inparentid, $inname, $inslug);

				as_db_category_set_content($categoryid, $incontent);

				if (isset($inposition))
					as_db_category_set_position($categoryid, $inposition);

				as_redirect(as_request(), array('edit' => $inparentid, 'added' => true));
			}
		}
	}
}


// Prepare content for theme

$as_content = as_content_prepare();

$as_content['title'] = as_lang_html('admin/admin_title') . ' - ' . as_lang_html('admin/categories_title');
$as_content['error'] = $securityexpired ? as_lang_html('admin/form_security_expired') : as_admin_page_error();

if ($setmissing) {
	$as_content['form'] = array(
		'tags' => 'method="post" action="' . as_path_html(as_request()) . '"',

		'style' => 'tall',

		'fields' => array(
			'reassign' => array(
				'label' => isset($editcategory)
					? as_lang_html_sub('admin/category_no_sub_to', as_html($editcategory['title']))
					: as_lang_html('admin/category_none_to'),
				'loose' => true,
			),
		),

		'buttons' => array(
			'save' => array(
				'tags' => 'id="dosaveoptions"', // just used for as_recalc_click()
				'label' => as_lang_html('main/save_button'),
			),

			'cancel' => array(
				'tags' => 'name="docancel"',
				'label' => as_lang_html('main/cancel_button'),
			),
		),

		'hidden' => array(
			'dosetmissing' => '1', // for IE
			'edit' => @$editcategory['categoryid'],
			'missing' => '1',
			'code' => as_get_form_security_code('admin/categories'),
		),
	);

	as_set_up_category_field($as_content, $as_content['form']['fields']['reassign'], 'reassign',
		$categories, @$editcategory['categoryid'], as_opt('allow_no_category'), as_opt('allow_no_sub_category'));


} elseif (isset($editcategory)) {
	$as_content['form'] = array(
		'tags' => 'method="post" action="' . as_path_html(as_request()) . '"',

		'style' => 'tall',

		'ok' => as_get('saved') ? as_lang_html('admin/category_saved') : (as_get('added') ? as_lang_html('admin/category_added') : null),

		'fields' => array(
			'name' => array(
				'id' => 'name_display',
				'tags' => 'name="name" id="name"',
				'label' => as_lang_html(count($categories) ? 'admin/category_name' : 'admin/category_name_first'),
				'value' => as_html(isset($inname) ? $inname : @$editcategory['title']),
				'error' => as_html(@$errors['name']),
			),

			'songs' => array(),

			'delete' => array(),

			'reassign' => array(),

			'slug' => array(
				'id' => 'slug_display',
				'tags' => 'name="slug"',
				'label' => as_lang_html('admin/category_slug'),
				'value' => as_html(isset($inslug) ? $inslug : @$editcategory['tags']),
				'error' => as_html(@$errors['slug']),
			),

			'content' => array(
				'id' => 'content_display',
				'tags' => 'name="content"',
				'label' => as_lang_html('admin/category_description'),
				'value' => as_html(isset($incontent) ? $incontent : @$editcategory['content']),
				'error' => as_html(@$errors['content']),
				'rows' => 2,
			),
		),

		'buttons' => array(
			'save' => array(
				'tags' => 'id="dosaveoptions"', // just used for as_recalc_click
				'label' => as_lang_html(isset($editcategory['categoryid']) ? 'main/save_button' : 'admin/add_category_button'),
			),

			'cancel' => array(
				'tags' => 'name="docancel"',
				'label' => as_lang_html('main/cancel_button'),
			),
		),

		'hidden' => array(
			'dosavecategory' => '1', // for IE
			'edit' => @$editcategory['categoryid'],
			'parent' => @$editcategory['parentid'],
			'setparent' => (int)$setparent,
			'code' => as_get_form_security_code('admin/categories'),
		),
	);


	if ($setparent) {
		unset($as_content['form']['fields']['delete']);
		unset($as_content['form']['fields']['reassign']);
		unset($as_content['form']['fields']['songs']);
		unset($as_content['form']['fields']['content']);

		$as_content['form']['fields']['parent'] = array(
			'label' => as_lang_html('admin/category_parent'),
		);

		$childdepth = as_db_category_child_depth($editcategory['categoryid']);

		as_set_up_category_field($as_content, $as_content['form']['fields']['parent'], 'parent',
			isset($incategories) ? $incategories : $categories, isset($inparentid) ? $inparentid : @$editcategory['parentid'],
			true, true, AS_CATEGORY_DEPTH - 1 - $childdepth, @$editcategory['categoryid']);

		$as_content['form']['fields']['parent']['options'][''] = as_lang_html('admin/category_top_level');

		@$as_content['form']['fields']['parent']['note'] .= as_lang_html_sub('admin/category_max_depth_x', AS_CATEGORY_DEPTH);

	} elseif (isset($editcategory['categoryid'])) { // existing category
		if ($hassubcategory) {
			$as_content['form']['fields']['name']['note'] = as_lang_html('admin/category_no_delete_subs');
			unset($as_content['form']['fields']['delete']);
			unset($as_content['form']['fields']['reassign']);

		} else {
			$as_content['form']['fields']['delete'] = array(
				'tags' => 'name="dodelete" id="dodelete"',
				'label' =>
					'<span id="reassign_shown">' . as_lang_html('admin/delete_category_reassign') . '</span>' .
					'<span id="reassign_hidden" style="display:none;">' . as_lang_html('admin/delete_category') . '</span>',
				'value' => 0,
				'type' => 'checkbox',
			);

			$as_content['form']['fields']['reassign'] = array(
				'id' => 'reassign_display',
				'tags' => 'name="reassign"',
			);

			as_set_up_category_field($as_content, $as_content['form']['fields']['reassign'], 'reassign',
				$categories, $editcategory['parentid'], true, true, null, $editcategory['categoryid']);
		}

		$as_content['form']['fields']['songs'] = array(
			'label' => as_lang_html('admin/total_qs'),
			'type' => 'static',
			'value' => '<a href="' . as_path_html('songs/' . as_category_path_request($categories, $editcategory['categoryid'])) . '">' .
				($editcategory['qcount'] == 1
					? as_lang_html_sub('main/1_song', '1', '1')
					: as_lang_html_sub('main/x_songs', as_format_number($editcategory['qcount']))
				) . '</a>',
		);

		if ($hassubcategory && !as_opt('allow_no_sub_category')) {
			$nosubcount = as_db_count_categoryid_qs($editcategory['categoryid']);

			if ($nosubcount) {
				$as_content['form']['fields']['songs']['error'] =
					strtr(as_lang_html('admin/category_no_sub_error'), array(
						'^q' => as_format_number($nosubcount),
						'^1' => '<a href="' . as_path_html(as_request(), array('edit' => $editcategory['categoryid'], 'missing' => 1)) . '">',
						'^2' => '</a>',
					));
			}
		}

		as_set_display_rules($as_content, array(
			'position_display' => '!dodelete',
			'slug_display' => '!dodelete',
			'content_display' => '!dodelete',
			'parent_display' => '!dodelete',
			'children_display' => '!dodelete',
			'reassign_display' => 'dodelete',
			'reassign_shown' => 'dodelete',
			'reassign_hidden' => '!dodelete',
		));

	} else { // new category
		unset($as_content['form']['fields']['delete']);
		unset($as_content['form']['fields']['reassign']);
		unset($as_content['form']['fields']['slug']);
		unset($as_content['form']['fields']['songs']);

		$as_content['focusid'] = 'name';
	}

	if (!$setparent) {
		$pathhtml = as_category_path_html($categories, @$editcategory['parentid']);

		if (count($categories)) {
			$as_content['form']['fields']['parent'] = array(
				'id' => 'parent_display',
				'label' => as_lang_html('admin/category_parent'),
				'type' => 'static',
				'value' => (strlen($pathhtml) ? $pathhtml : as_lang_html('admin/category_top_level')),
			);

			$as_content['form']['fields']['parent']['value'] =
				'<a href="' . as_path_html(as_request(), array('edit' => @$editcategory['parentid'])) . '">' .
				$as_content['form']['fields']['parent']['value'] . '</a>';

			if (isset($editcategory['categoryid'])) {
				$as_content['form']['fields']['parent']['value'] .= ' - ' .
					'<a href="' . as_path_html(as_request(), array('edit' => $editcategory['categoryid'], 'setparent' => 1)) .
					'" style="white-space: nowrap;">' . as_lang_html('admin/category_move_parent') . '</a>';
			}
		}

		$positionoptions = array();

		$previous = null;
		$passedself = false;

		foreach ($categories as $key => $category) {
			if (!strcmp($category['parentid'], @$editcategory['parentid'])) {
				if (isset($previous))
					$positionhtml = as_lang_html_sub('admin/after_x', as_html($passedself ? $category['title'] : $previous['title']));
				else
					$positionhtml = as_lang_html('admin/first');

				$positionoptions[$category['position']] = $positionhtml;

				if (!strcmp($category['categoryid'], @$editcategory['categoryid']))
					$passedself = true;

				$previous = $category;
			}
		}

		if (isset($editcategory['position']))
			$positionvalue = $positionoptions[$editcategory['position']];

		else {
			$positionvalue = isset($previous) ? as_lang_html_sub('admin/after_x', as_html($previous['title'])) : as_lang_html('admin/first');
			$positionoptions[1 + @max(array_keys($positionoptions))] = $positionvalue;
		}

		$as_content['form']['fields']['position'] = array(
			'id' => 'position_display',
			'tags' => 'name="position"',
			'label' => as_lang_html('admin/position'),
			'type' => 'select',
			'options' => $positionoptions,
			'value' => $positionvalue,
		);

		if (isset($editcategory['categoryid'])) {
			$catdepth = count(as_category_path($categories, $editcategory['categoryid']));

			if ($catdepth < AS_CATEGORY_DEPTH) {
				$childrenhtml = '';

				foreach ($categories as $category) {
					if (!strcmp($category['parentid'], $editcategory['categoryid'])) {
						$childrenhtml .= (strlen($childrenhtml) ? ', ' : '') .
							'<a href="' . as_path_html(as_request(), array('edit' => $category['categoryid'])) . '">' . as_html($category['title']) . '</a>' .
							' (' . $category['qcount'] . ')';
					}
				}

				if (!strlen($childrenhtml))
					$childrenhtml = as_lang_html('admin/category_no_subs');

				$childrenhtml .= ' - <a href="' . as_path_html(as_request(), array('addsub' => $editcategory['categoryid'])) .
					'" style="white-space: nowrap;"><b>' . as_lang_html('admin/category_add_sub') . '</b></a>';

				$as_content['form']['fields']['children'] = array(
					'id' => 'children_display',
					'label' => as_lang_html('admin/category_subs'),
					'type' => 'static',
					'value' => $childrenhtml,
				);
			} else {
				$as_content['form']['fields']['name']['note'] = as_lang_html_sub('admin/category_no_add_subs_x', AS_CATEGORY_DEPTH);
			}

		}
	}

} else {
	$as_content['form'] = array(
		'tags' => 'method="post" action="' . as_path_html(as_request()) . '"',

		'ok' => $savedoptions ? as_lang_html('admin/options_saved') : null,

		'style' => 'tall',

		'fields' => array(
			'intro' => array(
				'label' => as_lang_html('admin/categories_introduction'),
				'type' => 'static',
			),
		),

		'buttons' => array(
			'save' => array(
				'tags' => 'name="dosaveoptions" id="dosaveoptions"',
				'label' => as_lang_html('main/save_button'),
			),

			'add' => array(
				'tags' => 'name="doaddcategory"',
				'label' => as_lang_html('admin/add_category_button'),
			),
		),

		'hidden' => array(
			'code' => as_get_form_security_code('admin/categories'),
		),
	);

	if (count($categories)) {
		unset($as_content['form']['fields']['intro']);

		$navcategoryhtml = '';

		foreach ($categories as $category) {
			if (!isset($category['parentid'])) {
				$navcategoryhtml .=
					'<a href="' . as_path_html('admin/categories', array('edit' => $category['categoryid'])) . '">' .
					as_html($category['title']) .
					'</a> - ' .
					($category['qcount'] == 1
						? as_lang_html_sub('main/1_song', '1', '1')
						: as_lang_html_sub('main/x_songs', as_format_number($category['qcount']))
					) . '<br/>';
			}
		}

		$as_content['form']['fields']['nav'] = array(
			'label' => as_lang_html('admin/top_level_categories'),
			'type' => 'static',
			'value' => $navcategoryhtml,
		);

		$as_content['form']['fields']['allow_no_category'] = array(
			'label' => as_lang_html('options/allow_no_category'),
			'tags' => 'name="option_allow_no_category"',
			'type' => 'checkbox',
			'value' => as_opt('allow_no_category'),
		);

		if (!as_opt('allow_no_category')) {
			$nocatcount = as_db_count_categoryid_qs(null);

			if ($nocatcount) {
				$as_content['form']['fields']['allow_no_category']['error'] =
					strtr(as_lang_html('admin/category_none_error'), array(
						'^q' => as_format_number($nocatcount),
						'^1' => '<a href="' . as_path_html(as_request(), array('missing' => 1)) . '">',
						'^2' => '</a>',
					));
			}
		}

		$as_content['form']['fields']['allow_no_sub_category'] = array(
			'label' => as_lang_html('options/allow_no_sub_category'),
			'tags' => 'name="option_allow_no_sub_category"',
			'type' => 'checkbox',
			'value' => as_opt('allow_no_sub_category'),
		);

	} else
		unset($as_content['form']['buttons']['save']);
}

if (as_get('recalc')) {
	$as_content['form']['ok'] = '<span id="recalc_ok">' . as_lang_html('admin/recalc_categories') . '</span>';
	$as_content['form']['hidden']['code_recalc'] = as_get_form_security_code('admin/recalc');

	$as_content['script_rel'][] = 'as-content/as-admin.js?' . AS_VERSION;
	$as_content['script_var']['as_warning_recalc'] = as_lang('admin/stop_recalc_warning');

	$as_content['script_onloads'][] = array(
		"as_recalc_click('dorecalccategories', document.getElementById('dosaveoptions'), null, 'recalc_ok');"
	);
}

$as_content['navigation']['sub'] = as_admin_sub_navigation();


return $as_content;
