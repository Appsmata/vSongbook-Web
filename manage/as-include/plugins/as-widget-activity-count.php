<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Widget module class for activity count plugin


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

class as_activity_count
{
	public function allow_template($template)
	{
		return true;
	}

	public function allow_region($region)
	{
		return ($region == 'side');
	}

	public function output_widget($region, $place, $themeobject, $template, $request, $as_content)
	{
		$themeobject->output('<div class="as-activity-count">');

		$this->output_count($themeobject, as_opt('cache_bcount'), 'main/1_book', 'main/x_books');
		$this->output_count($themeobject, as_opt('cache_qcount'), 'main/1_song', 'main/x_songs');
		$this->output_count($themeobject, as_opt('cache_acount'), 'main/1_review', 'main/x_reviews');

		if (as_opt('comment_on_qs') || as_opt('comment_on_as'))
			$this->output_count($themeobject, as_opt('cache_ccount'), 'main/1_comment', 'main/x_comments');

		$this->output_count($themeobject, as_opt('cache_userpointscount'), 'main/1_user', 'main/x_users');

		$themeobject->output('</div>');
	}

	public function output_count($themeobject, $value, $langsingular, $langplural)
	{
		require_once AS_INCLUDE_DIR . 'app/format.php';

		$themeobject->output('<p class="as-activity-count-item">');

		if ($value == 1)
			$themeobject->output(as_lang_html_sub($langsingular, '<span class="as-activity-count-data">1</span>', '1'));
		else
			$themeobject->output(as_lang_html_sub($langplural, '<span class="as-activity-count-data">' . as_format_number((int)$value, 0, true) . '</span>'));

		$themeobject->output('</p>');
	}
}
