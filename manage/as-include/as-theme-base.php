<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	Description: Default theme class, broken into lots of little functions for easy overriding


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

if (!defined('AS_VERSION')) { // don't allow this page to be requested directly from browser
	header('Location: ../');
	exit;
}


/*
	How do I make a theme which goes beyond CSS to actually modify the HTML output?

	Create a file named as-theme.php in your new theme directory which defines a class as_html_theme
	that extends this base class as_html_theme_base. You can then override any of the methods below,
	referring back to the default method using double colon (as_html_theme_base::) notation.

	Plugins can also do something similar by using a layer. For more information and to see some example
	code, please consult the online APS documentation.
*/

class as_html_theme_base
{
	public $template;
	public $content;
	public $rooturl;
	public $request;
	public $isRTL; // (boolean) whether text direction is Right-To-Left

	protected $minifyHtml; // (boolean) whether to indent the HTML
	protected $indent = 0;
	protected $lines = 0;
	protected $context = array();

	// whether to use new block layout in rankings (true) or fall back to tables (false)
	protected $ranking_block_layout = false;
	// theme 'slug' to use as CSS class
	protected $theme;


	/**
	 * Initialize the object and assign local variables.
	 * @param $template
	 * @param $content
	 * @param $rooturl
	 * @param $request
	 */
	public function __construct($template, $content, $rooturl, $request)
	{
		$this->template = $template;
		$this->content = $content;
		$this->rooturl = $rooturl;
		$this->request = $request;
		$this->isRTL = isset($content['direction']) && $content['direction'] === 'rtl';
		$this->minifyHtml = !empty($content['options']['minify_html']);
	}

	/**
	 * @deprecated PHP4-style constructor deprecated from 1.7; please use proper `__construct`
	 * function instead.
	 * @param $template
	 * @param $content
	 * @param $rooturl
	 * @param $request
	 */
	public function as_html_theme_base($template, $content, $rooturl, $request)
	{
		self::__construct($template, $content, $rooturl, $request);
	}


	/**
	 * Output each element in $elements on a separate line, with automatic HTML indenting.
	 * This should be passed markup which uses the <tag/> form for unpaired tags, to help keep
	 * track of indenting, although its actual output converts these to <tag> for W3C validation.
	 * @param $elements
	 */
	public function output_array($elements)
	{
		foreach ($elements as $element) {
			$line = str_replace('/>', '>', $element);
			$delta = substr_count($element, '<') - substr_count($element, '<!') - 2 * substr_count($element, '</') - substr_count($element, '/>');

			if ($delta < 0) {
				$this->indent += $delta;
			}

			echo str_repeat("\t", max(0, $this->indent)) . $line . "\n";

			if ($delta > 0) {
				$this->indent += $delta;
			}
			
			$this->lines++;
		}
	}


	/**
	 * Output each passed parameter on a separate line - see output_array() comments.
	 */
	public function output() // other parameters picked up via func_get_args()
	{
		$args = func_get_args();
		$this->output_array($args);
	}


	/**
	 * Output $html at the current indent level, but don't change indent level based on the markup within.
	 * Useful for user-entered HTML which is unlikely to follow the rules we need to track indenting.
	 * @param $html
	 */
	public function output_raw($html)
	{
		if (strlen($html))
			echo str_repeat("\t", max(0, $this->indent)) . $html . "\n";
	}


	/**
	 * Output the three elements ['prefix'], ['data'] and ['suffix'] of $parts (if they're defined),
	 * with appropriate CSS classes based on $class, using $outertag and $innertag in the markup.
	 * @param $parts
	 * @param $class
	 * @param string $outertag
	 * @param string $innertag
	 * @param string $extraclass
	 */
	public function output_split($parts, $class, $outertag = 'span', $innertag = 'span', $extraclass = null)
	{
		if (empty($parts) && strtolower($outertag) != 'td')
			return;

		$this->output(
			'<' . $outertag . ' class="' . $class . (isset($extraclass) ? (' ' . $extraclass) : '') . '">',
			(strlen(@$parts['prefix']) ? ('<' . $innertag . ' class="' . $class . '-pad">' . $parts['prefix'] . '</' . $innertag . '>') : '') .
			(strlen(@$parts['data']) ? ('<' . $innertag . ' class="' . $class . '-data">' . $parts['data'] . '</' . $innertag . '>') : '') .
			(strlen(@$parts['suffix']) ? ('<' . $innertag . ' class="' . $class . '-pad">' . $parts['suffix'] . '</' . $innertag . '>') : ''),
			'</' . $outertag . '>'
		);
	}


	/**
	 * Set some context, which be accessed via $this->context for a function to know where it's being used on the page.
	 * @param $key
	 * @param $value
	 */
	public function set_context($key, $value)
	{
		$this->context[$key] = $value;
	}


	/**
	 * Clear some context (used at the end of the appropriate loop).
	 * @param $key
	 */
	public function clear_context($key)
	{
		unset($this->context[$key]);
	}


	/**
	 * Reorder the parts of the page according to the $parts array which contains part keys in their new order. Call this
	 * before main_parts(). See the docs for as_array_reorder() in util/sort.php for the other parameters.
	 * @param $parts
	 * @param string $beforekey
	 * @param bool $reorderrelative
	 */
	public function reorder_parts($parts, $beforekey = null, $reorderrelative = true)
	{
		require_once AS_INCLUDE_DIR . 'util/sort.php';

		as_array_reorder($this->content, $parts, $beforekey, $reorderrelative);
	}


	/**
	 * Output the widgets (as provided in $this->content['widgets']) for $region and $place.
	 * @param $region
	 * @param $place
	 */
	public function widgets($region, $place)
	{
		$widgetsHere = isset($this->content['widgets'][$region][$place]) ? $this->content['widgets'][$region][$place] : array();
		if (is_array($widgetsHere) && count($widgetsHere) > 0) {
			$this->output('<div class="as-widgets-' . $region . ' as-widgets-' . $region . '-' . $place . '">');

			foreach ($widgetsHere as $module) {
				$this->output('<div class="as-widget-' . $region . ' as-widget-' . $region . '-' . $place . '">');
				$module->output_widget($region, $place, $this, $this->template, $this->request, $this->content);
				$this->output('</div>');
			}

			$this->output('</div>', '');
		}
	}

	/**
	 * Pre-output initialization. Immediately called after loading of the module. Content and template variables are
	 * already setup at this point. Useful to perform layer initialization in the earliest and safest stage possible.
	 */
	public function initialize()
	{
		// abstract method
	}

	/**
	 * Post-output cleanup. For now, check that the indenting ended right, and if not, output a warning in an HTML comment.
	 */
	public function finish()
	{
		if ($this->indent !== 0 && !$this->minifyHtml) {
			echo "<!--\nIt's no big deal, but your HTML could not be indented properly. To fix, please:\n" .
				"1. Use this->output() to output all HTML.\n" .
				"2. Balance all paired tags like <td>...</td> or <div>...</div>.\n" .
				"3. Use a slash at the end of unpaired tags like <img/> or <input/>.\n" .
				"Thanks!\n-->\n";
		}
	}


	// From here on, we have a large number of class methods which output particular pieces of HTML markup
	// The calling chain is initiated from as-page.php, or ajax/*.php for refreshing parts of a page,
	// For most HTML elements, the name of the function is similar to the element's CSS class, for example:
	// search() outputs <div class="as-search">, s_list() outputs <div class="as-q-list">, etc...

	public function doctype()
	{
		$this->output('<!DOCTYPE html>');
	}

	public function html()
	{
		$attribution = '<!-- Powered by vSongBook - http://github.com/vsongbook -->';
		$extratags = isset($this->content['html_tags']) ? $this->content['html_tags'] : '';

		$this->output(
			'<html ' . $extratags . '>'
		);

		$this->head();

		if (as_is_logged_in()) $this->body();
		else $this->guest();
		$this->output(
			'</html>'
		);
	}

	public function head()
	{
		$this->output(
			'<head>',
			'<meta charset="' . $this->content['charset'] . '"/>',
			'<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">'
		);

		$this->head_title();
		$this->head_metas();
		$this->head_css();
		$this->head_links();
		$this->head_lines();
		$this->head_script();
		$this->head_custom();

		$this->output('</head>');
	}

	public function head_title()
	{
		$pagetitle = strlen($this->request) ? strip_tags(@$this->content['title']) : '';
		$headtitle = (strlen($pagetitle) ? "$pagetitle - " : '') . $this->content['site_title'];

		$this->output('<title>' . $headtitle . '</title>');
	}

	public function head_metas()
	{
		if (strlen(@$this->content['description'])) {
			$this->output('<meta name="description" content="' . $this->content['description'] . '"/>');
		}

		if (strlen(@$this->content['keywords'])) {
			// as far as I know, meta keywords have zero effect on search rankings or listings
			$this->output('<meta name="keywords" content="' . $this->content['keywords'] . '"/>');
		}
	}

	public function head_links()
	{
		if (isset($this->content['canonical'])) {
			$this->output('<link rel="canonical" href="' . $this->content['canonical'] . '"/>');
		}

		if (isset($this->content['feed']['url'])) {
			$this->output('<link rel="alternate" type="application/rss+xml" href="' . $this->content['feed']['url'] . '" title="' . @$this->content['feed']['label'] . '"/>');
		}

		// convert page links to rel=prev and rel=next tags
		if (isset($this->content['page_links']['items'])) {
			foreach ($this->content['page_links']['items'] as $page_link) {
				if (in_array($page_link['type'], array('prev', 'next')))
					$this->output('<link rel="' . $page_link['type'] . '" href="' . $page_link['url'] . '" />');
			}
		}
	}

