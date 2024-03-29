<?php
/*
	Snow Theme for vSongBook Package
	Copyright (C) 2014 APS Market <http://www.apsmarket.com>

	File:           as-theme.php
	Version:        Snow 1.4
	Description:    APS theme class

	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	GNU General Public License for more details.
*/

/**
 * Snow theme extends
 *
 * Extends the core theme class <code>as_html_theme_base</code>
 *
 * @package as_html_theme_base
 * @subpackage as_html_theme
 * @category Theme
 * @since Snow 1.0
 * @version 1.4
 * @author APS Market <http://www.apsmarket.com>
 * @copyright (c) 2014, APS Market
 * @license http://www.gnu.org/copyleft/gpl.html
 */
class as_html_theme extends as_html_theme_base
{
	protected $theme = 'snowflat';

	// use local font files instead of Google Fonts
	private $localfonts = true;

	// theme subdirectories
	private $js_dir = 'js';
	private $icon_url = 'images/icons';

	private $fixed_topbar = false;
	private $welcome_widget_class = 'wet-asphalt';
	private $post_search_box_class = 'turquoise';
	// Size of the user avatar in the navigation bar
	private $nav_bar_avatar_size = 52;

	// use new block layout in rankings
	protected $ranking_block_layout = true;

	/**
	 * Adding aditional meta for responsive design
	 *
	 * @since Snow 1.4
	 */
	public function head_metas()
	{
		$this->output('<meta name="viewport" content="width=device-width, initial-scale=1"/>');
		parent::head_metas();
	}

	/**
	 * Adding theme stylesheets
	 *
	 * @since Snow 1.4
	 */
	public function head_css()
	{
		// add RTL CSS file
		if ($this->isRTL)
			$this->content['css_src'][] = $this->rooturl . 'as-styles-rtl.css?' . AS_VERSION;

		if ($this->localfonts) {
			// add Ubuntu font locally (inlined for speed)
			$this->output_array(array(
				"<style>",
				"@font-face {",
				" font-family: 'Ubuntu'; font-weight: normal; font-style: normal;",
				" src: local('Ubuntu'),",
				"  url('{$this->rooturl}fonts/ubuntu-regular.woff2') format('woff2'), url('{$this->rooturl}fonts/ubuntu-regular.woff') format('woff');",
				"}",
				"@font-face {",
				" font-family: 'Ubuntu'; font-weight: bold; font-style: normal;",
				" src: local('Ubuntu Bold'), local('Ubuntu-Bold'),",
				"  url('{$this->rooturl}fonts/ubuntu-bold.woff2') format('woff2'), url('{$this->rooturl}fonts/ubuntu-bold.woff') format('woff');",
				"}",
				"@font-face {",
				" font-family: 'Ubuntu'; font-weight: normal; font-style: italic;",
				" src: local('Ubuntu Italic'), local('Ubuntu-Italic'),",
				"  url('{$this->rooturl}fonts/ubuntu-italic.woff2') format('woff2'), url('{$this->rooturl}fonts/ubuntu-italic.woff') format('woff');",
				"}",
				"@font-face {",
				" font-family: 'Ubuntu'; font-weight: bold; font-style: italic;",
				" src: local('Ubuntu Bold Italic'), local('Ubuntu-BoldItalic'),",
				"  url('{$this->rooturl}fonts/ubuntu-bold-italic.woff2') format('woff2'), url('{$this->rooturl}fonts/ubuntu-bold-italic.woff') format('woff');",
				"}",
				"</style>",
			));
		}
		else {
			// add Ubuntu font CSS file from Google Fonts
			$this->content['css_src'][] = 'https://fonts.googleapis.com/css?family=Ubuntu:400,400i,700,700i';
		}

		parent::head_css();

		// output some dynamic CSS inline
		$this->head_inline_css();
	}

