<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Server-side response to Ajax create review requests


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

require_once AS_INCLUDE_DIR . 'app/posts.php';
require_once AS_INCLUDE_DIR . 'app/users.php';
require_once AS_INCLUDE_DIR . 'app/limits.php';
require_once AS_INCLUDE_DIR . 'db/selects.php';


// Load relevant information about this song

$songid = as_post_text('a_songid');
$userid = as_get_logged_in_userid();

list($song, $childposts) = as_db_select_with_pending(
	as_db_full_post_selectspec($userid, $songid),
	as_db_full_child_posts_selectspec($userid, $songid)
);


// Check if the song exists, is not closed, and whether the user has permission to do this

if (@$song['basetype'] == 'S' && !as_post_is_closed($song) && !as_user_post_permit_error('permit_post_a', $song, AS_LIMIT_REVIEWS)) {
	require_once AS_INCLUDE_DIR . 'app/captcha.php';
	require_once AS_INCLUDE_DIR . 'app/format.php';
	require_once AS_INCLUDE_DIR . 'app/post-create.php';
	require_once AS_INCLUDE_DIR . 'app/cookies.php';
	require_once AS_INCLUDE_DIR . 'pages/song-view.php';
	require_once AS_INCLUDE_DIR . 'pages/song-submit.php';


	// Try to create the new review

	$usecaptcha = as_user_use_captcha(as_user_level_for_post($song));
	$reviews = as_page_q_load_as($song, $childposts);
	$reviewid = as_page_q_add_a_submit($song, $reviews, $usecaptcha, $in, $errors);

	// If successful, page content will be updated via Ajax

	if (isset($reviewid)) {
		$review = as_db_select_with_pending(as_db_full_post_selectspec($userid, $reviewid));

		$song = $song + as_page_q_post_rules($song, null, null, $childposts); // array union
		$review = $review + as_page_q_post_rules($review, $song, $reviews, null);

		$usershtml = as_userids_handles_html(array($review), true);

		$a_view = as_page_q_review_view($song, $review, false, $usershtml, false);

		$themeclass = as_load_theme_class(as_get_site_theme(), 'ajax-review', null, null);
		$themeclass->initialize();

		echo "AS_AJAX_RESPONSE\n1\n";


		// Send back whether the 'review' button should still be visible

		echo (int)as_opt('allow_multi_reviews') . "\n";


		// Send back the count of reviews

		$countreviews = $song['acount'] + 1;

		if ($countreviews == 1) {
			echo as_lang_html('song/1_review_title') . "\n";
		} else {
			echo as_lang_html_sub('song/x_reviews_title', $countreviews) . "\n";
		}


		// Send back the HTML

		$themeclass->a_list_item($a_view);

		return;
	}
}


echo "AS_AJAX_RESPONSE\n0\n"; // fall back to non-Ajax submission if there were any problems
