<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	File: as-plugin/tag-cloud-widget/as-tag-cloud.php
	Description: Widget module class for tag cloud plugin


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

class as_tag_cloud
{
	public function option_default($option)
	{
		switch ($option) {
			case 'tag_cloud_count_tags':
				return 100;
			case 'tag_cloud_font_size':
				return 24;
			case 'tag_cloud_minimal_font_size':
				return 10;
			case 'tag_cloud_size_popular':
				return true;
		}
	}


	public function admin_form()
	{
		$saved = as_clicked('tag_cloud_save_button');

		if ($saved) {
			as_opt('tag_cloud_count_tags', (int) as_post_text('tag_cloud_count_tags_field'));
			as_opt('tag_cloud_font_size', (int) as_post_text('tag_cloud_font_size_field'));
			as_opt('tag_cloud_minimal_font_size', (int) as_post_text('tag_cloud_minimal_font_size_field'));
			as_opt('tag_cloud_size_popular', (int) as_post_text('tag_cloud_size_popular_field'));
		}

		return array(
			'ok' => $saved ? 'Tag cloud settings saved' : null,

			'fields' => array(
				array(
					'label' => 'Maximum tags to show:',
					'type' => 'number',
					'value' => (int) as_opt('tag_cloud_count_tags'),
					'suffix' => 'tags',
					'tags' => 'name="tag_cloud_count_tags_field"',
				),

				array(
					'label' => 'Biggest font size:',
					'suffix' => 'pixels',
					'type' => 'number',
					'value' => (int) as_opt('tag_cloud_font_size'),
					'tags' => 'name="tag_cloud_font_size_field"',
				),

				array(
					'label' => 'Smallest allowed font size:',
					'suffix' => 'pixels',
					'type' => 'number',
					'value' => (int) as_opt('tag_cloud_minimal_font_size'),
					'tags' => 'name="tag_cloud_minimal_font_size_field"',
				),

				array(
					'label' => 'Font size represents tag popularity',
					'type' => 'checkbox',
					'value' => as_opt('tag_cloud_size_popular'),
					'tags' => 'name="tag_cloud_size_popular_field"',
				),
			),

			'buttons' => array(
				array(
					'label' => 'Save Changes',
					'tags' => 'name="tag_cloud_save_button"',
				),
			),
		);
	}


	public function allow_template($template)
	{
		$allowed = array(
			'activity', 'as', 'songs', 'hot', 'post', 'categories', 'song',
			'tag', 'tags', 'unreviewed', 'user', 'users', 'search', 'admin', 'custom',
		);
		return in_array($template, $allowed);
	}


	public function allow_region($region)
	{
		return ($region === 'side');
	}


	public function output_widget($region, $place, $themeobject, $template, $request, $as_content)
	{
		require_once AS_INCLUDE_DIR.'db/selects.php';

		$populartags = as_db_single_select(as_db_popular_tags_selectspec(0, (int) as_opt('tag_cloud_count_tags')));

		$populartagslog = array_map(array($this, 'log_callback'), $populartags);

		$maxcount = reset($populartagslog);

		$themeobject->output(sprintf('<h2 style="margin-top: 0; padding-top: 0;">%s</h2>', as_lang_html('main/popular_tags')));

		$themeobject->output('<div style="font-size: 10px;">');

		$maxsize = as_opt('tag_cloud_font_size');
		$minsize = as_opt('tag_cloud_minimal_font_size');
		$scale = as_opt('tag_cloud_size_popular');
		$blockwordspreg = as_get_block_words_preg();

		foreach ($populartagslog as $tag => $count) {
			$matches = as_block_words_match_all($tag, $blockwordspreg);
			if (!empty($matches)) {
				continue;
			}

			if ($scale) {
				$size = number_format($maxsize * $count / $maxcount, 1);
				if ($size < $minsize) {
					$size = $minsize;
				}
			} else {
				$size = $maxsize;
			}

			$themeobject->output(sprintf('<a href="%s" style="font-size: %dpx; vertical-align: baseline;">%s</a>', as_path_html('tag/' . $tag), $size, as_html($tag)));
		}

		$themeobject->output('</div>');
	}

	private function log_callback($e)
	{
		return log($e) + 1;
	}
}