	/**
	 * Adding theme javascripts
	 *
	 * @since Snow 1.4
	 */
	public function head_script()
	{
		$jsUrl = $this->rooturl . $this->js_dir . '/snow-core.js?' . AS_VERSION;
		$this->content['script'][] = '<script src="' . $jsUrl . '"></script>';

		parent::head_script();
	}

	/**
	 * Adding point count for logged in user
	 *
	 * @since Snow 1.4
	 */
	public function logged_in()
	{
		parent::logged_in();
		if (as_is_logged_in()) {
			$userpoints = as_get_logged_in_points();
			$pointshtml = $userpoints == 1
				? as_lang_html_sub('main/1_point', '1', '1')
				: as_html(as_format_number($userpoints))
			;
			$this->output('<div class="qam-logged-in-points">' . $pointshtml . '</div>');
		}
	}

	/**
	 * Adding body class dynamically. Override needed to add class on admin/approve-users page
	 *
	 * @since Snow 1.4
	 */
	public function body_tags()
	{
		$class = 'as-template-' . as_html($this->template);
		$class .= empty($this->theme) ? '' : ' as-theme-' . as_html($this->theme);

		if (isset($this->content['categoryids'])) {
			foreach ($this->content['categoryids'] as $categoryid) {
				$class .= ' as-category-' . as_html($categoryid);
			}
		}

		// add class if admin/approve-users page
		if ($this->template === 'admin' && as_request_part(1) === 'approve')
			$class .= ' qam-approve-users';

		if ($this->fixed_topbar)
			$class .= ' qam-body-fixed';

		$this->output('class="' . $class . ' as-body-js-off"');
	}

	/**
	 * Login form for user dropdown menu.
	 *
	 * @since Snow 1.4
	 */
	public function nav_user_search()
	{
		// outputs signin form if user not logged in
		$this->output('<div class="qam-account-items-wrapper">');

		$this->qam_user_account();

		$this->output('<div class="qam-account-items clearfix">');

		if (!as_is_logged_in()) {
			if (isset($this->content['navigation']['user']['signin']) && !AS_FINAL_EXTERNAL_USERS) {
				$signin = $this->content['navigation']['user']['signin'];
				$this->output(
					'<form action="' . $signin['url'] . '" method="post">',
						'<input type="text" name="emailhandle" dir="auto" placeholder="' . trim(as_lang_html(as_opt('allow_signin_email_only') ? 'users/email_label' : 'users/email_handle_label'), ':') . '"/>',
						'<input type="password" name="password" dir="auto" placeholder="' . trim(as_lang_html('users/password_label'), ':') . '"/>',
						'<div><input type="checkbox" name="remember" id="qam-rememberme" value="1"/>',
						'<label for="qam-rememberme">' . as_lang_html('users/remember') . '</label></div>',
						'<input type="hidden" name="code" value="' . as_html(as_get_form_security_code('signin')) . '"/>',
						'<input type="submit" value="' . $signin['label'] . '" class="as-form-tall-button as-form-tall-button-signin" name="dosignin"/>',
					'</form>'
				);

				// remove regular navigation link to log in page
				unset($this->content['navigation']['user']['signin']);
			}
		}

		$this->nav('user');
		$this->output('</div> <!-- END qam-account-items -->');
		$this->output('</div> <!-- END qam-account-items-wrapper -->');
	}

	/**
	 * Modify markup for topbar.
	 *
	 * @since Snow 1.4
	 */
	public function nav_main_sub()
	{
		$this->output('<div class="qam-main-nav-wrapper clearfix">');
		$this->output('<div class="sb-toggle-left qam-menu-toggle"><i class="icon-th-list"></i></div>');
		$this->nav_user_search();
		$this->logo();
		$this->nav('main');
		$this->output('</div> <!-- END qam-main-nav-wrapper -->');
		$this->nav('sub');
	}

