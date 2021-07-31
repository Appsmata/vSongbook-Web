<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Controller for song page (only viewing functionality here)


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

require_once AS_INCLUDE_DIR . 'app/cookies.php';
require_once AS_INCLUDE_DIR . 'app/format.php';
require_once AS_INCLUDE_DIR . 'db/selects.php';
require_once AS_INCLUDE_DIR . 'util/sort.php';
require_once AS_INCLUDE_DIR . 'util/string.php';
require_once AS_INCLUDE_DIR . 'app/captcha.php';
require_once AS_INCLUDE_DIR . 'pages/song-view.php';
require_once AS_INCLUDE_DIR . 'app/updates.php';

$songid = as_request_part(0);
$userid = as_get_logged_in_userid();
$cookieid = as_cookie_get();
$pagestate = as_get_state();


// Get information about this song

$cacheDriver = APS_Storage_CacheFactory::getCacheDriver();
$cacheKey = "song:$songid";
$useCache = $userid === null && $cacheDriver->isEnabled() && !as_is_http_post() && empty($pagestate);
$saveCache = false;

if ($useCache) {
	$songData = $cacheDriver->get($cacheKey);
}

if (!isset($songData)) {
	$songData = as_db_select_with_pending(
		as_db_full_post_selectspec($userid, $songid),
		as_db_full_child_posts_selectspec($userid, $songid),
		as_db_full_a_child_posts_selectspec($userid, $songid),
		as_db_post_parent_q_selectspec($songid),
		as_db_post_close_post_selectspec($songid),
		as_db_post_duplicates_selectspec($songid),
		as_db_post_meta_selectspec($songid, 'as_q_extra'),
		as_db_category_nav_selectspec($songid, true, true, true),
		isset($userid) ? as_db_is_favorite_selectspec($userid, AS_ENTITY_SONG, $songid) : null
	);

	// whether to save the cache (actioned below, after basic checks)
	$saveCache = $useCache;
}

list($song, $childposts, $achildposts, $parentsong, $closepost, $duplicateposts, $extravalue, $categories, $favorite) = $songData;


if ($song['basetype'] != 'S') // don't allow direct viewing of other types of post
	$song = null;

if (isset($song)) {
	$q_request = as_q_request($songid, $song['title']);

	if (trim($q_request, '/') !== trim(as_request(), '/')) {
		// redirect if the current URL is incorrect
		as_redirect($q_request);
	}

	$song['extra'] = $extravalue;

	$reviews = as_page_q_load_as($song, $childposts);
	$commentsfollows = as_page_q_load_c_follows($song, $childposts, $achildposts, $duplicateposts);

	$song = $song + as_page_q_post_rules($song, null, null, $childposts + $duplicateposts); // array union

	if ($song['selchildid'] && (@$reviews[$song['selchildid']]['type'] != 'R'))
		$song['selchildid'] = null; // if selected review is hidden or somehow not there, consider it not selected

	foreach ($reviews as $key => $review) {
		$reviews[$key] = $review + as_page_q_post_rules($review, $song, $reviews, $achildposts);
		$reviews[$key]['isselected'] = ($review['postid'] == $song['selchildid']);
	}

	foreach ($commentsfollows as $key => $commentfollow) {
		$parent = ($commentfollow['parentid'] == $songid) ? $song : @$reviews[$commentfollow['parentid']];
		$commentsfollows[$key] = $commentfollow + as_page_q_post_rules($commentfollow, $parent, $commentsfollows, null);
	}
}

// Deal with song not found or not viewable, otherwise report the view event

if (!isset($song))
	return include AS_INCLUDE_DIR . 'as-page-not-found.php';

if (!$song['viewable']) {
	$as_content = as_content_prepare();

	if ($song['queued'])
		$as_content['error'] = as_lang_html('song/q_waiting_approval');
	elseif ($song['flagcount'] && !isset($song['lastuserid']))
		$as_content['error'] = as_lang_html('song/q_hidden_flagged');
	elseif ($song['authorlast'])
		$as_content['error'] = as_lang_html('song/q_hidden_author');
	else
		$as_content['error'] = as_lang_html('song/q_hidden_other');

	$as_content['suggest_next'] = as_html_suggest_qs_tags(as_using_tags());

	return $as_content;
}

$permiterror = as_user_post_permit_error('permit_view_q_page', $song, null, false);

