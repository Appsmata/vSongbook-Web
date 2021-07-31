<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Widget module class for post a song box


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

class as_post_box
{
	public function allow_template($template)
	{
		$allowed = array(
			'activity', 'categories', 'custom', 'feedback', 'as', 'songs',
			'hot', 'search', 'tag', 'tags', 'unreviewed',
		);
		return in_array($template, $allowed);
	}

	public function allow_region($region)
	{
		return in_array($region, array('main', 'side', 'full'));
	}

	public function output_widget($region, $place, $themeobject, $template, $request, $as_content)
	{
		if (isset($as_content['categoryids']))
			$params = array('cat' => end($as_content['categoryids']));
		else
			$params = null;

		?>
<div class="as-post-box">
	<form method="post" action="<?php echo as_path_html('post', $params); ?>">
		<table class="as-form-tall-table" style="width:100%">
			<tr style="vertical-align:middle;">
				<td class="as-form-tall-label" style="width: 1px; padding:8px; white-space:nowrap; <?php echo ($region=='side') ? 'padding-bottom:0;' : 'text-align:right;'?>">
					<?php echo strtr(as_lang_html('song/post_title'), array(' ' => '&nbsp;'))?>:
				</td>
		<?php if ($region=='side') : ?>
			</tr>
			<tr>
		<?php endif; ?>
				<td class="as-form-tall-data" style="padding:8px;">
					<input name="title" type="text" class="as-form-tall-text" style="width:95%;">
				</td>
			</tr>
		</table>
		<input type="hidden" name="dopost1" value="1">
	</form>
</div>
		<?php
	}
}