	/**
	 * Remove the '-' from the note for the category page (notes).
	 *
	 * @since Snow 1.4
	 * @param array $navlink
	 * @param string $class
	 */
	public function nav_link($navlink, $class)
	{
		if (isset($navlink['note']) && !empty($navlink['note'])) {
			$search = array(' - <', '> - ');
			$replace = array(' <', '> ');
			$navlink['note'] = str_replace($search, $replace, $navlink['note']);
		}
		parent::nav_link($navlink, $class);
	}

	/**
	 * Rearranges the layout:
	 * - Swaps the <tt>main()</tt> and <tt>sidepanel()</tt> functions
	 * - Moves the header and footer functions outside as-body-wrapper
	 * - Keeps top/high and low/bottom widgets separated
	 *
	 * @since Snow 1.4
	 */
	public function body_content()
	{
		$this->body_prefix();
		$this->notices();

		$this->widgets('full', 'top');
		$this->header();

		$extratags = isset($this->content['wrapper_tags']) ? $this->content['wrapper_tags'] : '';
		$this->output('<div class="as-body-wrapper"' . $extratags . '>', '');
		$this->widgets('full', 'high');

		$this->output('<div class="as-main-wrapper">', '');
		$this->main();
		$this->sidepanel();
		$this->output('</div> <!-- END main-wrapper -->');

		$this->widgets('full', 'low');
		$this->output('</div> <!-- END body-wrapper -->');

		$this->footer();

		$this->body_suffix();
	}

	/**
	 * Header in full width top bar
	 *
	 * @since Snow 1.4
	 */
	public function header()
	{
		$class = $this->fixed_topbar ? ' fixed' : '';

		$this->output('<div id="qam-topbar" class="clearfix' . $class . '">');

		$this->nav_main_sub();
		$this->output('</div> <!-- END qam-topbar -->');

		$this->output($this->post_button());
		$this->qam_search('the-top', 'the-top-search');
	}

	/**
	 * Footer in full width bottom bar
	 *
	 * @since Snow 1.4
	 */
	public function footer()
	{
		$this->output('<div class="qam-footer-box">');

		$this->output('<div class="qam-footer-row">');
		$this->widgets('full', 'bottom');
		$this->output('</div> <!-- END qam-footer-row -->');

		parent::footer();
		$this->output('</div> <!-- END qam-footer-box -->');
	}

	/**
	 * Overridden to customize layout and styling
	 *
	 * @since Snow 1.4
	 */
	public function sidepanel()
	{
		// remove sidebar for user profile pages
		if ($this->template == 'user')
			return;

		$this->output('<div id="qam-sidepanel-toggle"><i class="icon-left-open-big"></i></div>');
		$this->output('<div class="as-sidepanel" id="qam-sidepanel-mobile">');
		$this->qam_search();
		$this->widgets('side', 'top');
		$this->sidebar();
		$this->widgets('side', 'high');
		$this->widgets('side', 'low');
		if (isset($this->content['sidepanel']))
			$this->output_raw($this->content['sidepanel']);
		$this->feed();
		$this->widgets('side', 'bottom');
		$this->output('</div> <!-- as-sidepanel -->', '');
	}

	/**
	 * Allow alternate sidebar color.
	 *
	 * @since Snow 1.4
	 */
	public function sidebar()
	{
		if (isset($this->content['sidebar'])) {
			$sidebar = $this->content['sidebar'];
			if (!empty($sidebar)) {
				$this->output('<div class="as-sidebar ' . $this->welcome_widget_class . '">');
				$this->output_raw($sidebar);
				$this->output('</div> <!-- as-sidebar -->', '');
			}
		}
	}

	/**
	 * Add close icon
	 *
	 * @since Snow 1.4
	 * @param array $q_item
	 */
	public function q_item_title($q_item)
	{
		$closedText = as_lang('main/closed');
		$imgHtml = empty($q_item['closed'])
			? ''
			: '<img src="' . $this->rooturl . $this->icon_url . '/closed-q-list.png" class="qam-q-list-close-icon" alt="' . $closedText . '" title="' . $closedText . '"/>';

		$this->output(
			'<div class="as-q-item-title">',
			// add closed note in title
			$imgHtml,
			'<a href="' . $q_item['url'] . '">' . $q_item['title'] . '</a>',
			'</div>'
		);
	}