	public function head_script()
	{
		if (isset($this->content['script'])) {
			foreach ($this->content['script'] as $scriptline) {
				$this->output_raw($scriptline);
			}
		}
	}

	public function head_css()
	{
		$this->output('<link rel="stylesheet" href="' . $this->rooturl . $this->css_name() . '"/>');

		if (isset($this->content['css_src'])) {
			foreach ($this->content['css_src'] as $css_src) {
				$this->output('<link rel="stylesheet" href="' . $css_src . '"/>');
			}
		}

		if (!empty($this->content['notices'])) {
			$this->output(
				'<style>',
				'.as-body-js-on .as-notice {display:none;}',
				'</style>'
			);
		}
	}

	public function css_name()
	{
		return 'as-styles.css?' . AS_VERSION;
	}

	public function head_lines()
	{
		if (isset($this->content['head_lines'])) {
			foreach ($this->content['head_lines'] as $line) {
				$this->output_raw($line);
			}
		}
	}

	public function head_custom()
	{
		// abstract method
	}

	public function body()
	{
		$this->output('<body');
		$this->body_tags();
		$this->output('>');

		$this->body_script();
		$this->body_header();
		
		$this->body_content();

		$this->body_footer();
		$this->body_hidden();

		$this->output('</body>');
	}

	public function guest()
	{
		$this->output('<body>');
		$this->output('<div class="as-guest">');
		$this->output('<div class="as-guest-wrapper">');
		$this->output('<div class="as-guest-logo">'.$this->content['logo'].'</div>');
		$this->page_title_error();
		$this->main_parts($this->content);
		$this->output('</div>');
		$this->output('</div>');
		$this->output('</body>');
	}

	public function body_hidden()
	{
		$this->output('<div style="position:absolute;overflow:hidden;clip:rect(0 0 0 0);height:0;width:0;margin:0;padding:0;border:0;">');
		$this->waiting_template();
		$this->output('</div>');
	}

	public function waiting_template()
	{
		$this->output('<span id="as-waiting-template" class="as-waiting">...</span>');
	}

	public function body_script()
	{
		$this->output(
			'<script>',
			"var b = document.getElementsByTagName('body')[0];",
			"b.className = b.className.replace('as-body-js-off', 'as-body-js-on');",
			'</script>'
		);
	}

	public function body_header()
	{
		if (isset($this->content['body_header'])) {
			$this->output_raw($this->content['body_header']);
		}
	}

	public function body_footer()
	{
		if (isset($this->content['body_footer'])) {
			$this->output_raw($this->content['body_footer']);
		}
	}

	public function body_content()
	{
		$this->body_prefix();
		$this->notices();

		$extratags = isset($this->content['wrapper_tags']) ? $this->content['wrapper_tags'] : '';
		$this->output('<div class="as-body-wrapper"' . $extratags . '>', '');

		$this->widgets('full', 'top');
		$this->header();
		$this->widgets('full', 'high');
		$this->sidepanel();
		$this->main();
		$this->widgets('full', 'low');
		$this->footer();
		$this->widgets('full', 'bottom');

		$this->output('</div> <!-- END body-wrapper -->');

		$this->body_suffix();
	}

	public function body_tags()
	{
		$class = 'as-template-' . as_html($this->template);
		$class .= empty($this->theme) ? '' : ' as-theme-' . as_html($this->theme);

		if (isset($this->content['categoryids'])) {
			foreach ($this->content['categoryids'] as $categoryid) {
				$class .= ' as-category-' . as_html($categoryid);
			}
		}

		$this->output('class="' . $class . ' as-body-js-off"');
	}

	public function body_prefix()
	{
		// abstract method
	}

	public function body_suffix()
	{
		// abstract method
	}

	public function notices()
	{
		if (!empty($this->content['notices'])) {
			foreach ($this->content['notices'] as $notice) {
				$this->notice($notice);
			}
		}
	}

	public function notice($notice)
	{
		$this->output('<div class="as-notice" id="' . $notice['id'] . '">');

		if (isset($notice['form_tags']))
			$this->output('<form ' . $notice['form_tags'] . '>');

		$this->output_raw($notice['content']);

		$this->output('<input ' . $notice['close_tags'] . ' type="submit" value="X" class="as-notice-close-button"/> ');

		if (isset($notice['form_tags'])) {
			$this->form_hidden_elements(@$notice['form_hidden']);
			$this->output('</form>');
		}

		$this->output('</div>');
	}

	public function header()
	{
		$this->output('<div class="as-header">');

		$this->logo();
		$this->nav_user_search();
		$this->nav_main_sub();
		$this->header_clear();

		$this->output('</div> <!-- END as-header -->', '');
	}

	public function nav_user_search()
	{
		$this->nav('user');
		$this->search();
	}

	public function nav_main_sub()
	{
		$this->nav('main');
		$this->nav('sub');
	}

	public function logo()
	{
		$this->output(
			'<div class="as-logo">',
			$this->content['logo'],
			'</div>'
		);
	}

	public function search()
	{
		$search = $this->content['search'];

		$this->output(
			'<div class="as-search">',
			'<form ' . $search['form_tags'] . '>',
			@$search['form_extra']
		);

		$this->search_field($search);
		$this->search_button($search);

		$this->output(
			'</form>',
			'</div>'
		);
	}

	public function search_field($search)
	{
		$this->output('<input type="text" ' . $search['field_tags'] . ' value="' . @$search['value'] . '" class="as-search-field"/>');
	}

	public function search_button($search)
	{
		$this->output('<input type="submit" value="' . $search['button_label'] . '" class="as-search-button"/>');
	}

	public function nav($navtype, $level = null)
	{
		$navigation = @$this->content['navigation'][$navtype];

		if ($navtype == 'user' || isset($navigation)) {
			$this->output('<div class="as-nav-' . $navtype . '">');

			if ($navtype == 'user')
				$this->logged_in();

			// reverse order of 'opposite' items since they float right
			foreach (array_reverse($navigation, true) as $key => $navlink) {
				if (@$navlink['opposite']) {
					unset($navigation[$key]);
					$navigation[$key] = $navlink;
				}
			}

			$this->set_context('nav_type', $navtype);
			$this->nav_list($navigation, 'nav-' . $navtype, $level);
			$this->nav_clear($navtype);
			$this->clear_context('nav_type');

			$this->output('</div>');
		}
	}

	public function nav_list($navigation, $class, $level = null)
	{
		$this->output('<ul class="as-' . $class . '-list' . (isset($level) ? (' as-' . $class . '-list-' . $level) : '') . '">');

		$index = 0;

		foreach ($navigation as $key => $navlink) {
			$this->set_context('nav_key', $key);
			$this->set_context('nav_index', $index++);
			$this->nav_item($key, $navlink, $class, $level);
		}

		$this->clear_context('nav_key');
		$this->clear_context('nav_index');

		$this->output('</ul>');
	}

	public function nav_clear($navtype)
	{
		$this->output(
			'<div class="as-nav-' . $navtype . '-clear">',
			'</div>'
		);
	}

	public function nav_item($key, $navlink, $class, $level = null)
	{
		$suffix = strtr($key, array( // map special character in navigation key
			'$' => '',
			'/' => '-',
		));

		$this->output('<li class="as-' . $class . '-item' . (@$navlink['opposite'] ? '-opp' : '') .
			(@$navlink['state'] ? (' as-' . $class . '-' . $navlink['state']) : '') . ' as-' . $class . '-' . $suffix . '">');
		$this->nav_link($navlink, $class);

		$subnav = isset($navlink['subnav']) ? $navlink['subnav'] : array();
		if (is_array($subnav) && count($subnav) > 0) {
			$this->nav_list($subnav, $class, 1 + $level);
		}

		$this->output('</li>');
	}

	public function nav_link($navlink, $class)
	{
		if (isset($navlink['url'])) {
			$this->output(
				'<a href="' . $navlink['url'] . '" class="as-' . $class . '-link' .
				(@$navlink['selected'] ? (' as-' . $class . '-selected') : '') .
				(@$navlink['favorited'] ? (' as-' . $class . '-favorited') : '') .
				'"' . (strlen(@$navlink['popup']) ? (' title="' . $navlink['popup'] . '"') : '') .
				(isset($navlink['target']) ? (' target="' . $navlink['target'] . '"') : '') . '>' . $navlink['label'] .
				'</a>'
			);
		} else {
			$this->output(
				'<span class="as-' . $class . '-nolink' . (@$navlink['selected'] ? (' as-' . $class . '-selected') : '') .
				(@$navlink['favorited'] ? (' as-' . $class . '-favorited') : '') . '"' .
				(strlen(@$navlink['popup']) ? (' title="' . $navlink['popup'] . '"') : '') .
				'>' . $navlink['label'] . '</span>'
			);
		}

		if (strlen(@$navlink['note']))
			$this->output('<span class="as-' . $class . '-note">' . $navlink['note'] . '</span>');
	}

	public function logged_in()
	{
		$this->output_split(@$this->content['signedin'], 'as-logged-in', 'div');
	}

