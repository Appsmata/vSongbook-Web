<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Server-side response to Ajax request based on post a song title


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

require_once AS_INCLUDE_DIR . 'db/selects.php';
require_once AS_INCLUDE_DIR . 'util/string.php';
require_once AS_INCLUDE_DIR . 'app/users.php';
require_once AS_INCLUDE_DIR . 'app/format.php';


// Collect the information we need from the database

$intitle = as_post_text('title');
$dopostcheck = as_opt('do_post_check_qs');
$doexampletags = as_using_tags() && as_opt('do_example_tags');

if ($dopostcheck || $doexampletags) {
	$countqs = max($doexampletags ? AS_DB_RETRIEVE_ASK_TAG_QS : 0, $dopostcheck ? as_opt('page_size_post_check_qs') : 0);

	$relatedsongs = as_db_select_with_pending(
		as_db_search_posts_selectspec(null, as_string_to_words($intitle), null, null, null, null, 0, false, $countqs)
	);
}


// Collect example tags if appropriate

if ($doexampletags) {
	$tagweight = array();
	foreach ($relatedsongs as $song) {
		$tags = as_tagstring_to_tags($song['tags']);
		foreach ($tags as $tag) {
			@$tagweight[$tag] += exp($song['score']);
		}
	}

	arsort($tagweight, SORT_NUMERIC);

	$exampletags = array();

	$minweight = exp(as_match_to_min_score(as_opt('match_example_tags')));
	$maxcount = as_opt('page_size_post_tags');

	foreach ($tagweight as $tag => $weight) {
		if ($weight < $minweight)
			break;

		$exampletags[] = $tag;
		if (count($exampletags) >= $maxcount)
			break;
	}
} else {
	$exampletags = array();
}


// Output the response header and example tags

echo "AS_AJAX_RESPONSE\n1\n";

echo strtr(as_html(implode(',', $exampletags)), "\r\n", '  ') . "\n";


// Collect and output the list of related songs

if ($dopostcheck) {
	$minscore = as_match_to_min_score(as_opt('match_post_check_qs'));
	$maxcount = as_opt('page_size_post_check_qs');

	$relatedsongs = array_slice($relatedsongs, 0, $maxcount);
	$limitedsongs = array();

	foreach ($relatedsongs as $song) {
		if ($song['score'] < $minscore)
			break;

		$limitedsongs[] = $song;
	}

	$themeclass = as_load_theme_class(as_get_site_theme(), 'ajax-posttitle', null, null);
	$themeclass->initialize();
	$themeclass->q_post_similar($limitedsongs, as_lang_html('song/post_same_q'));
}