	/**
	 * Add RSS feeds icon
	 */
	public function favorite()
	{
		parent::favorite();

		// RSS feed link in title
		if (isset($this->content['feed']['url'])) {
			$feed = $this->content['feed'];
			$label = isset($feed['label']) ? $feed['label'] : '';
			$this->output('<a href="' . $feed['url'] . '" title="' . $label . '"><i class="icon-rss qam-title-rss"></i></a>');
		}
	}

	/**
	 * Add closed icon for closed songs
	 *
	 * @since Snow 1.4
	 */
	public function title()
	{
		$q_view = isset($this->content['q_view']) ? $this->content['q_view'] : null;

		// link title where appropriate
		$url = isset($q_view['url']) ? $q_view['url'] : false;

		// add closed image
		$closedText = as_lang('main/closed');
		$imgHtml = empty($q_view['closed'])
			? ''
			: '<img src="' . $this->rooturl . $this->icon_url . '/closed-q-view.png" class="qam-q-view-close-icon" alt="' . $closedText . '" width="24" height="24" title="' . $closedText . '"/>';

		if (isset($this->content['title'])) {
			$this->output(
				$imgHtml,
				$url ? '<a href="' . $url . '">' : '',
				$this->content['title'],
				$url ? '</a>' : ''
			);
		}
	}

	/**
	 * Add view counter to song list
	 *
	 * @since Snow 1.4
	 * @param array $q_item
	 */
	public function q_item_stats($q_item)
	{
		$this->output('<div class="as-q-item-stats">');

		$this->thumbing($q_item);
		$this->a_count($q_item);
		parent::view_count($q_item);

		$this->output('</div>');
	}

	/**
	 * Prevent display view counter on usual place
	 *
	 * @since Snow 1.4
	 * @param array $q_item
	 */
	public function view_count($q_item)
	{
		// do nothing
	}

	/**
	 * Add view counter to song view
	 *
	 * @since Snow 1.4
	 * @param array $q_view
	 */
	public function q_view_stats($q_view)
	{
		$this->output('<div class="as-q-view-stats">');

		$this->thumbing($q_view);
		$this->a_count($q_view);
		parent::view_count($q_view);

		$this->output('</div>');
	}

	/**
	 * Modify user whometa, move to top
	 *
	 * @since Snow 1.4
	 * @param array $q_view
	 */
	public function q_view_main($q_view)
	{
		$this->output('<div class="as-q-view-main">');

		if (isset($q_view['main_form_tags'])) {
			$this->output('<form ' . $q_view['main_form_tags'] . '>'); // form for buttons on song
		}

		$this->post_avatar_meta($q_view, 'as-q-view');
		$this->q_view_content($q_view);
		$this->q_view_extra($q_view);
		$this->q_view_follows($q_view);
		$this->q_view_closed($q_view);
		$this->post_tags($q_view, 'as-q-view');

		$this->q_view_buttons($q_view);

		if (isset($q_view['main_form_tags'])) {
			if (isset($q_view['buttons_form_hidden']))
				$this->form_hidden_elements($q_view['buttons_form_hidden']);
			$this->output('</form>');
		}

		$this->c_list(isset($q_view['c_list']) ? $q_view['c_list'] : null, 'as-q-view');
		$this->c_form(isset($q_view['c_form']) ? $q_view['c_form'] : null);

		$this->output('</div> <!-- END as-q-view-main -->');
	}

	/**
	 * Hide thumbs when zero
	 * @param  array $post
	 */
	public function thumb_count($post)
	{
		if ($post['raw']['basetype'] === 'C' && $post['raw']['netthumbs'] == 0) {
			$post['netthumbs_view']['data'] = '';
		}

		parent::thumb_count($post);
	}