if ($permiterror && (as_is_human_probably() || !as_opt('allow_view_q_bots'))) {
	$as_content = as_content_prepare();
	$topage = as_q_request($songid, $song['title']);

	switch ($permiterror) {
		case 'signin':
			$as_content['error'] = as_insert_signin_links(as_lang_html('main/view_q_must_signin'), $topage);
			break;

		case 'confirm':
			$as_content['error'] = as_insert_signin_links(as_lang_html('main/view_q_must_confirm'), $topage);
			break;

		case 'approve':
			$as_content['error'] = strtr(as_lang_html('main/view_q_must_be_approved'), array(
				'^1' => '<a href="' . as_path_html('account') . '">',
				'^2' => '</a>',
			));
			break;

		default:
			$as_content['error'] = as_lang_html('users/no_permission');
			break;
	}

	return $as_content;
}


// Save song data to cache (if older than configured limit)

if ($saveCache) {
	$songAge = as_opt('db_time') - $song['created'];
	if ($songAge > 86400 * as_opt('caching_q_start')) {
		$cacheDriver->set($cacheKey, $songData, as_opt('caching_q_time'));
	}
}


// Determine if captchas will be required

$captchareason = as_user_captcha_reason(as_user_level_for_post($song));
$usecaptcha = ($captchareason != false);


// If we're responding to an HTTP POST, include file that handles all posting/editing/etc... logic
// This is in a separate file because it's a *lot* of logic, and will slow down ordinary page views

$pagestart = as_get_start();
$showid = as_get('show');
$pageerror = null;
$formtype = null;
$formpostid = null;
$jumptoanchor = null;
$commentsall = null;

if (substr($pagestate, 0, 13) == 'showcomments-') {
	$commentsall = substr($pagestate, 13);
	$pagestate = null;

} elseif (isset($showid)) {
	foreach ($commentsfollows as $comment) {
		if ($comment['postid'] == $showid) {
			$commentsall = $comment['parentid'];
			break;
		}
	}
}

if (as_is_http_post() || strlen($pagestate))
	require AS_INCLUDE_DIR . 'pages/song-post.php';

$formrequested = isset($formtype);

if (!$formrequested && $song['reviewbutton']) {
	$immedoption = as_opt('show_a_form_immediate');

	if ($immedoption == 'always' || ($immedoption == 'if_no_as' && !$song['isbyuser'] && !$song['acount']))
		$formtype = 'a_add'; // show review form by default
}


// Get information on the users referenced

$usershtml = as_userids_handles_html(array_merge(array($song), $reviews, $commentsfollows), true);


// Prepare content for theme

$as_content = as_content_prepare(true, array_keys(as_category_path($categories, $song['categoryid'])));

if (isset($userid) && !$formrequested)
	$as_content['favorite'] = as_favorite_form(AS_ENTITY_SONG, $songid, $favorite,
		as_lang($favorite ? 'song/remove_q_favorites' : 'song/add_q_favorites'));

if (isset($pageerror))
	$as_content['error'] = $pageerror; // might also show thumbing error set in as-index.php

elseif ($song['queued'])
	$as_content['error'] = $song['isbyuser'] ? as_lang_html('song/q_your_waiting_approval') : as_lang_html('song/q_waiting_your_approval');

if ($song['hidden'])
	$as_content['hidden'] = true;

as_sort_by($commentsfollows, 'created');


// Prepare content for the song...

if ($formtype == 'q_edit') { // ...in edit mode
	$as_content['title'] = as_lang_html($song['editable'] ? 'song/edit_q_title' :
		(as_using_categories() ? 'song/recat_q_title' : 'song/retag_q_title'));
	$as_content['form_q_edit'] = as_page_q_edit_q_form($as_content, $song, @$qin, @$qerrors, $completetags, $categories);
	$as_content['q_view']['raw'] = $song;

} else { // ...in view mode
	$as_content['q_view'] = as_page_q_song_view($song, $parentsong, $closepost, $usershtml, $formrequested);

	$as_content['title'] = $as_content['q_view']['title'];

	$as_content['description'] = as_html(as_shorten_string_line(as_viewer_text($song['content'], $song['format']), 150));

	$categorykeyword = @$categories[$song['categoryid']]['title'];

	$as_content['keywords'] = as_html(implode(',', array_merge(
		(as_using_categories() && strlen($categorykeyword)) ? array($categorykeyword) : array(),
		as_tagstring_to_tags($song['tags'])
	))); // as far as I know, META keywords have zero effect on search rankings or listings, but many people have posted for this
}

