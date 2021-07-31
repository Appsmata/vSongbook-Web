<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Handles favoriting and unfavoriting (application level)


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
 * Set an entity to be favorited or removed from favorites. Handles event reporting.
 *
 * @param int $userid ID of user assigned to the favorite
 * @param string $handle Username of user
 * @param string $cookieid Cookie ID of user
 * @param string $entitytype Entity type code (one of AS_ENTITY_* constants)
 * @param string $entityid ID of the entity being favorited (e.g. postid for songs)
 * @param bool $favorite Whether to add favorite (true) or remove favorite (false)
 */
function as_user_favorite_set($userid, $handle, $cookieid, $entitytype, $entityid, $favorite)
{
	require_once AS_INCLUDE_DIR . 'db/favorites.php';
	require_once AS_INCLUDE_DIR . 'app/limits.php';
	require_once AS_INCLUDE_DIR . 'app/updates.php';

	// Make sure the user is not favoriting themselves
	if ($entitytype == AS_ENTITY_USER && $userid == $entityid) {
		return;
	}

	if ($favorite)
		as_db_favorite_create($userid, $entitytype, $entityid);
	else
		as_db_favorite_delete($userid, $entitytype, $entityid);

	switch ($entitytype) {
		case AS_ENTITY_SONG:
			$action = $favorite ? 'q_favorite' : 'q_unfavorite';
			$params = array('postid' => $entityid);
			break;

		case AS_ENTITY_USER:
			$action = $favorite ? 'u_favorite' : 'u_unfavorite';
			$params = array('userid' => $entityid);
			break;

		case AS_ENTITY_TAG:
			$action = $favorite ? 'tag_favorite' : 'tag_unfavorite';
			$params = array('wordid' => $entityid);
			break;

		case AS_ENTITY_CATEGORY:
			$action = $favorite ? 'cat_favorite' : 'cat_unfavorite';
			$params = array('categoryid' => $entityid);
			break;

		default:
			as_fatal_error('Favorite type not recognized');
			break;
	}

	as_report_event($action, $userid, $handle, $cookieid, $params);
}


/**
 * Returns content to set in $as_content['s_list'] for a user's favorite $songs. Pre-generated
 * user HTML in $usershtml.
 * @param $songs
 * @param $usershtml
 * @return array
 */
function as_favorite_s_list_view($songs, $usershtml)
{
	$s_list = array(
		'qs' => array(),
	);

	if (count($songs) === 0)
		return $s_list;

	$s_list['form'] = array(
		'tags' => 'method="post" action="' . as_self_html() . '"',
		'hidden' => array(
			'code' => as_get_form_security_code('thumb'),
		),
	);

	$defaults = as_post_html_defaults('S');

	foreach ($songs as $song) {
		$s_list['qs'][] = as_post_html_fields($song, as_get_logged_in_userid(), as_cookie_get(),
			$usershtml, null, as_post_html_options($song, $defaults));
	}

	return $s_list;
}


/**
 * Returns content to set in $as_content['ranking_users'] for a user's favorite $users. Pre-generated
 * user HTML in $usershtml.
 * @param $users
 * @param $usershtml
 * @return array|null
 */
function as_favorite_users_view($users, $usershtml)
{
	if (AS_FINAL_EXTERNAL_USERS)
		return null;

	require_once AS_INCLUDE_DIR . 'app/users.php';
	require_once AS_INCLUDE_DIR . 'app/format.php';

	$ranking = array(
		'items' => array(),
		'rows' => ceil(count($users) / as_opt('columns_users')),
		'type' => 'users',
	);

	foreach ($users as $user) {
		$avatarhtml = as_get_user_avatar_html($user['flags'], $user['email'], $user['handle'],
			$user['avatarblobid'], $user['avatarwidth'], $user['avatarheight'], as_opt('avatar_users_size'), true);

		$ranking['items'][] = array(
			'avatar' => $avatarhtml,
			'label' => $usershtml[$user['userid']],
			'score' => as_html(as_format_number($user['points'], 0, true)),
			'raw' => $user,
		);
	}

	return $ranking;
}


/**
 * Returns content to set in $as_content['ranking_tags'] for a user's favorite $tags.
 * @param $tags
 * @return array
 */
function as_favorite_tags_view($tags)
{
	require_once AS_INCLUDE_DIR . 'app/format.php';

	$ranking = array(
		'items' => array(),
		'rows' => ceil(count($tags) / as_opt('columns_tags')),
		'type' => 'tags',
	);

	foreach ($tags as $tag) {
		$ranking['items'][] = array(
			'label' => as_tag_html($tag['word'], false, true),
			'count' => as_html(as_format_number($tag['tagcount'], 0, true)),
		);
	}

	return $ranking;
}


/**
 * Returns content to set in $as_content['nav_list_categories'] for a user's favorite $categories.
 * @param $categories
 * @return array
 */
function as_favorite_categories_view($categories)
{
	require_once AS_INCLUDE_DIR . 'app/format.php';

	$nav_list_categories = array(
		'nav' => array(),
		'type' => 'browse-cat',
	);

	foreach ($categories as $category) {
		$cat_url = as_path_html('songs/' . implode('/', array_reverse(explode('/', $category['backpath']))));
		$cat_anchor = $category['qcount'] == 1
			? as_lang_html_sub('main/1_song', '1', '1')
			: as_lang_html_sub('main/x_songs', as_format_number($category['qcount'], 0, true));
		$cat_descr = strlen($category['content']) ? as_html(' - ' . $category['content']) : '';

		$nav_list_categories['nav'][$category['categoryid']] = array(
			'label' => as_html($category['title']),
			'state' => 'open',
			'favorited' => true,
			'note' => ' - <a href="' . $cat_url . '">' . $cat_anchor . '</a>' . $cat_descr,
		);
	}

	return $nav_list_categories;
}
