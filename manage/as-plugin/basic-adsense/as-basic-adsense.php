<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	File: as-plugin/basic-adsense/as-basic-adsense.php
	Description: Widget module class for AdSense widget plugin


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

class as_basic_adsense
{
	public function allow_template($template)
	{
		return $template != 'admin';
	}


	public function allow_region($region)
	{
		return in_array($region, array('main', 'side', 'full'));
	}


	public function admin_form(&$as_content)
	{
		$saved = false;

		if (as_clicked('adsense_save_button')) {
			// prevent common errors by copying and pasting from Javascript
			$trimchars = "=;\"\' \t\r\n";
			as_opt('adsense_publisher_id', trim(as_post_text('adsense_publisher_id_field'), $trimchars));
			as_opt('adsense_adunit_id', trim(as_post_text('adsense_adunit_id_field'), $trimchars));

			$saved = true;
		}

		return array(
			'ok' => $saved ? 'AdSense settings saved' : null,

			'fields' => array(
				array(
					'label' => 'AdSense Publisher ID:',
					'value' => as_html(as_opt('adsense_publisher_id')),
					'tags' => 'name="adsense_publisher_id_field"',
					'note' => 'Example: <i>pub-1234567890123456</i>',
				),
				array(
					'label' => 'AdSense Ad Unit ID:',
					'value' => as_html(as_opt('adsense_adunit_id')),
					'tags' => 'name="adsense_adunit_id_field"',
					'note' => 'Example: <i>8XXXXX1</i>',
				),
			),

			'buttons' => array(
				array(
					'label' => 'Save Changes',
					'tags' => 'name="adsense_save_button"',
				),
			),
		);
	}


	public function output_widget($region, $place, $themeobject, $template, $request, $as_content)
	{
		$format = 'auto';

		switch ($region) {
			case 'full':
			case 'main':
				$format = 'horizontal';
				break;

			case 'side':
				$format = 'vertical';
				break;
		}

		?>
		<script async src="//pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script>
		<ins class="adsbygoogle <?php echo as_html($region) ?>"
			style="display:block; margin:.5em auto"
			data-ad-client="<?php echo as_html(as_opt('adsense_publisher_id')) ?>"
			data-ad-slot="<?php echo as_html(as_opt('adsense_adunit_id')) ?>"
			data-ad-format="<?php echo as_html($format) ?>">
		</ins>
		<script>
			(adsbygoogle = window.adsbygoogle || []).push({});
		</script>
		<?php
	}
}