$microdata = as_opt('use_microdata');
if ($microdata) {
	$as_content['head_lines'][] = '<meta itemprop="name" content="' . as_html($as_content['q_view']['raw']['title']) . '">';
	$as_content['html_tags'] .= ' itemscope itemtype="https://schema.org/QAPage"';
	$as_content['wrapper_tags'] = ' itemprop="mainEntity" itemscope itemtype="https://schema.org/Song"';
}


// Prepare content for an review being edited (if any) or to be added

if ($formtype == 'a_edit') {
	$as_content['a_form'] = as_page_q_edit_a_form($as_content, 'a' . $formpostid, $reviews[$formpostid],
		$song, $reviews, $commentsfollows, @$aeditin[$formpostid], @$aediterrors[$formpostid]);

	$as_content['a_form']['c_list'] = as_page_q_comment_follow_list($song, $reviews[$formpostid],
		$commentsfollows, true, $usershtml, $formrequested, $formpostid);

	$jumptoanchor = 'a' . $formpostid;

} elseif ($formtype == 'a_add' || ($song['reviewbutton'] && !$formrequested)) {
	$as_content['a_form'] = as_page_q_add_a_form($as_content, 'anew', $captchareason, $song, @$anewin, @$anewerrors, $formtype == 'a_add', $formrequested);

	if ($formrequested) {
		$jumptoanchor = 'anew';
	} elseif ($formtype == 'a_add') {
		$as_content['script_onloads'][] = array(
			"as_element_revealed=document.getElementById('anew');"
		);
	}
}


// Prepare content for comments on the song, plus add or edit comment forms

if ($formtype == 'q_close') {
	$as_content['q_view']['c_form'] = as_page_q_close_q_form($as_content, $song, 'close', @$closein, @$closeerrors);
	$jumptoanchor = 'close';

} elseif (($formtype == 'c_add' && $formpostid == $songid) || ($song['commentbutton'] && !$formrequested)) { // ...to be added
	$as_content['q_view']['c_form'] = as_page_q_add_c_form($as_content, $song, $song, 'c' . $songid,
		$captchareason, @$cnewin[$songid], @$cnewerrors[$songid], $formtype == 'c_add');

	if ($formtype == 'c_add' && $formpostid == $songid) {
		$jumptoanchor = 'c' . $songid;
		$commentsall = $songid;
	}

} elseif ($formtype == 'c_edit' && @$commentsfollows[$formpostid]['parentid'] == $songid) { // ...being edited
	$as_content['q_view']['c_form'] = as_page_q_edit_c_form($as_content, 'c' . $formpostid, $commentsfollows[$formpostid],
		@$ceditin[$formpostid], @$cediterrors[$formpostid]);

	$jumptoanchor = 'c' . $formpostid;
	$commentsall = $songid;
}

$as_content['q_view']['c_list'] = as_page_q_comment_follow_list($song, $song, $commentsfollows,
	$commentsall == $songid, $usershtml, $formrequested, $formpostid); // ...for viewing


// Prepare content for existing reviews (could be added to by Ajax)

$as_content['a_list'] = array(
	'tags' => 'id="a_list"',
	'as' => array(),
);

// sort according to the site preferences

if (as_opt('sort_reviews_by') == 'thumbs') {
	foreach ($reviews as $reviewid => $review)
		$reviews[$reviewid]['sortthumbs'] = $review['thumbsdown'] - $review['thumbsup'];

	as_sort_by($reviews, 'sortthumbs', 'created');

} else {
	as_sort_by($reviews, 'created');
}

// further changes to ordering to deal with queued, hidden and selected reviews

$countfortitle = (int) $song['acount'];
$nextposition = 10000;
$reviewposition = array();

foreach ($reviews as $reviewid => $review) {
	if ($review['viewable']) {
		$position = $nextposition++;

		if ($review['hidden'])
			$position += 10000;

		elseif ($review['queued']) {
			$position -= 10000;
			$countfortitle++; // include these in displayed count

		} elseif ($review['isselected'] && as_opt('show_selected_first'))
			$position -= 5000;

		$reviewposition[$reviewid] = $position;
	}
}

asort($reviewposition, SORT_NUMERIC);