	public function header_clear()
	{
		$this->output(
			'<div class="as-header-clear">',
			'</div>'
		);
	}

	public function cover_page( $name, $handle, $avatar, $isowner, $points )
	{
		$this->output('<style>.as-main-heading{display:none;}</style>');
		$this->output('<div class="user-top clearfix">');
		//if ( $isowner ) $this->output('<a id="upload-cover" class="btn btn-default">Change cover</a>');
		$this->output('<div class="user-bar"><div class="avatar pull-left">' . $avatar . '</div></div>');
		$this->output('<div class="user-bar-holder">
					<div class="user-stat pull-right">
						<ul>
							<li class="points">' . $points . '</li>
							<li class="followers">0 <span>Followers</span></li>
						</ul>
					</div>');
		$this->output('<div class="user-nag"><div class="user-buttons pull-right"></div>
						<span class="full-name">'.$name.' ('.$handle. ')</span>');
	}
	
	public function profile_page($user)
	{
		$this->output('<style>.as-main-heading{display:none;}</style>');
		$this->output('<div class="user-top clearfix"><br>');
		$this->output('<div class="user-bar" style="float: left;margin: -30px 30px 0px 0px;">' . $user['user_avatar'] . '</div>');
		$this->output('<div style="">');
		$this->output('<p><span style="font-size:25px;"><b>' . $user['fullname'] . ' (@'. $user['handle'].')</b></span>');
		$this->output( '<br>' . $user['since_when'] . '</p>');
		$this->output('<p><table>');
		$this->output('<tr><td><b>Mobile </b></td><td>: +' . $user['mobile'] .' - ' . $user['country'] .'</td></tr>');
		$this->output('<tr><td><b>Email </b></td><td>: ' . $user['email'] .'</td></tr>');
		$this->output('</table></p>');
		$this->output('<p><table>');
		$this->output('<tr><td><b>Church </b></td><td>: ' . $user['churchname'] .'</td></tr>');
		$this->output('<tr><td><b>City </b></td><td>: ' . $user['cityname'] .'</td></tr>');
		$this->output('</table></p>');
		$this->output('</div>');
	}

	public function action_buttons($actionbtns)
	{
		$actionbtntags = isset($actionbtns['action_tags']) ? $actionbtns['action_tags'] : '';
		$this->output('<span ' . $actionbtntags . '>');
		foreach ($actionbtns['buttons'] as $button => $btn) {
			$this->output('<input type="submit" value="'.$btn['label'].'" '.$btn['tags'].' class="as-form-profile-btn as-form-profile-' . $button . '"/> ');
		}
		$this->output('</span>');
	}

	public function sidepanel()
	{
		$this->output('<div class="as-sidepanel">');
		$this->widgets('side', 'top');
		$this->sidebar();
		$this->widgets('side', 'high');
		$this->widgets('side', 'low');
		$this->output_raw(@$this->content['sidepanel']);
		$this->feed();
		$this->widgets('side', 'bottom');
		$this->output('</div>', '');
	}

	public function sidebar()
	{
		$sidebar = @$this->content['sidebar'];

		if (!empty($sidebar)) {
			$this->output('<div class="as-sidebar">');
			$this->output_raw($sidebar);
			$this->output('</div>', '');
		}
	}

	public function feed()
	{
		$feed = @$this->content['feed'];

		if (!empty($feed)) {
			$this->output('<div class="as-feed">');
			$this->output('<a href="' . $feed['url'] . '" class="as-feed-link">' . @$feed['label'] . '</a>');
			$this->output('</div>');
		}
	}

	public function main()
	{
		$content = $this->content;
		$hidden = !empty($content['hidden']) ? ' as-main-hidden' : '';

		$this->output('<div class="as-main' . $hidden . '">');

		$this->widgets('main', 'top');

		$this->page_title_error();

		$this->widgets('main', 'high');

		$this->main_parts($content);

		$this->widgets('main', 'low');

		$this->page_links();
		$this->suggest_next();

		$this->widgets('main', 'bottom');

		$this->output('</div> <!-- END as-main -->', '');
	}

	public function page_title_error()
	{
		if (isset($this->content['title'])) {
			$favorite = isset($this->content['favorite']) ? $this->content['favorite'] : null;

			if (isset($favorite))
				$this->output('<form ' . $favorite['form_tags'] . '>');

			$this->output('<div class="as-main-heading">');
			$this->favorite();
			$this->output('<h1>');
			$this->title();
			$this->output('</h1>');
			$this->output('</div>');

			if (isset($favorite)) {
				$formhidden = isset($favorite['form_hidden']) ? $favorite['form_hidden'] : null;
				$this->form_hidden_elements($formhidden);
				$this->output('</form>');
			}
		}

		if (isset($this->content['success']))
			$this->success($this->content['success']);
		if (isset($this->content['error']))
			$this->error($this->content['error']);
	}

	public function favorite()
	{
		$favorite = isset($this->content['favorite']) ? $this->content['favorite'] : null;
		if (isset($favorite)) {
			$favoritetags = isset($favorite['favorite_tags']) ? $favorite['favorite_tags'] : '';
			$this->output('<span class="as-favoriting" ' . $favoritetags . '>');
			$this->favorite_inner_html($favorite);
			$this->output('</span>');
		}
	}

	public function title()
	{
		$q_view = @$this->content['q_view'];

		// link title where appropriate
		$url = isset($q_view['url']) ? $q_view['url'] : false;

		if (isset($this->content['title'])) {
			$this->output(
				$url ? '<a href="' . $url . '">' : '',
				$this->content['title'],
				$url ? '</a>' : ''
			);
		}

		// add closed note in title
		if (!empty($q_view['closed']['state']))
			$this->output(' [' . $q_view['closed']['state'] . ']');
	}

	public function favorite_inner_html($favorite)
	{
		$this->favorite_button(@$favorite['favorite_add_tags'], 'as-favorite');
		$this->favorite_button(@$favorite['favorite_remove_tags'], 'as-unfavorite');
	}

	public function favorite_button($tags, $class)
	{
		if (isset($tags))
			$this->output('<input ' . $tags . ' type="submit" value="" class="' . $class . '-button"/> ');
	}

	public function error($error)
	{
		if (strlen($error)) {
			$this->output(
				'<div class="as-error">',
				$error,
				'</div>'
			);
		}
	}

	public function success($message)
	{
		if (strlen($message)) {
			$this->output(
				'<div class="as-success">',
				$message,
				'</div>'
			);
		}
	}

	public function main_parts($content)
	{
		foreach ($content as $key => $part) {
			$this->set_context('part', $key);
			$this->main_part($key, $part);
		}

		$this->clear_context('part');
	}

	public function main_part($key, $part)
	{
		$isRanking = strpos($key, 'ranking') === 0;

		$partdiv = (
			strpos($key, 'custom') === 0 ||
			strpos($key, 'form') === 0 ||
			strpos($key, 's_list') === 0 ||
			(strpos($key, 'q_view') === 0 && !isset($this->content['form_q_edit'])) ||
			strpos($key, 'a_form') === 0 ||
			strpos($key, 'a_list') === 0 ||
			$isRanking ||
			strpos($key, 'message_list') === 0 ||
			strpos($key, 'nav_list') === 0
		);

		if ($partdiv) {
			$class = 'as-part-' . strtr($key, '_', '-');
			if ($isRanking)
				$class .= ' as-ranking-' . $part['type'] . '-' . (isset($part['sort']) ? $part['sort'] : 'points');
			// help target CSS to page parts
			$this->output('<div class="' . $class . '">');
		}

		if (strpos($key, 'custom') === 0)
			$this->output_raw($part);

		elseif (strpos($key, 'form') === 0)
			$this->form($part);

		elseif (strpos($key, 's_list') === 0)
			$this->s_list_and_form($part);

		elseif (strpos($key, 'q_view') === 0)
			$this->q_view($part);

		elseif (strpos($key, 'a_form') === 0)
			$this->a_form($part);

		elseif (strpos($key, 'a_list') === 0)
			$this->a_list($part);

		elseif (strpos($key, 'ranking') === 0)
			$this->ranking($part);

		elseif (strpos($key, 'message_list') === 0)
			$this->message_list_and_form($part);

		elseif (strpos($key, 'profile_page') === 0)
			$this->profile_page($part);

		elseif (strpos($key, 'nav_list') === 0) {
			$this->part_title($part);
			$this->nav_list($part['nav'], $part['type'], 1);
		}

		if ($partdiv)
			$this->output('</div>');
	}

	public function footer()
	{
		$this->output('<div class="as-footer">');

		$this->nav('footer');
		$this->attribution();
		$this->footer_clear();

		$this->output('</div> <!-- END as-footer -->', '');
	}

	public function attribution()
	{
		// Hi there. I'd really appreciate you displaying this link on your APS site. Thank you - Gideon

		$this->output(
			'<div class="as-attribution">',
			'Powered by <a href="http://github.com/vsongbook">vSongBook</a>',
			'</div>'
		);
	}

	public function footer_clear()
	{
		$this->output(
			'<div class="as-footer-clear">',
			'</div>'
		);
	}

	public function section($title)
	{
		$this->part_title(array('title' => $title));
	}

	public function part_title($part)
	{
		if (strlen(@$part['title']) || strlen(@$part['title_tags']))
			$this->output('<h2' . rtrim(' ' . @$part['title_tags']) . '>' . @$part['title'] . '</h2>');
	}

	public function part_footer($part)
	{
		if (isset($part['footer']))
			$this->output($part['footer']);
	}

	public function form($form)
	{
		if (!empty($form)) {
			$this->part_title($form);

			if (isset($form['tags']))
				$this->output('<form ' . $form['tags'] . '>');

			$this->form_body($form);

			if (isset($form['tags']))
				$this->output('</form>');
		}
	}

	public function form_columns($form)
	{
		if (isset($form['ok']) || !empty($form['fields']))
			$columns = ($form['style'] == 'wide') ? 2 : 1;
		else
			$columns = 0;

		return $columns;
	}

	public function form_spacer($form, $columns)
	{
		$this->output(
			'<tr>',
			'<td colspan="' . $columns . '" class="as-form-' . $form['style'] . '-spacer">',
			'&nbsp;',
			'</td>',
			'</tr>'
		);
	}

	public function form_body($form)
	{
		if (@$form['boxed'])
			$this->output('<div class="as-form-table-boxed">');

		$columns = $this->form_columns($form);

		if ($columns)
			$this->output('<table class="as-form-' . $form['style'] . '-table">');

		$this->form_ok($form, $columns);
		$this->form_fields($form, $columns);
		$this->form_buttons($form, $columns);
		$this->form_links($form, $columns);

		if ($columns)
			$this->output('</table>');

		$this->form_hidden($form);

		if (@$form['boxed'])
			$this->output('</div>');
	}

	public function form_ok($form, $columns)
	{
		if (!empty($form['ok'])) {
			$this->output(
				'<tr>',
				'<td colspan="' . $columns . '" class="as-form-' . $form['style'] . '-ok">',
				$form['ok'],
				'</td>',
				'</tr>'
			);
		}
	}

	/**
	 * Reorder the fields of $form according to the $keys array which contains the field keys in their new order. Call
	 * before any fields are output. See the docs for as_array_reorder() in util/sort.php for the other parameters.
	 * @param $form
	 * @param $keys
	 * @param mixed $beforekey
	 * @param bool $reorderrelative
	 */
	public function form_reorder_fields(&$form, $keys, $beforekey = null, $reorderrelative = true)
	{
		require_once AS_INCLUDE_DIR . 'util/sort.php';

		if (is_array($form['fields']))
			as_array_reorder($form['fields'], $keys, $beforekey, $reorderrelative);
	}

	public function form_fields($form, $columns)
	{
		if (!empty($form['fields'])) {
			foreach ($form['fields'] as $key => $field) {
				$this->set_context('field_key', $key);

				if (@$field['type'] == 'blank')
					$this->form_spacer($form, $columns);
				else
					$this->form_field_rows($form, $columns, $field);
			}

			$this->clear_context('field_key');
		}
	}

	public function form_field_rows($form, $columns, $field)
	{
		$style = $form['style'];

		if (isset($field['style'])) { // field has different style to most of form
			$style = $field['style'];
			$colspan = $columns;
			$columns = ($style == 'wide') ? 2 : 1;
		} else
			$colspan = null;

		$prefixed = (@$field['type'] == 'checkbox') && ($columns == 1) && !empty($field['label']);
		$suffixed = (@$field['type'] == 'select' || @$field['type'] == 'number') && $columns == 1 && !empty($field['label']) && !@$field['loose'];
		$skipdata = @$field['tight'];
		$tworows = ($columns == 1) && (!empty($field['label'])) && (!$skipdata) &&
			((!($prefixed || $suffixed)) || (!empty($field['error'])) || (!empty($field['note'])));

		if (isset($field['id'])) {
			if ($columns == 1)
				$this->output('<tbody id="' . $field['id'] . '">', '<tr>');
			else
				$this->output('<tr id="' . $field['id'] . '">');
		} else
			$this->output('<tr>');

		if ($columns > 1 || !empty($field['label']))
			$this->form_label($field, $style, $columns, $prefixed, $suffixed, $colspan);

		if ($tworows) {
			$this->output(
				'</tr>',
				'<tr>'
			);
		}

		if (!$skipdata)
			$this->form_data($field, $style, $columns, !($prefixed || $suffixed), $colspan);

		$this->output('</tr>');

		if ($columns == 1 && isset($field['id']))
			$this->output('</tbody>');
	}

	public function form_label($field, $style, $columns, $prefixed, $suffixed, $colspan)
	{
		$extratags = '';

		if ($columns > 1 && (@$field['type'] == 'select-radio' || @$field['rows'] > 1))
			$extratags .= ' style="vertical-align:top;"';

		if (isset($colspan))
			$extratags .= ' colspan="' . $colspan . '"';

		$this->output('<td class="as-form-' . $style . '-label"' . $extratags . '>');

		if ($prefixed) {
			$this->output('<label>');
			$this->form_field($field, $style);
		}

		$this->output(@$field['label']);

		if ($prefixed)
			$this->output('</label>');

		if ($suffixed) {
			$this->output('&nbsp;');
			$this->form_field($field, $style);
		}

		$this->output('</td>');
	}

	public function form_data($field, $style, $columns, $showfield, $colspan)
	{
		if ($showfield || (!empty($field['error'])) || (!empty($field['note']))) {
			$this->output(
				'<td class="as-form-' . $style . '-data"' . (isset($colspan) ? (' colspan="' . $colspan . '"') : '') . '>'
			);

			if ($showfield)
				$this->form_field($field, $style);

			if (!empty($field['error'])) {
				if (@$field['note_force'])
					$this->form_note($field, $style, $columns);

				$this->form_error($field, $style, $columns);
			} elseif (!empty($field['note']))
				$this->form_note($field, $style, $columns);

			$this->output('</td>');
		}
	}

	public function form_field($field, $style)
	{
		$this->form_prefix($field, $style);

		$this->output_raw(@$field['html_prefix']);

		switch (@$field['type']) {
			case 'checkbox':
				$this->form_checkbox($field, $style);
				break;

			case 'static':
				$this->form_static($field, $style);
				break;

			case 'password':
				$this->form_password($field, $style);
				break;

			case 'number':
				$this->form_number($field, $style);
				break;

			case 'file':
				$this->form_file($field, $style);
				break;

			case 'select':
				$this->form_select($field, $style);
				break;

			case 'select-radio':
				$this->form_select_radio($field, $style);
				break;

			case 'radio':
				$this->form_radio($field, $style);
				break;
	
			case 'image':
				$this->form_image($field, $style);
				break;

			case 'custom':
				$this->output_raw(@$field['html']);
				break;

			default:
				if (@$field['type'] == 'textarea' || @$field['rows'] > 1)
					$this->form_text_multi_row($field, $style);
				else
					$this->form_text_single_row($field, $style);
				break;
		}

		$this->output_raw(@$field['html_suffix']);

		$this->form_suffix($field, $style);
	}

	/**
	 * Reorder the buttons of $form according to the $keys array which contains the button keys in their new order. Call
	 * before any buttons are output. See the docs for as_array_reorder() in util/sort.php for the other parameters.
	 * @param $form
	 * @param $keys
	 * @param mixed $beforekey
	 * @param bool $reorderrelative
	 */
	public function form_reorder_buttons(&$form, $keys, $beforekey = null, $reorderrelative = true)
	{
		require_once AS_INCLUDE_DIR . 'util/sort.php';

		if (is_array($form['buttons']))
			as_array_reorder($form['buttons'], $keys, $beforekey, $reorderrelative);
	}

	public function form_buttons($form, $columns)
	{
		if (!empty($form['buttons'])) {
			$style = @$form['style'];

			if ($columns) {
				$this->output(
					'<tr>',
					'<td colspan="' . $columns . '" class="as-form-' . $style . '-buttons">'
				);
			}

			foreach ($form['buttons'] as $key => $button) {
				$this->set_context('button_key', $key);

				if (empty($button))
					$this->form_button_spacer($style);
				else {
					$this->form_button_data($button, $key, $style);
					$this->form_button_note($button, $style);
				}
			}

			$this->clear_context('button_key');

			if ($columns) {
				$this->output(
					'</td>',
					'</tr>'
				);
			}
		}
	}

	public function form_button_data($button, $key, $style)
	{
		$baseclass = 'as-form-' . $style . '-button as-form-' . $style . '-button-' . $key;

		$this->output('<input' . rtrim(' ' . @$button['tags']) . ' value="' . @$button['label'] . '" title="' . @$button['popup'] . '" type="submit"' .
			(isset($style) ? (' class="' . $baseclass . '"') : '') . '/>');
	}

	public function form_button_note($button, $style)
	{
		if (!empty($button['note'])) {
			$this->output(
				'<span class="as-form-' . $style . '-note">',
				$button['note'],
				'</span>',
				'<br/>'
			);
		}
	}

	public function form_button_spacer($style)
	{
		$this->output('<span class="as-form-' . $style . '-buttons-spacer">&nbsp;</span>');
	}

	public function form_links($form, $columns)
	{
		if (!empty($form['links'])) {
			$style = @$form['style'];

			if ($columns) {
				$this->output(
					'<tr>',
					'<td colspan="' . $columns . '" class="as-form-' . $style . '-buttons">'
				);
			}

			foreach ($form['links'] as $key => $button) {
				$this->set_context('button_key', $key);

				if (empty($button))
					$this->form_button_spacer($style);
				else {
					$this->form_link_data($button, $key, $style);
				}
			}

			$this->clear_context('button_key');

			if ($columns) {
				$this->output(
					'</td>',
					'</tr>'
				);
			}
		}
	}

	public function form_link_data($link, $key, $style)
	{
		$baseclass = 'as-form-' . $style . '-button as-form-' . $style . '-button-' . $key;

		$this->output('<a' . rtrim(' ' . @$link['tags']) . ' href="' . @$link['url'] . '" ' .
			(isset($style) ? (' class="' . $baseclass . '"') : '') . '>'.@$link['label'].'</a>');
	}

	public function form_hidden($form)
	{
		$this->form_hidden_elements(@$form['hidden']);
	}

	public function form_hidden_elements($hidden)
	{
		if (empty($hidden))
			return;

		foreach ($hidden as $name => $value) {
			if (is_array($value)) {
				// new method of outputting tags
				$this->output('<input ' . @$value['tags'] . ' type="hidden" value="' . @$value['value'] . '"/>');
			} else {
				// old method
				$this->output('<input name="' . $name . '" type="hidden" value="' . $value . '"/>');
			}
		}
	}

	public function form_prefix($field, $style)
	{
		if (!empty($field['prefix']))
			$this->output('<span class="as-form-' . $style . '-prefix">' . $field['prefix'] . '</span>');
	}

	public function form_suffix($field, $style)
	{
		if (!empty($field['suffix']))
			$this->output('<span class="as-form-' . $style . '-suffix">' . $field['suffix'] . '</span>');
	}

	public function form_checkbox($field, $style)
	{
		$this->output('<input ' . @$field['tags'] . ' type="checkbox" value="1"' . (@$field['value'] ? ' checked' : '') . ' class="as-form-' . $style . '-checkbox"/>');
	}

	public function form_static($field, $style)
	{
		$this->output('<span class="as-form-' . $style . '-static">' . @$field['value'] . '</span>');
	}

	public function form_password($field, $style)
	{
		$this->output('<input ' . @$field['tags'] . ' type="password" value="' . @$field['value'] . '" class="as-form-' . $style . '-text"/>');
	}

	public function form_number($field, $style)
	{
		$this->output('<input ' . @$field['tags'] . ' type="text" value="' . @$field['value'] . '" class="as-form-' . $style . '-number"/>');
	}

	public function form_file($field, $style)
	{
		$this->output('<input ' . @$field['tags'] . ' type="file" class="as-form-' . $style . '-file"/>');
	}

	/**
	 * Output a <select> element. The $field array may contain the following keys:
	 *   options: (required) a key-value array containing all the options in the select.
	 *   tags: any attributes to be added to the select.
	 *   value: the selected value from the 'options' parameter.
	 *   match_by: whether to match the 'value' (default) or 'key' of each option to determine if it is to be selected.
	 * @param $field
	 * @param $style
	 */
	public function form_select($field, $style)
	{
		$this->output('<select ' . (isset($field['tags']) ? $field['tags'] : '') . ' class="as-form-' . $style . '-select">');

		// Only match by key if it is explicitly specified. Otherwise, for backwards compatibility, match by value
		$matchbykey = isset($field['match_by']) && $field['match_by'] === 'key';

		foreach ($field['options'] as $key => $value) {
			$selected = isset($field['value']) && (
				($matchbykey && $key === $field['value']) ||
				(!$matchbykey && $value === $field['value'])
			);
			$this->output('<option value="' . $key . '"' . ($selected ? ' selected' : '') . '>' . $value . '</option>');
		}

		$this->output('</select>');
	}

	public function form_select_radio($field, $style)
	{
		$radios = 0;

		foreach ($field['options'] as $tag => $value) {
			if ($radios++)
				$this->output('<br/>');

			$this->output('<input ' . @$field['tags'] . ' type="radio" value="' . $tag . '"' . (($value == @$field['value']) ? ' checked' : '') . ' class="as-form-' . $style . '-radio"/> ' . $value);
		}
	}

	public function form_radio($field, $style)
	{
		$radios = 0;

		foreach ($field['options'] as $tag => $value) {
			if ($radios++) $this->output("\t\t".' ');

			$this->output("\t\t".'&nbsp;&nbsp;<label> <input ' . @$field['tags'] . ' type="radio" value="' . $tag . '"' . (($value == @$field['value']) ? ' checked' : '') . ' /> ' . $value . '</label>');
		}
	}

	public function form_image($field, $style)
	{
		$this->output('<div class="as-form-' . $style . '-image">' . @$field['html'] . '</div>');
	}

	public function form_text_single_row($field, $style)
	{
		$this->output('<input ' . @$field['tags'] . ' type="text" value="' . @$field['value'] . '" class="as-form-' . $style . '-text"/>');
	}

	public function form_text_multi_row($field, $style)
	{
		$this->output('<textarea ' . @$field['tags'] . ' rows="' . (int)$field['rows'] . '" cols="40" class="as-form-' . $style . '-text">' . @$field['value'] . '</textarea>');
	}

	public function form_error($field, $style, $columns)
	{
		$tag = ($columns > 1) ? 'span' : 'div';

		$this->output('<' . $tag . ' class="as-form-' . $style . '-error">' . $field['error'] . '</' . $tag . '>');
	}

	public function form_note($field, $style, $columns)
	{
		$tag = ($columns > 1) ? 'span' : 'div';

		$this->output('<' . $tag . ' class="as-form-' . $style . '-note">' . @$field['note'] . '</' . $tag . '>');
	}

	public function ranking($ranking)
	{
		$this->part_title($ranking);

		if (!isset($ranking['type']))
			$ranking['type'] = 'items';
		$class = 'as-top-' . $ranking['type'];

		if (!$this->ranking_block_layout) {
			// old, less semantic table layout
			$this->ranking_table($ranking, $class);
		} else {
			// new block layout
			foreach ($ranking['items'] as $item) {
				$this->output('<span class="as-ranking-item ' . $class . '-item">');
				$this->ranking_item($item, $class);
				$this->output('</span>');
			}
		}

		$this->part_footer($ranking);
	}

	public function ranking_item($item, $class, $spacer = false) // $spacer is deprecated
	{
		if (!$this->ranking_block_layout) {
			// old table layout
			$this->ranking_table_item($item, $class, $spacer);
			return;
		}

		if (isset($item['count']))
			$this->ranking_count($item, $class);

		if (isset($item['avatar']))
			$this->avatar($item, $class);

		$this->ranking_label($item, $class);

		if (isset($item['score']))
			$this->ranking_score($item, $class);
	}

	public function ranking_cell($content, $class)
	{
		$tag = $this->ranking_block_layout ? 'span' : 'td';
		$this->output('<' . $tag . ' class="' . $class . '">' . $content . '</' . $tag . '>');
	}

	public function ranking_count($item, $class)
	{
		$this->ranking_cell($item['count'] . ' &#215;', $class . '-count');
	}

	public function ranking_label($item, $class)
	{
		$this->ranking_cell($item['label'], $class . '-label');
	}

	public function ranking_score($item, $class)
	{
		$this->ranking_cell($item['score'], $class . '-score');
	}

	/**
	 * @deprecated Table-based layout of users/tags is deprecated from 1.7 onwards and may be
	 * removed in a future version. Themes can switch to the new layout by setting the user
	 * variable $ranking_block_layout to false.
	 * @param $ranking
	 * @param $class
	 */
	public function ranking_table($ranking, $class)
	{
		$rows = min($ranking['rows'], count($ranking['items']));

		if ($rows > 0) {
			$this->output('<table class="' . $class . '-table">');
			$columns = ceil(count($ranking['items']) / $rows);

			for ($row = 0; $row < $rows; $row++) {
				$this->set_context('ranking_row', $row);
				$this->output('<tr>');

				for ($column = 0; $column < $columns; $column++) {
					$this->set_context('ranking_column', $column);
					$this->ranking_table_item(@$ranking['items'][$column * $rows + $row], $class, $column > 0);
				}

				$this->clear_context('ranking_column');
				$this->output('</tr>');
			}
			$this->clear_context('ranking_row');
			$this->output('</table>');
		}
	}

	/**
	 * @deprecated See ranking_table above.
	 * @param $item
	 * @param $class
	 * @param $spacer
	 */
	public function ranking_table_item($item, $class, $spacer)
	{
		if ($spacer)
			$this->ranking_spacer($class);

		if (empty($item)) {
			$this->ranking_spacer($class);
			$this->ranking_spacer($class);

		} else {
			if (isset($item['count']))
				$this->ranking_count($item, $class);

			if (isset($item['avatar']))
				$item['label'] = $item['avatar'] . ' ' . $item['label'];

			$this->ranking_label($item, $class);

			if (isset($item['score']))
				$this->ranking_score($item, $class);
		}
	}

	/**
	 * @deprecated See ranking_table above.
	 * @param $class
	 */
	public function ranking_spacer($class)
	{
		$this->output('<td class="' . $class . '-spacer">&nbsp;</td>');
	}


	public function message_list_and_form($list)
	{
		if (!empty($list)) {
			$this->part_title($list);

			$this->error(@$list['error']);

			if (!empty($list['form'])) {
				$this->output('<form ' . $list['form']['tags'] . '>');
				unset($list['form']['tags']); // we already output the tags before the messages
				$this->message_list_form($list);
			}

			$this->message_list($list);

			if (!empty($list['form'])) {
				$this->output('</form>');
			}
		}
	}

	public function message_list_form($list)
	{
		if (!empty($list['form'])) {
			$this->output('<div class="as-message-list-form">');
			$this->form($list['form']);
			$this->output('</div>');
		}
	}

	public function message_list($list)
	{
		if (isset($list['messages'])) {
			$this->output('<div class="as-message-list" ' . @$list['tags'] . '>');

			foreach ($list['messages'] as $message) {
				$this->message_item($message);
			}

			$this->output('</div> <!-- END as-message-list -->', '');
		}
	}

	public function message_item($message)
	{
		$this->output('<div class="as-message-item" ' . @$message['tags'] . '>');
		$this->message_content($message);
		$this->post_avatar_meta($message, 'as-message');
		$this->message_buttons($message);
		$this->output('</div> <!-- END as-message-item -->', '');
	}

	public function message_content($message)
	{
		if (!empty($message['content'])) {
			$this->output('<div class="as-message-content">');
			$this->output_raw($message['content']);
			$this->output('</div>');
		}
	}

	public function message_buttons($item)
	{
		if (!empty($item['form'])) {
			$this->output('<div class="as-message-buttons">');
			$this->form($item['form']);
			$this->output('</div>');
		}
	}

	public function list_thumb_disabled($items)
	{
		$disabled = false;

		if (count($items)) {
			$disabled = true;

			foreach ($items as $item) {
				if (@$item['thumb_on_page'] != 'disabled')
					$disabled = false;
			}
		}

		return $disabled;
	}

	public function s_list_and_form($s_list)
	{
		if (empty($s_list))
			return;

		$this->part_title($s_list);

		if (!empty($s_list['form']))
			$this->output('<form ' . $s_list['form']['tags'] . '>');

		$this->s_list($s_list);

		if (!empty($s_list['form'])) {
			unset($s_list['form']['tags']); // we already output the tags before the qs
			$this->s_list_form($s_list);
			$this->output('</form>');
		}

		$this->part_footer($s_list);
	}

	public function s_list_form($s_list)
	{
		if (!empty($s_list['form'])) {
			$this->output('<div class="as-q-list-form">');
			$this->form($s_list['form']);
			$this->output('</div>');
		}
	}

	public function s_list($s_list)
	{
		if (isset($s_list['qs'])) {
			$this->output('<div class="as-q-list' . ($this->list_thumb_disabled($s_list['qs']) ? ' as-q-list-thumb-disabled' : '') . '">', '');
			$this->s_list_items($s_list['qs']);
			$this->output('</div> <!-- END as-q-list -->', '');
		}
	}

	public function s_list_items($q_items)
	{
		foreach ($q_items as $q_item) {
			$this->s_list_item($q_item);
		}
	}

	public function s_list_item($q_item)
	{
		$this->output('<div class="as-q-list-item' . rtrim(' ' . @$q_item['classes']) . '" ' . @$q_item['tags'] . '>');

		//$this->q_item_stats($q_item);
		$this->q_item_main($q_item);
		$this->q_item_clear();

		$this->output('</div> <!-- END as-q-list-item -->', '');
	}

	public function q_item_stats($q_item)
	{
		$this->output('<div class="as-q-item-stats">');

		//$this->thumbing($q_item);
		$this->a_count($q_item);

		$this->output('</div>');
	}

	public function q_item_main($q_item)
	{
		$this->output('<div class="as-q-item-main">');

		$this->view_count($q_item);
		$this->q_item_title($q_item);
		$this->q_item_content($q_item);

		$this->post_avatar_meta($q_item, 'as-q-item');
		$this->post_tags($q_item, 'as-q-item');
		$this->q_item_buttons($q_item);

		$this->output('</div>');
	}

	public function q_item_clear()
	{
		$this->output(
			'<div class="as-q-item-clear">',
			'</div>'
		);
	}

	public function q_item_title($q_item)
	{
		$this->output(
			'<div class="as-q-item-title">',
			'<a href="' . $q_item['url'] . '">' . $q_item['title'] . '</a>',
			// add closed note in title
			empty($q_item['closed']['state']) ? '' : ' [' . $q_item['closed']['state'] . ']',
			'</div>'
		);
	}

	public function q_item_content($q_item)
	{
		if (!empty($q_item['content'])) {
			$this->output('<div class="as-q-item-content">');
			$this->output_raw($q_item['content']);
			$this->output('</div>');
		}
	}

	public function q_item_buttons($q_item)
	{
		if (!empty($q_item['form'])) {
			$this->output('<div class="as-q-item-buttons">');
			$this->form($q_item['form']);
			$this->output('</div>');
		}
	}

	public function thumbing($post)
	{
		if (isset($post['thumb_view'])) {
			$this->output('<div class="as-thumbing ' . (($post['thumb_view'] == 'updown') ? 'as-thumbing-updown' : 'as-thumbing-net') . '" ' . @$post['thumb_tags'] . '>');
			$this->thumbing_inner_html($post);
			$this->output('</div>');
		}
	}

	public function thumbing_inner_html($post)
	{
		$this->thumb_buttons($post);
		$this->thumb_count($post);
		$this->thumb_clear();
	}

	public function thumb_buttons($post)
	{
		$this->output('<div class="as-thumb-buttons ' . (($post['thumb_view'] == 'updown') ? 'as-thumb-buttons-updown' : 'as-thumb-buttons-net') . '">');

		switch (@$post['thumb_state']) {
			case 'thumbd_up':
				$this->post_hover_button($post, 'thumb_up_tags', '+', 'as-thumb-one-button as-thumbd-up');
				break;

			case 'thumbd_up_disabled':
				$this->post_disabled_button($post, 'thumb_up_tags', '+', 'as-thumb-one-button as-thumb-up');
				break;

			case 'thumbd_down':
				$this->post_hover_button($post, 'thumb_down_tags', '&ndash;', 'as-thumb-one-button as-thumbd-down');
				break;

			case 'thumbd_down_disabled':
				$this->post_disabled_button($post, 'thumb_down_tags', '&ndash;', 'as-thumb-one-button as-thumb-down');
				break;

			case 'up_only':
				$this->post_hover_button($post, 'thumb_up_tags', '+', 'as-thumb-first-button as-thumb-up');
				$this->post_disabled_button($post, 'thumb_down_tags', '', 'as-thumb-second-button as-thumb-down');
				break;

			case 'enabled':
				$this->post_hover_button($post, 'thumb_up_tags', '+', 'as-thumb-first-button as-thumb-up');
				$this->post_hover_button($post, 'thumb_down_tags', '&ndash;', 'as-thumb-second-button as-thumb-down');
				break;

			default:
				$this->post_disabled_button($post, 'thumb_up_tags', '', 'as-thumb-first-button as-thumb-up');
				$this->post_disabled_button($post, 'thumb_down_tags', '', 'as-thumb-second-button as-thumb-down');
				break;
		}

		$this->output('</div>');
	}

	public function thumb_count($post)
	{
		// You can also use $post['thumbsup_raw'], $post['thumbsdown_raw'], $post['netthumbs_raw'] to get
		// raw integer thumb counts, for graphing or showing in other non-textual ways

		$this->output('<div class="as-thumb-count ' . (($post['thumb_view'] == 'updown') ? 'as-thumb-count-updown' : 'as-thumb-count-net') . '"' . @$post['thumb_count_tags'] . '>');

		if ($post['thumb_view'] == 'updown') {
			$this->output_split($post['thumbsup_view'], 'as-upthumb-count');
			$this->output_split($post['thumbsdown_view'], 'as-downthumb-count');
		} else {
			$this->output_split($post['netthumbs_view'], 'as-netthumb-count');
		}

		$this->output('</div>');
	}

	public function thumb_clear()
	{
		$this->output(
			'<div class="as-thumb-clear">',
			'</div>'
		);
	}

	public function a_count($post)
	{
		// You can also use $post['reviews_raw'] to get a raw integer count of reviews

		$this->output_split(@$post['reviews'], 'as-a-count', 'span', 'span',
			@$post['review_selected'] ? 'as-a-count-selected' : (@$post['reviews_raw'] ? null : 'as-a-count-zero'));
	}

	public function view_count($post)
	{
		// You can also use $post['views_raw'] to get a raw integer count of views

		$this->output_split(@$post['views'], 'as-view-count');
	}

	public function avatar($item, $class, $prefix = null)
	{
		if (isset($item['avatar'])) {
			if (isset($prefix))
				$this->output($prefix);

			$this->output(
				'<span class="' . $class . '-avatar">',
				$item['avatar'],
				'</span>'
			);
		}
	}

	public function a_selection($post)
	{
		$this->output('<div class="as-a-selection">');

		if (isset($post['select_tags']))
			$this->post_hover_button($post, 'select_tags', '', 'as-a-select');
		elseif (isset($post['unselect_tags']))
			$this->post_hover_button($post, 'unselect_tags', '', 'as-a-unselect');
		elseif ($post['selected'])
			$this->output('<div class="as-a-selected">&nbsp;</div>');

		if (isset($post['select_text']))
			$this->output('<div class="as-a-selected-text">' . @$post['select_text'] . '</div>');

		$this->output('</div>');
	}

	public function post_hover_button($post, $element, $value, $class)
	{
		if (isset($post[$element]))
			$this->output('<input ' . $post[$element] . ' type="submit" value="' . $value . '" class="' . $class . '-button"/> ');
	}

	public function post_disabled_button($post, $element, $value, $class)
	{
		if (isset($post[$element]))
			$this->output('<input ' . $post[$element] . ' type="submit" value="' . $value . '" class="' . $class . '-disabled" disabled="disabled"/> ');
	}

	public function post_avatar_meta($post, $class, $avatarprefix = null, $metaprefix = null, $metaseparator = '<br/>')
	{
		$this->output('<span class="' . $class . '-avatar-meta">');
		$this->avatar($post, $class, $avatarprefix);
		$this->post_meta($post, $class, $metaprefix, $metaseparator);
		$this->output('</span>');
	}

	/**
	 * @deprecated Deprecated from 1.7; please use avatar() instead.
	 * @param $post
	 * @param $class
	 * @param string $prefix
	 */
	public function post_avatar($post, $class, $prefix = null)
	{
		$this->avatar($post, $class, $prefix);
	}

	public function post_meta($post, $class, $prefix = null, $separator = '<br/>')
	{
		$this->output('<span class="' . $class . '-meta">');

		if (isset($prefix))
			$this->output($prefix);

		$order = explode('^', @$post['meta_order']);

		foreach ($order as $element) {
			switch ($element) {
				case 'what':
					$this->post_meta_what($post, $class);
					break;

				case 'when':
					$this->post_meta_when($post, $class);
					break;

				case 'where':
					$this->post_meta_where($post, $class);
					break;

				case 'who':
					$this->post_meta_who($post, $class);
					break;
			}
		}

		$this->post_meta_flags($post, $class);

		if (!empty($post['what_2'])) {
			$this->output($separator);

			foreach ($order as $element) {
				switch ($element) {
					case 'what':
						$this->output('<span class="' . $class . '-what">' . $post['what_2'] . '</span>');
						break;

					case 'when':
						$this->output_split(@$post['when_2'], $class . '-when');
						break;

					case 'who':
						$this->output_split(@$post['who_2'], $class . '-who');
						break;
				}
			}
		}

		$this->output('</span>');
	}

	public function post_meta_what($post, $class)
	{
		if (isset($post['what'])) {
			$classes = $class . '-what';
			if (isset($post['what_your']) && $post['what_your']) {
				$classes .= ' ' . $class . '-what-your';
			}

			if (isset($post['what_url'])) {
				$tags = isset($post['what_url_tags']) ? $post['what_url_tags'] : '';
				$this->output('<a href="' . $post['what_url'] . '" class="' . $classes . '"' . $tags . '>' . $post['what'] . '</a>');
			} else {
				$this->output('<span class="' . $classes . '">' . $post['what'] . '</span>');
			}
		}
	}

	public function post_meta_when($post, $class)
	{
		$this->output_split(@$post['when'], $class . '-when');
	}

	public function post_meta_where($post, $class)
	{
		$this->output_split(@$post['where'], $class . '-where');
	}

	public function post_meta_who($post, $class)
	{
		if (isset($post['who'])) {
			$this->output('<span class="' . $class . '-who">');

			if (strlen(@$post['who']['prefix']))
				$this->output('<span class="' . $class . '-who-pad">' . $post['who']['prefix'] . '</span>');

			if (isset($post['who']['data']))
				$this->output('<span class="' . $class . '-who-data">' . $post['who']['data'] . '</span>');

			if (isset($post['who']['title']))
				$this->output('<span class="' . $class . '-who-title">' . $post['who']['title'] . '</span>');

			// You can also use $post['level'] to get the author's privilege level (as a string)

			if (isset($post['who']['points'])) {
				$post['who']['points']['prefix'] = '(' . $post['who']['points']['prefix'];
				$post['who']['points']['suffix'] .= ')';
				$this->output_split($post['who']['points'], $class . '-who-points');
			}

			if (strlen(@$post['who']['suffix']))
				$this->output('<span class="' . $class . '-who-pad">' . $post['who']['suffix'] . '</span>');

			$this->output('</span>');
		}
	}

	public function post_meta_flags($post, $class)
	{
		$this->output_split(@$post['flags'], $class . '-flags');
	}

	public function post_tags($post, $class)
	{
		if (!empty($post['q_tags'])) {
			$this->output('<div class="' . $class . '-tags">');
			$this->post_tag_list($post, $class);
			$this->output('</div>');
		}
	}

	public function post_tag_list($post, $class)
	{
		$this->output('<ul class="' . $class . '-tag-list">');

		foreach ($post['q_tags'] as $taghtml) {
			$this->post_tag_item($taghtml, $class);
		}

		$this->output('</ul>');
	}

	public function post_tag_item($taghtml, $class)
	{
		$this->output('<li class="' . $class . '-tag-item">' . $taghtml . '</li>');
	}

	public function page_links()
	{
		$page_links = @$this->content['page_links'];

		if (!empty($page_links)) {
			$this->output('<div class="as-page-links">');

			$this->page_links_label(@$page_links['label']);
			$this->page_links_list(@$page_links['items']);
			$this->page_links_clear();

			$this->output('</div>');
		}
	}

	public function page_links_label($label)
	{
		if (!empty($label))
			$this->output('<span class="as-page-links-label">' . $label . '</span>');
	}

	public function page_links_list($page_items)
	{
		if (!empty($page_items)) {
			$this->output('<ul class="as-page-links-list">');

			$index = 0;

			foreach ($page_items as $page_link) {
				$this->set_context('page_index', $index++);
				$this->page_links_item($page_link);

				if ($page_link['ellipsis'])
					$this->page_links_item(array('type' => 'ellipsis'));
			}

			$this->clear_context('page_index');

			$this->output('</ul>');
		}
	}

	public function page_links_item($page_link)
	{
		$this->output('<li class="as-page-links-item">');
		$this->page_link_content($page_link);
		$this->output('</li>');
	}

	public function page_link_content($page_link)
	{
		$label = @$page_link['label'];
		$url = @$page_link['url'];

		switch ($page_link['type']) {
			case 'this':
				$this->output('<span class="as-page-selected">' . $label . '</span>');
				break;

			case 'prev':
				$this->output('<a href="' . $url . '" class="as-page-prev">&laquo; ' . $label . '</a>');
				break;

			case 'next':
				$this->output('<a href="' . $url . '" class="as-page-next">' . $label . ' &raquo;</a>');
				break;

			case 'ellipsis':
				$this->output('<span class="as-page-ellipsis">...</span>');
				break;

			default:
				$this->output('<a href="' . $url . '" class="as-page-link">' . $label . '</a>');
				break;
		}
	}

	public function page_links_clear()
	{
		$this->output(
			'<div class="as-page-links-clear">',
			'</div>'
		);
	}

	public function suggest_next()
	{
		$suggest = @$this->content['suggest_next'];

		if (!empty($suggest)) {
			$this->output('<div class="as-suggest-next">');
			$this->output($suggest);
			$this->output('</div>');
		}
	}

	public function q_view($q_view)
	{
		if (!empty($q_view)) {
			$this->output('<div class="as-q-view' . (@$q_view['hidden'] ? ' as-q-view-hidden' : '') . rtrim(' ' . @$q_view['classes']) . '"' . rtrim(' ' . @$q_view['tags']) . '>');

			if (isset($q_view['main_form_tags'])) {
				$this->output('<form ' . $q_view['main_form_tags'] . '>'); // form for song thumbing buttons
			}

			//$this->q_view_stats($q_view);

			if (isset($q_view['main_form_tags'])) {
				$this->form_hidden_elements(@$q_view['thumbing_form_hidden']);
				$this->output('</form>');
			}

			$this->q_view_main($q_view);
			$this->q_view_clear();

			$this->output('</div> <!-- END as-q-view -->', '');
		}
	}

	public function q_view_stats($q_view)
	{
		$this->output('<div class="as-q-view-stats">');

		$this->thumbing($q_view);
		$this->a_count($q_view);

		$this->output('</div>');
	}

	public function q_view_main($q_view)
	{
		$this->output('<div class="as-q-view-main">');

		if (isset($q_view['main_form_tags'])) {
			$this->output('<form ' . $q_view['main_form_tags'] . '>'); // form for buttons on song
		}

		$this->view_count($q_view);
		$this->q_view_content($q_view);
		$this->q_view_extra($q_view);
		$this->q_view_follows($q_view);
		$this->q_view_closed($q_view);
		$this->post_tags($q_view, 'as-q-view');
		$this->post_avatar_meta($q_view, 'as-q-view');
		$this->q_view_buttons($q_view);

		if (isset($q_view['main_form_tags'])) {
			$this->form_hidden_elements(@$q_view['buttons_form_hidden']);
			$this->output('</form>');
		}

		$this->c_list(@$q_view['c_list'], 'as-q-view');
		$this->c_form(@$q_view['c_form']);

		$this->output('</div> <!-- END as-q-view-main -->');
	}

	public function q_view_content($q_view)
	{
		$content = isset($q_view['content']) ? $q_view['content'] : '';

		$this->output('<div class="as-q-view-content as-post-content">');
		$this->output_raw($content);
		$this->output('</div>');
	}

	public function q_view_follows($q_view)
	{
		if (!empty($q_view['follows']))
			$this->output(
				'<div class="as-q-view-follows">',
				$q_view['follows']['label'],
				'<a href="' . $q_view['follows']['url'] . '" class="as-q-view-follows-link">' . $q_view['follows']['title'] . '</a>',
				'</div>'
			);
	}

	public function q_view_closed($q_view)
	{
		if (!empty($q_view['closed'])) {
			$haslink = isset($q_view['closed']['url']);

			$this->output(
				'<div class="as-q-view-closed">',
				$q_view['closed']['label'],
				($haslink ? ('<a href="' . $q_view['closed']['url'] . '"') : '<span') . ' class="as-q-view-closed-content">',
				$q_view['closed']['content'],
				$haslink ? '</a>' : '</span>',
				'</div>'
			);
		}
	}

	public function q_view_extra($q_view)
	{
		if (!empty($q_view['extra'])) {
			$this->output(
				'<div class="as-q-view-extra">',
				$q_view['extra']['label'],
				'<span class="as-q-view-extra-content">',
				$q_view['extra']['content'],
				'</span>',
				'</div>'
			);
		}
	}

	public function q_view_buttons($q_view)
	{
		if (!empty($q_view['form'])) {
			$this->output('<div class="as-q-view-buttons">');
			$this->form($q_view['form']);
			$this->output('</div>');
		}
	}

	public function q_view_clear()
	{
		$this->output(
			'<div class="as-q-view-clear">',
			'</div>'
		);
	}

	public function a_form($a_form)
	{
		$this->output('<div class="as-a-form"' . (isset($a_form['id']) ? (' id="' . $a_form['id'] . '"') : '') .
			(@$a_form['collapse'] ? ' style="display:none;"' : '') . '>');

		$this->form($a_form);
		$this->c_list(@$a_form['c_list'], 'as-a-item');

		$this->output('</div> <!-- END as-a-form -->', '');
	}

	public function a_list($a_list)
	{
		if (!empty($a_list)) {
			$this->part_title($a_list);

			$this->output('<div class="as-a-list' . ($this->list_thumb_disabled($a_list['as']) ? ' as-a-list-thumb-disabled' : '') . '" ' . @$a_list['tags'] . '>', '');
			$this->a_list_items($a_list['as']);
			$this->output('</div> <!-- END as-a-list -->', '');
		}
	}

	public function a_list_items($a_items)
	{
		foreach ($a_items as $a_item) {
			$this->a_list_item($a_item);
		}
	}

	public function a_list_item($a_item)
	{
		$extraclass = @$a_item['classes'] . ($a_item['hidden'] ? ' as-a-list-item-hidden' : ($a_item['selected'] ? ' as-a-list-item-selected' : ''));

		$this->output('<div class="as-a-list-item ' . $extraclass . '" ' . @$a_item['tags'] . '>');

		if (isset($a_item['main_form_tags'])) {
			$this->output('<form ' . $a_item['main_form_tags'] . '>'); // form for review thumbing buttons
		}

		$this->thumbing($a_item);

		if (isset($a_item['main_form_tags'])) {
			$this->form_hidden_elements(@$a_item['thumbing_form_hidden']);
			$this->output('</form>');
		}

		$this->a_item_main($a_item);
		$this->a_item_clear();

		$this->output('</div> <!-- END as-a-list-item -->', '');
	}

	public function a_item_main($a_item)
	{
		$this->output('<div class="as-a-item-main">');

		if (isset($a_item['main_form_tags'])) {
			$this->output('<form ' . $a_item['main_form_tags'] . '>'); // form for buttons on review
		}

		if ($a_item['hidden'])
			$this->output('<div class="as-a-item-hidden">');
		elseif ($a_item['selected'])
			$this->output('<div class="as-a-item-selected">');

		$this->a_selection($a_item);
		$this->error(@$a_item['error']);
		$this->a_item_content($a_item);
		$this->post_avatar_meta($a_item, 'as-a-item');

		if ($a_item['hidden'] || $a_item['selected'])
			$this->output('</div>');

		$this->a_item_buttons($a_item);

		if (isset($a_item['main_form_tags'])) {
			$this->form_hidden_elements(@$a_item['buttons_form_hidden']);
			$this->output('</form>');
		}

		$this->c_list(@$a_item['c_list'], 'as-a-item');
		$this->c_form(@$a_item['c_form']);

		$this->output('</div> <!-- END as-a-item-main -->');
	}

	public function a_item_clear()
	{
		$this->output(
			'<div class="as-a-item-clear">',
			'</div>'
		);
	}

	public function a_item_content($a_item)
	{
		if (!isset($a_item['content'])) {
			$a_item['content'] = '';
		}

		$this->output('<div class="as-a-item-content as-post-content">');
		$this->output_raw($a_item['content']);
		$this->output('</div>');
	}

	public function a_item_buttons($a_item)
	{
		if (!empty($a_item['form'])) {
			$this->output('<div class="as-a-item-buttons">');
			$this->form($a_item['form']);
			$this->output('</div>');
		}
	}

	public function c_form($c_form)
	{
		$this->output('<div class="as-c-form"' . (isset($c_form['id']) ? (' id="' . $c_form['id'] . '"') : '') .
			(@$c_form['collapse'] ? ' style="display:none;"' : '') . '>');

		$this->form($c_form);

		$this->output('</div> <!-- END as-c-form -->', '');
	}

	public function c_list($c_list, $class)
	{
		if (!empty($c_list)) {
			$this->output('', '<div class="' . $class . '-c-list"' . (@$c_list['hidden'] ? ' style="display:none;"' : '') . ' ' . @$c_list['tags'] . '>');
			$this->c_list_items($c_list['cs']);
			$this->output('</div> <!-- END as-c-list -->', '');
		}
	}

	public function c_list_items($c_items)
	{
		foreach ($c_items as $c_item) {
			$this->c_list_item($c_item);
		}
	}

	public function c_list_item($c_item)
	{
		$extraclass = @$c_item['classes'] . (@$c_item['hidden'] ? ' as-c-item-hidden' : '');

		$this->output('<div class="as-c-list-item ' . $extraclass . '" ' . @$c_item['tags'] . '>');

		if (isset($c_item['thumb_view']) && isset($c_item['main_form_tags'])) {
			// form for comment thumbing buttons
			$this->output('<form ' . $c_item['main_form_tags'] . '>');
			$this->thumbing($c_item);
			$this->form_hidden_elements(@$c_item['thumbing_form_hidden']);
			$this->output('</form>');
		}

		$this->c_item_main($c_item);
		$this->c_item_clear();

		$this->output('</div> <!-- END as-c-item -->');
	}

	public function c_item_main($c_item)
	{
		if (isset($c_item['main_form_tags'])) {
			$this->output('<form ' . $c_item['main_form_tags'] . '>'); // form for buttons on comment
		}

		$this->error(@$c_item['error']);

		if (isset($c_item['expand_tags']))
			$this->c_item_expand($c_item);
		elseif (isset($c_item['url']))
			$this->c_item_link($c_item);
		else
			$this->c_item_content($c_item);

		$this->output('<div class="as-c-item-footer">');
		$this->post_avatar_meta($c_item, 'as-c-item');
		$this->c_item_buttons($c_item);
		$this->output('</div>');

		if (isset($c_item['main_form_tags'])) {
			$this->form_hidden_elements(@$c_item['buttons_form_hidden']);
			$this->output('</form>');
		}
	}

	public function c_item_link($c_item)
	{
		$this->output(
			'<a href="' . $c_item['url'] . '" class="as-c-item-link">' . $c_item['title'] . '</a>'
		);
	}

	public function c_item_expand($c_item)
	{
		$this->output(
			'<a href="' . $c_item['url'] . '" ' . $c_item['expand_tags'] . ' class="as-c-item-expand">' . $c_item['title'] . '</a>'
		);
	}

	public function c_item_content($c_item)
	{
		if (!isset($c_item['content'])) {
			$c_item['content'] = '';
		}

		$this->output('<div class="as-c-item-content as-post-content">');
		$this->output_raw($c_item['content']);
		$this->output('</div>');
	}

	public function c_item_buttons($c_item)
	{
		if (!empty($c_item['form'])) {
			$this->output('<div class="as-c-item-buttons">');
			$this->form($c_item['form']);
			$this->output('</div>');
		}
	}

	public function c_item_clear()
	{
		$this->output(
			'<div class="as-c-item-clear">',
			'</div>'
		);
	}


	/**
	 * Generic method to output a basic list of song links.
	 * @param array $s_list
	 * @param string $attrs
	 */
	public function q_title_list($s_list, $attrs = null)
	{
		$this->output('<ul class="as-q-title-list">');
		foreach ($s_list as $q) {
			$this->output(
				'<li class="as-q-title-item">',
				'<a href="' . as_q_path_html($q['postid'], $q['title']) . '" ' . $attrs . '>' . as_html($q['title']) . '</a>',
				'</li>'
			);
		}
		$this->output('</ul>');
	}

	/**
	 * Output block of similar songs when posting.
	 * @param array $s_list
	 * @param string $pretext
	 */
	public function q_post_similar($s_list, $pretext = '')
	{
		if (!count($s_list))
			return;

		$this->output('<div class="as-post-similar">');

		if (strlen($pretext) > 0)
			$this->output('<p class="as-post-similar-title">' . $pretext . '</p>');
		$this->q_title_list($s_list, 'target="_blank"');

		$this->output('</div>');
	}
}
