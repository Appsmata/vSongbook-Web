<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Widget module class for related songs


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

class as_related_qs
{
	public function allow_template($template)
	{
		return $template == 'song';
	}

	public function allow_region($region)
	{
		return in_array($region, array('side', 'main', 'full'));
	}

	public function output_widget($region, $place, $themeobject, $template, $request, $as_content)
	{
		require_once AS_INCLUDE_DIR . 'db/selects.php';

		if (!isset($as_content['q_view']['raw']['type']) || $as_content['q_view']['raw']['type'] != 'S') // song might not be visible, etc...
			return;

		$songid = $as_content['q_view']['raw']['postid'];

		$userid = as_get_logged_in_userid();
		$cookieid = as_cookie_get();

		$songs = as_db_single_select(as_db_related_qs_selectspec($userid, $songid, as_opt('page_size_related_qs')));

		$minscore = as_match_to_min_score(as_opt('match_related_qs'));

		foreach ($songs as $key => $song) {
			if ($song['score'] < $minscore)
				unset($songs[$key]);
		}

		$titlehtml = as_lang_html(count($songs) ? 'main/related_qs_title' : 'main/no_related_qs_title');

		if ($region == 'side') {
			$themeobject->output(
				'<div class="as-related-qs">',
				'<h2 style="margin-top:0; padding-top:0;">',
				$titlehtml,
				'</h2>'
			);

			$themeobject->output('<ul class="as-related-q-list">');

			foreach ($songs as $song) {
				$themeobject->output(
					'<li class="as-related-q-item">' .
					'<a href="' . as_q_path_html($song['postid'], $song['title']) . '">' .
					as_html($song['title']) .
					'</a>' .
					'</li>'
				);
			}

			$themeobject->output(
				'</ul>',
				'</div>'
			);
		} else {
			$themeobject->output(
				'<h2>',
				$titlehtml,
				'</h2>'
			);

			$s_list = array(
				'form' => array(
					'tags' => 'method="post" action="' . as_self_html() . '"',
					'hidden' => array(
						'code' => as_get_form_security_code('thumb'),
					),
				),
				'qs' => array(),
			);

			$defaults = as_post_html_defaults('S');
			$usershtml = as_userids_handles_html($songs);

			foreach ($songs as $song) {
				$s_list['qs'][] = as_post_html_fields($song, $userid, $cookieid, $usershtml, null, as_post_html_options($song, $defaults));
			}

			$themeobject->s_list_and_form($s_list);
		}
	}
}
