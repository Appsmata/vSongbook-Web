<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Basic module for indexing and searching APS posts


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

class as_search_basic
{
	public function index_post($postid, $type, $songid, $parentid, $title, $content, $format, $text, $tagstring, $categoryid)
	{
		require_once AS_INCLUDE_DIR . 'db/post-create.php';

		// Get words from each textual element

		$titlewords = array_unique(as_string_to_words($title));
		$contentcount = array_count_values(as_string_to_words($text));
		$tagwords = array_unique(as_string_to_words($tagstring));
		$wholetags = array_unique(as_tagstring_to_tags($tagstring));

		// Map all words to their word IDs

		$words = array_unique(array_merge($titlewords, array_keys($contentcount), $tagwords, $wholetags));
		$wordtoid = as_db_word_mapto_ids_add($words);

		// Add to title words index

		$titlewordids = as_array_filter_by_keys($wordtoid, $titlewords);
		as_db_titlewords_add_post_wordids($postid, $titlewordids);

		// Add to content words index (including word counts)

		$contentwordidcounts = array();
		foreach ($contentcount as $word => $count) {
			if (isset($wordtoid[$word]))
				$contentwordidcounts[$wordtoid[$word]] = $count;
		}

		as_db_contentwords_add_post_wordidcounts($postid, $type, $songid, $contentwordidcounts);

		// Add to tag words index

		$tagwordids = as_array_filter_by_keys($wordtoid, $tagwords);
		as_db_tagwords_add_post_wordids($postid, $tagwordids);

		// Add to whole tags index

		$wholetagids = as_array_filter_by_keys($wordtoid, $wholetags);
		as_db_posttags_add_post_wordids($postid, $wholetagids);

		// Update counts cached in database (will be skipped if as_suspend_update_counts() was called

		as_db_word_titlecount_update($titlewordids);
		as_db_word_contentcount_update(array_keys($contentwordidcounts));
		as_db_word_tagwordcount_update($tagwordids);
		as_db_word_tagcount_update($wholetagids);
	}

	public function unindex_post($postid)
	{
		require_once AS_INCLUDE_DIR . 'db/post-update.php';

		$titlewordids = as_db_titlewords_get_post_wordids($postid);
		as_db_titlewords_delete_post($postid);
		as_db_word_titlecount_update($titlewordids);

		$contentwordids = as_db_contentwords_get_post_wordids($postid);
		as_db_contentwords_delete_post($postid);
		as_db_word_contentcount_update($contentwordids);

		$tagwordids = as_db_tagwords_get_post_wordids($postid);
		as_db_tagwords_delete_post($postid);
		as_db_word_tagwordcount_update($tagwordids);

		$wholetagids = as_db_posttags_get_post_wordids($postid);
		as_db_posttags_delete_post($postid);
		as_db_word_tagcount_update($wholetagids);
	}

	public function move_post($postid, $categoryid)
	{
		// for now, the built-in search engine ignores categories
	}

	public function index_page($pageid, $request, $title, $content, $format, $text)
	{
		// for now, the built-in search engine ignores custom pages
	}

	public function unindex_page($pageid)
	{
		// for now, the built-in search engine ignores custom pages
	}

	public function process_search($query, $start, $count, $userid, $absoluteurls, $fullcontent)
	{
		require_once AS_INCLUDE_DIR . 'db/selects.php';
		require_once AS_INCLUDE_DIR . 'util/string.php';

		$words = as_string_to_words($query);

		$songs = as_db_select_with_pending(
			as_db_search_posts_selectspec($userid, $words, $words, $words, $words, trim($query), $start, $fullcontent, $count)
		);

		$results = array();

		foreach ($songs as $song) {
			as_search_set_max_match($song, $type, $postid); // to link straight to best part

			$results[] = array(
				'song' => $song,
				'match_type' => $type,
				'match_postid' => $postid,
			);
		}

		return $results;
	}
}
