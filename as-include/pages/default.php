<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Controller for home page, Q&A listing page, custom pages and plugin pages


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

$as_content = as_content_prepare();

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

list($categories, $songs) = as_db_select_with_pending(
	as_db_category_enabled(),
	as_db_posts_select($userid, 1)
);

$as_content['vsonghome'] = array(
	
);

foreach ($categories as $book)
{
	$as_content['vsonghome']['booklist'][$book['categoryid']] = $book['title'] . ' ('.$book['qcount'].')';
}

foreach ($songs as $song)
{
	$as_content['vsonghome']['songlist'][$song['postid']] = array(
		$song['number'] . '. '.$song['title'], $song['content']);
}

return $as_content;