	/**
	 * Move user whometa to top in review
	 *
	 * @since Snow 1.4
	 * @param array $a_item
	 */
	public function a_item_main($a_item)
	{
		$this->output('<div class="as-a-item-main">');

		if (isset($a_item['main_form_tags'])) {
			$this->output('<form ' . $a_item['main_form_tags'] . '>'); // form for buttons on review
		}

		$this->post_avatar_meta($a_item, 'as-a-item');

		if ($a_item['hidden'])
			$this->output('<div class="as-a-item-hidden">');
		elseif ($a_item['selected'])
			$this->output('<div class="as-a-item-selected">');

		$this->a_selection($a_item);
		if (isset($a_item['error']))
			$this->error($a_item['error']);
		$this->a_item_content($a_item);

		if ($a_item['hidden'] || $a_item['selected'])
			$this->output('</div>');

		$this->a_item_buttons($a_item);

		if (isset($a_item['main_form_tags'])) {
			if (isset($a_item['buttons_form_hidden']))
				$this->form_hidden_elements($a_item['buttons_form_hidden']);
			$this->output('</form>');
		}

		$this->c_list(isset($a_item['c_list']) ? $a_item['c_list'] : null, 'as-a-item');
		$this->c_form(isset($a_item['c_form']) ? $a_item['c_form'] : null);

		$this->output('</div> <!-- END as-a-item-main -->');
	}

	/**
	 * Remove comment thumbing here
	 * @param array $c_item
	 */
	public function c_list_item($c_item)
	{
		$extraclass = @$c_item['classes'] . (@$c_item['hidden'] ? ' as-c-item-hidden' : '');

		$this->output('<div class="as-c-list-item ' . $extraclass . '" ' . @$c_item['tags'] . '>');

		$this->c_item_main($c_item);
		$this->c_item_clear();

		$this->output('</div> <!-- END as-c-item -->');
	}

	/**
	 * Move user whometa to top in comment, add comment thumbing back in
	 *
	 * @since Snow 1.4
	 * @param array $c_item
	 */
	public function c_item_main($c_item)
	{
		$this->post_avatar_meta($c_item, 'as-c-item');

		if (isset($c_item['error']))
			$this->error($c_item['error']);

		if (isset($c_item['main_form_tags'])) {
			$this->output('<form ' . $c_item['main_form_tags'] . '>'); // form for comment thumbing buttons
		}

		$this->thumbing($c_item);

		if (isset($c_item['main_form_tags'])) {
			$this->form_hidden_elements(@$c_item['thumbing_form_hidden']);
			$this->output('</form>');
		}

		if (isset($c_item['main_form_tags'])) {
			$this->output('<form ' . $c_item['main_form_tags'] . '>'); // form for buttons on comment
		}

		if (isset($c_item['expand_tags']))
			$this->c_item_expand($c_item);
		elseif (isset($c_item['url']))
			$this->c_item_link($c_item);
		else
			$this->c_item_content($c_item);

		$this->output('<div class="as-c-item-footer">');
		$this->c_item_buttons($c_item);
		$this->output('</div>');

		if (isset($c_item['main_form_tags'])) {
			$this->form_hidden_elements(@$c_item['buttons_form_hidden']);
			$this->output('</form>');
		}
	}

	/**
	 * APS Market attribution.
	 * I'd really appreciate you displaying this link on your APS site. Thank you - Jatin
	 *
	 * @since Snow 1.4
	 */
	public function attribution()
	{
		// floated right
		$this->output(
			'<div class="as-attribution">',
			'Snow Theme by <a href="http://www.apsmarket.com">APS Market</a>',
			'</div>'
		);
		parent::attribution();
	}