// extract IDs and prepare for pagination

$reviewids = array_keys($reviewposition);
$countforpages = count($reviewids);
$pagesize = as_opt('page_size_q_as');

// see if we need to display a particular review

if (isset($showid)) {
	if (isset($commentsfollows[$showid]))
		$showid = $commentsfollows[$showid]['parentid'];

	$position = array_search($showid, $reviewids);

	if (is_numeric($position))
		$pagestart = floor($position / $pagesize) * $pagesize;
}

// set the canonical url based on possible pagination

$as_content['canonical'] = as_path_html(as_q_request($song['postid'], $song['title']),
	($pagestart > 0) ? array('start' => $pagestart) : null, as_opt('site_url'));

// build the actual review list

$reviewids = array_slice($reviewids, $pagestart, $pagesize);

foreach ($reviewids as $reviewid) {
	$review = $reviews[$reviewid];

	if (!($formtype == 'a_edit' && $formpostid == $reviewid)) {
		$a_view = as_page_q_review_view($song, $review, $review['isselected'], $usershtml, $formrequested);

		// Prepare content for comments on this review, plus add or edit comment forms

		if (($formtype == 'c_add' && $formpostid == $reviewid) || ($review['commentbutton'] && !$formrequested)) { // ...to be added
			$a_view['c_form'] = as_page_q_add_c_form($as_content, $song, $review, 'c' . $reviewid,
				$captchareason, @$cnewin[$reviewid], @$cnewerrors[$reviewid], $formtype == 'c_add');

			if ($formtype == 'c_add' && $formpostid == $reviewid) {
				$jumptoanchor = 'c' . $reviewid;
				$commentsall = $reviewid;
			}

		} elseif ($formtype == 'c_edit' && @$commentsfollows[$formpostid]['parentid'] == $reviewid) { // ...being edited
			$a_view['c_form'] = as_page_q_edit_c_form($as_content, 'c' . $formpostid, $commentsfollows[$formpostid],
				@$ceditin[$formpostid], @$cediterrors[$formpostid]);

			$jumptoanchor = 'c' . $formpostid;
			$commentsall = $reviewid;
		}

		$a_view['c_list'] = as_page_q_comment_follow_list($song, $review, $commentsfollows,
			$commentsall == $reviewid, $usershtml, $formrequested, $formpostid); // ...for viewing

		// Add the review to the list

		$as_content['a_list']['as'][] = $a_view;
	}
}

if ($song['basetype'] == 'S') {
	$as_content['a_list']['title_tags'] = 'id="a_list_title"';

	$split = $countfortitle == 1
		? as_lang_html_sub_split('song/1_review_title', '1', '1')
		: as_lang_html_sub_split('song/x_reviews_title', $countfortitle);

	if ($microdata) {
		$split['data'] = '<span itemprop="reviewCount">' . $split['data'] . '</span>';
	}

	$as_content['a_list']['title'] = $split['prefix'] . $split['data'] . $split['suffix'];

	if ($countfortitle == 0) {
		$as_content['a_list']['title_tags'] .= ' style="display:none;" ';
	}
}

if (!$formrequested) {
	$as_content['page_links'] = as_html_page_links(as_request(), $pagestart, $pagesize, $countforpages, as_opt('pages_prev_next'), array(), false, 'a_list_title');
}


// Some generally useful stuff

if (as_using_categories() && count($categories)) {
	$as_content['navigation']['cat'] = as_category_navigation($categories, $song['categoryid']);
}

if (isset($jumptoanchor)) {
	$as_content['script_onloads'][] = array(
		'as_scroll_page_to($("#"+' . as_js($jumptoanchor) . ').offset().top);'
	);
}


// Determine whether this request should be counted for page view statistics.
// The lastviewip check is now part of the hotness query in order to bypass caching.

if (as_opt('do_count_q_views') && !$formrequested && !as_is_http_post() && as_is_human_probably() &&
	(!$song['views'] || (
		// if it has more than zero views, then it must be different IP & user & cookieid from the creator
		(@inet_ntop($song['createip']) != as_remote_ip_address() || !isset($song['createip'])) &&
		($song['userid'] != $userid || !isset($song['userid'])) &&
		($song['cookieid'] != $cookieid || !isset($song['cookieid']))
	))
) {
	$as_content['inc_views_postid'] = $songid;
}


return $as_content;
