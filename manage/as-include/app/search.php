<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Wrapper functions and utilities for search modules


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
 * Returns $count search results for $query performed by $userid, starting at offset $start. Set $absoluteurls to true
 * to get absolute URLs for the results and $fullcontent if the results should include full post content. This calls
 * through to the chosen search module, and performs all the necessary post-processing to supplement the results for
 * display online or in an RSS feed.
 * @param $query
 * @param $start
 * @param $count
 * @param $userid
 * @param $absoluteurls
 * @param $fullcontent
 * @return
 */
function as_get_search_results($query, $start, $count, $userid, $absoluteurls, $fullcontent)
{
	// Identify which search module should be used

	$searchmodules = as_load_modules_with('search', 'process_search');

	if (!count($searchmodules))
		as_fatal_error('No search engine is available');

	$module = reset($searchmodules); // use first one by default

	if (count($searchmodules) > 1) {
		$tryname = as_opt('search_module'); // use chosen one if it's available

		if (isset($searchmodules[$tryname]))
			$module = $searchmodules[$tryname];
	}

	// Get the results

	$results = $module->process_search($query, $start, $count, $userid, $absoluteurls, $fullcontent);

	// Work out what additional information (if any) we need to retrieve for the results

	$keypostidgetfull = array();
	$keypostidgettype = array();
	$keypostidgetsong = array();
	$keypageidgetpage = array();

	foreach ($results as $result) {
		if (isset($result['song_postid']) && !isset($result['song']))
			$keypostidgetfull[$result['song_postid']] = true;

		if (isset($result['match_postid'])) {
			if (!((isset($result['song_postid'])) || (isset($result['song']))))
				$keypostidgetsong[$result['match_postid']] = true; // we can also get $result['match_type'] from this

			elseif (!isset($result['match_type']))
				$keypostidgettype[$result['match_postid']] = true;
		}

		if (isset($result['page_pageid']) && !isset($result['page']))
			$keypageidgetpage[$result['page_pageid']] = true;
	}

	// Perform the appropriate database queries

	list($postidfull, $postidtype, $postidsong, $pageidpage) = as_db_select_with_pending(
		count($keypostidgetfull) ? as_db_posts_selectspec($userid, array_keys($keypostidgetfull), $fullcontent) : null,
		count($keypostidgettype) ? as_db_posts_basetype_selectspec(array_keys($keypostidgettype)) : null,
		count($keypostidgetsong) ? as_db_posts_to_qs_selectspec($userid, array_keys($keypostidgetsong), $fullcontent) : null,
		count($keypageidgetpage) ? as_db_pages_selectspec(null, array_keys($keypageidgetpage)) : null
	);

	// Supplement the results as appropriate

	foreach ($results as $key => $result) {
		if (isset($result['song_postid']) && !isset($result['song']))
			if (@$postidfull[$result['song_postid']]['basetype'] == 'S')
				$result['song'] = @$postidfull[$result['song_postid']];

		if (isset($result['match_postid'])) {
			if (!(isset($result['song_postid']) || isset($result['song']))) {
				$result['song'] = @$postidsong[$result['match_postid']];

				if (!isset($result['match_type']))
					$result['match_type'] = @$result['song']['obasetype'];

			} elseif (!isset($result['match_type']))
				$result['match_type'] = @$postidtype[$result['match_postid']];
		}

		if (isset($result['song']) && !isset($result['song_postid']))
			$result['song_postid'] = $result['song']['postid'];

		if (isset($result['page_pageid']) && !isset($result['page']))
			$result['page'] = @$pageidpage[$result['page_pageid']];

		if (!isset($result['title'])) {
			if (isset($result['song']))
				$result['title'] = $result['song']['title'];
			elseif (isset($result['page']))
				$result['title'] = $result['page']['heading'];
		}

		if (!isset($result['url'])) {
			if (isset($result['song']))
				$result['url'] = as_q_path($result['song']['postid'], $result['song']['title'],
					$absoluteurls, @$result['match_type'], @$result['match_postid']);
			elseif (isset($result['page']))
				$result['url'] = as_path($result['page']['tags'], null, as_opt('site_url'));
		}

		$results[$key] = $result;
	}

	// Return the results

	return $results;
}