	/**
	 * User account navigation item. This will return based on signin information.
	 * If user is logged in, it will populate user avatar and account links.
	 * If user is guest, it will populate signin form and registration link.
	 *
	 * @since Snow 1.4
	 */
	private function qam_user_account()
	{
		if (as_is_logged_in()) {
			// get logged-in user avatar
			$handle = as_get_logged_in_user_field('handle');
			$toggleClass = 'qam-logged-in';

			if (AS_FINAL_EXTERNAL_USERS)
				$tobar_avatar = as_get_external_avatar_html(as_get_logged_in_user_field('userid'), $this->nav_bar_avatar_size, true);
			else {
				$tobar_avatar = as_get_user_avatar_html(
					as_get_logged_in_user_field('flags'),
					as_get_logged_in_user_field('email'),
					$handle,
					as_get_logged_in_user_field('avatarblobid'),
					as_get_logged_in_user_field('avatarwidth'),
					as_get_logged_in_user_field('avatarheight'),
					$this->nav_bar_avatar_size,
					false
				);
			}

			$avatar = strip_tags($tobar_avatar, '<img>');
			if (!empty($avatar))
				$handle = '';
		}
		else {
			// display signin icon and label
			$handle = $this->content['navigation']['user']['signin']['label'];
			$toggleClass = 'qam-logged-out';
			$avatar = '<i class="icon-key qam-auth-key"></i>';
		}

		// finally output avatar with div tag
		$handleBlock = empty($handle) ? '' : '<div class="qam-account-handle">' . as_html($handle) . '</div>';
		$this->output(
			'<div id="qam-account-toggle" class="' . $toggleClass . '">',
			$avatar,
			$handleBlock,
			'</div>'
		);
	}

	/**
	 * Add search-box wrapper with extra class for color scheme
	 *
	 * @since Snow 1.4
	 * @version 1.0
	 * @param string $addon_class
	 * @param string $ids
	 */
	private function qam_search($addon_class = null, $ids = null)
	{
		$id = isset($ids) ? ' id="' . $ids . '"' : '';

		$this->output('<div class="qam-search ' . $this->post_search_box_class . ' ' . $addon_class . '"' . $id . '>');
		$this->search();
		$this->output('</div>');
	}


	/**
	 * Dynamic <code>CSS</code> based on options and other interaction with APS.
	 *
	 * @since Snow 1.4
	 * @version 1.0
	 * @return string The CSS code
	 */
	private function head_inline_css()
	{
		$css = array('<style>');

		if (!as_is_logged_in())
			$css[] = '.as-nav-user { margin: 0 !important; }';

		if (as_request_part(1) !== as_get_logged_in_handle()) {
			$css[] = '@media (max-width: 979px) {';
			$css[] = ' body.as-template-user.fixed, body[class*="as-template-user-"].fixed { padding-top: 118px !important; }';
			$css[] = ' body.as-template-users.fixed { padding-top: 95px !important; }';
			$css[] = '}';
			$css[] = '@media (min-width: 980px) {';
			$css[] = ' body.as-template-users.fixed { padding-top: 105px !important;}';
			$css[] = '}';
		}

		$css[] = '</style>';

		$this->output_array($css);
	}

	/**
	 * Custom post button for medium and small screen
	 *
	 * @access private
	 * @since Snow 1.4
	 * @version 1.0
	 * @return string Post button html markup
	 */
	private function post_button()
	{
		return
			'<div class="qam-post-search-box">' .
			'<div class="qam-post-mobile">' .
			'<a href="' . as_path('post', null, as_path_to_root()) . '" class="' . $this->post_search_box_class . '">' .
			as_lang_html('main/nav_post') .
			'</a>' .
			'</div>' .
			'<div class="qam-search-mobile ' . $this->post_search_box_class . '" id="qam-search-mobile">' .
			'</div>' .
			'</div>';
	}

	/**
	 * Adds placeholder "Search..." for search box
	 *
	 * @since Snow 1.4
	 */
	public function search_field($search)
	{
		$this->output('<input type="text" ' .'placeholder="' . $search['button_label'] . '..." ' . $search['field_tags'] . ' value="' . @$search['value'] . '" class="as-search-field"/>');
	}
}
