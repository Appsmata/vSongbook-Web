<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Controller for page listing categories


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

require_once AS_INCLUDE_DIR . 'db/selects.php';
require_once AS_INCLUDE_DIR . 'app/format.php';


$categoryslugs = as_request_parts(1);
$countslugs = count($categoryslugs);


// Get information about appropriate categories and redirect to songs page if category has no sub-categories

$userid = as_get_logged_in_userid();
list($categories, $categoryid, $favoritecats) = as_db_select_with_pending(
	as_db_category_nav_selectspec($categoryslugs, false, false, true),
	$countslugs ? as_db_slugs_to_category_id_selectspec($categoryslugs) : null,
	isset($userid) ? as_db_user_favorite_categories_selectspec($userid) : null
);

if ($countslugs && !isset($categoryid)) {
	return include AS_INCLUDE_DIR . 'as-page-not-found.php';
}


// Function for recursive display of categories

function as_category_nav_to_browse(&$navigation, $categories, $categoryid, $favoritemap)
{
	foreach ($navigation as $key => $navlink) {
		$category = $categories[$navlink['categoryid']];

		if (!$category['childcount']) {
			unset($navigation[$key]['url']);
		} elseif ($navlink['selected']) {
			$navigation[$key]['state'] = 'open';
			$navigation[$key]['url'] = as_path_html('categories/' . as_category_path_request($categories, $category['parentid']));
		} else
			$navigation[$key]['state'] = 'closed';

		if (@$favoritemap[$navlink['categoryid']]) {
			$navigation[$key]['favorited'] = true;
		}

		$navigation[$key]['note'] =
			' - <a href="'.as_path_html('songs/'.implode('/', array_reverse(explode('/', $category['backpath'])))).'">'.( ($category['qcount']==1)
				? as_lang_html_sub('main/1_song', '1', '1')
				: as_lang_html_sub('main/x_songs', number_format($category['qcount']))
			).'</a>';

		if (strlen($category['content']))
			$navigation[$key]['note'] .= as_html(' - ' . $category['content']);

		if (isset($navlink['subnav']))
			as_category_nav_to_browse($navigation[$key]['subnav'], $categories, $categoryid, $favoritemap);
	}
}


// Prepare content for theme

$as_content = as_content_prepare(false, array_keys(as_category_path($categories, $categoryid)));

$as_content['title'] = as_lang_html('misc/browse_categories');

if (count($categories)) {
	$navigation = as_category_navigation($categories, $categoryid, 'categories/', false);

	unset($navigation['all']);

	$favoritemap = array();
	if (isset($favoritecats)) {
		foreach ($favoritecats as $category) {
			$favoritemap[$category['categoryid']] = true;
		}
	}

	as_category_nav_to_browse($navigation, $categories, $categoryid, $favoritemap);

	$as_content['nav_list'] = array(
		'nav' => $navigation,
		'type' => 'browse-cat',
	);

} else {
	$as_content['title'] = as_lang_html('main/no_categories_found');
	$as_content['suggest_next'] = as_html_suggest_qs_tags(as_using_tags());
}


return $as_content;
