<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Basic editor module for plain text editing


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

class as_editor_basic
{
	public function load_module($localdir, $htmldir)
	{
	}

	public function calc_quality($content, $format)
	{
		if ($format == '')
			return 1.0;

		if ($format == 'html')
			return 0.2;

		return 0;
	}

	public function get_field(&$as_content, $content, $format, $fieldname, $rows /* $autofocus parameter deprecated */)
	{
		return array(
			'type' => 'textarea',
			'tags' => 'name="' . $fieldname . '" id="' . $fieldname . '"',
			'value' => as_html($content),
			'rows' => $rows,
		);
	}

	public function focus_script($fieldname)
	{
		return "document.getElementById('" . $fieldname . "').focus();";
	}

	public function read_post($fieldname)
	{
		return array(
			'format' => '',
			'content' => as_post_text($fieldname),
		);
	}
}
