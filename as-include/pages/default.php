<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/appsmata/

	Description: Controller for home page, Q&A listing page, custom pages and plugin pages


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

require_once AS_INCLUDE_DIR . 'db/selects.php';
require_once AS_INCLUDE_DIR . 'app/format.php';


// Determine whether path begins with as or not (song and review listing can be accessed either way)

$requestparts = explode('/', as_request());
$explicitqa = (strtolower($requestparts[0]) == 'as');

if ($explicitqa) {
	$slugs = array_slice($requestparts, 1);
} elseif (strlen($requestparts[0])) {
	$slugs = $requestparts;
} else {
	$slugs = array();
}

$countslugs = count($slugs);


// Get list of songs, other bits of information that might be useful

$userid = as_get_logged_in_userid();

list($songs1, $songs2, $categories, $categoryid, $custompage) = as_db_select_with_pending(
	as_db_qs_selectspec($userid, 'created', 0, $slugs, null, false, false, as_opt_if_loaded('page_size_activity')),
	as_db_recent_a_qs_selectspec($userid, 0, $slugs),
	as_db_category_nav_selectspec($slugs, false, false, true),
	$countslugs ? as_db_slugs_to_category_id_selectspec($slugs) : null,
	($countslugs == 1 && !$explicitqa) ? as_db_page_full_selectspec($slugs[0], false) : null
);


// First, if this matches a custom page, return immediately with that page's content

if (isset($custompage) && !($custompage['flags'] & AS_PAGE_FLAGS_EXTERNAL)) {
	as_set_template('custom-' . $custompage['pageid']);

	$as_content = as_content_prepare();

	$level = as_get_logged_in_level();

	if (!as_permit_value_error($custompage['permit'], $userid, $level, as_get_logged_in_flags()) || !isset($custompage['permit'])) {
		$as_content['title'] = as_html($custompage['heading']);
		$as_content['custom'] = $custompage['content'];

		if ($level >= AS_USER_LEVEL_ADMIN) {
			$as_content['navigation']['sub'] = array(
				'admin/pages' => array(
					'label' => as_lang('admin/edit_custom_page'),
					'url' => as_path_html('admin/pages', array('edit' => $custompage['pageid'])),
				),
			);
		}

	} else {
		$as_content['error'] = as_lang_html('users/no_permission');
	}

	return $as_content;
}


// Then, see if we should redirect because the 'as' page is the same as the home page

if ($explicitqa && !as_is_http_post() && !as_has_custom_home()) {
	as_redirect(as_category_path_request($categories, $categoryid), $_GET);
}


// Then, if there's a slug that matches no category, check page modules provided by plugins

if (!$explicitqa && $countslugs && !isset($categoryid)) {
	$pagemodules = as_load_modules_with('page', 'match_request');
	$request = as_request();

	foreach ($pagemodules as $pagemodule) {
		if ($pagemodule->match_request($request)) {
			$tmpl = isset($custompage['pageid']) ? 'custom-' . $custompage['pageid'] : 'custom';
			as_set_template($tmpl);
			return $pagemodule->process_request($request);
		}
	}
}


// Then, check whether we are showing a custom home page

if (!$explicitqa && !$countslugs && as_opt('show_custom_home')) {
	as_set_template('custom');
	$as_content = as_content_prepare();
	$as_content['title'] = as_html(as_opt('custom_home_heading'));
	$as_content['custom'] = as_opt('custom_home_content');
	return $as_content;
}


// If we got this far, it's a good old-fashioned Q&A listing page

require_once AS_INCLUDE_DIR . 'app/s-list.php';

as_set_template('as');
$songs = as_any_sort_and_dedupe(array_merge($songs1, $songs2));
$pagesize = as_opt('page_size_home');

if ($countslugs) {
	if (!isset($categoryid)) {
		return include AS_INCLUDE_DIR . 'as-page-not-found.php';
	}

	$categorytitlehtml = as_html($categories[$categoryid]['title']);
	$sometitle = as_lang_html_sub('main/recent_qs_as_in_x', $categorytitlehtml);
	$nonetitle = as_lang_html_sub('main/no_songs_in_x', $categorytitlehtml);

} else {
	$sometitle = as_lang_html('main/recent_qs_as_title');
	$nonetitle = as_lang_html('main/no_songs_found');
}


// Prepare and return content for theme for Q&A listing page

$as_content = as_s_list_page_content(
	$songs, // songs
	$pagesize, // songs per page
	0, // start offset
	null, // total count (null to hide page links)
	$sometitle, // title if some songs
	$nonetitle, // title if no songs
	$categories, // categories for navigation
	$categoryid, // selected category id
	true, // show song counts in category navigation
	$explicitqa ? 'as/' : '', // prefix for links in category navigation
	as_opt('feed_for_qa') ? 'as' : null, // prefix for RSS feed paths (null to hide)
	(count($songs) < $pagesize) // suggest what to do next
		? as_html_suggest_post($categoryid)
		: as_html_suggest_qs_tags(as_using_tags(), as_category_path_request($categories, $categoryid)),
	null, // page link params
	null // category nav params
);

return $as_content;
