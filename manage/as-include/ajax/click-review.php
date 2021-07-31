<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Server-side response to Ajax single clicks on review


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

require_once AS_INCLUDE_DIR . 'app/cookies.php';
require_once AS_INCLUDE_DIR . 'app/format.php';
require_once AS_INCLUDE_DIR . 'app/users.php';
require_once AS_INCLUDE_DIR . 'db/selects.php';
require_once AS_INCLUDE_DIR . 'pages/song-view.php';
require_once AS_INCLUDE_DIR . 'pages/song-submit.php';
require_once AS_INCLUDE_DIR . 'util/sort.php';


// Load relevant information about this review

$reviewid = as_post_text('reviewid');
$songid = as_post_text('songid');

$userid = as_get_logged_in_userid();

list($review, $song, $qchildposts, $achildposts) = as_db_select_with_pending(
	as_db_full_post_selectspec($userid, $reviewid),
	as_db_full_post_selectspec($userid, $songid),
	as_db_full_child_posts_selectspec($userid, $songid),
	as_db_full_child_posts_selectspec($userid, $reviewid)
);


// Check if there was an operation that succeeded

if (@$review['basetype'] == 'R' && @$song['basetype'] == 'S') {
	$reviews = as_page_q_load_as($song, $qchildposts);

	$song = $song + as_page_q_post_rules($song, null, null, $qchildposts); // array union
	$review = $review + as_page_q_post_rules($review, $song, $qchildposts, $achildposts);

	if (as_page_q_single_click_a($review, $song, $reviews, $achildposts, false, $error)) {
		list($review, $song) = as_db_select_with_pending(
			as_db_full_post_selectspec($userid, $reviewid),
			as_db_full_post_selectspec($userid, $songid)
		);


		// If so, page content to be updated via Ajax

		echo "AS_AJAX_RESPONSE\n1\n";


		// Send back new count of reviews

		$countreviews = $song['acount'];

		if ($countreviews == 1)
			echo as_lang_html('song/1_review_title');
		else
			echo as_lang_html_sub('song/x_reviews_title', $countreviews);


		// If the review was not deleted....

		if (isset($review)) {
			$song = $song + as_page_q_post_rules($song, null, null, $qchildposts); // array union
			$review = $review + as_page_q_post_rules($review, $song, $qchildposts, $achildposts);

			$commentsfollows = as_page_q_load_c_follows($song, $qchildposts, $achildposts);

			foreach ($commentsfollows as $key => $commentfollow) {
				$commentsfollows[$key] = $commentfollow + as_page_q_post_rules($commentfollow, $review, $commentsfollows, null);
			}

			$usershtml = as_userids_handles_html(array_merge(array($review), $commentsfollows), true);
			as_sort_by($commentsfollows, 'created');

			$a_view = as_page_q_review_view($song, $review, ($review['postid'] == $song['selchildid'] && $review['type'] == 'R'),
				$usershtml, false);

			$a_view['c_list'] = as_page_q_comment_follow_list($song, $review, $commentsfollows, false, $usershtml, false, null);

			$themeclass = as_load_theme_class(as_get_site_theme(), 'ajax-review', null, null);
			$themeclass->initialize();


			// ... send back the HTML for it

			echo "\n";

			$themeclass->a_list_item($a_view);
		}

		return;
	}
}


echo "AS_AJAX_RESPONSE\n0\n"; // fall back to non-Ajax submission if something failed
