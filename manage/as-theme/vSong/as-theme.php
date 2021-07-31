<?php

	$theme_dir = dirname( __FILE__ ) . '/';
	$theme_url = as_opt('site_url') . 'as-theme/' . as_get_site_theme() . '/';

	//var_dump($theme_dir);

	class as_html_theme extends as_html_theme_base
	{

		function head_metas()
		{
			as_html_theme_base::head_metas();
			$this->output('<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">');
		}
		
		function head_script()
		{
			as_html_theme_base::head_script();
			
			$this->output('
				<script type="text/javascript">
				$(document).ready(function(){
					$(".menu_show_hide").click(function(){
					$(".as-nav-main").slideToggle();
					});

				$(window).resize(function() {
					if ($(window).width()>720) {$(".as-nav-main").show();}
				});
				}
				);
				</script>');

		}

		function head_css()
		{
			if (as_opt('qat_compression')==2) //gzip
				$this->output('<link rel="stylesheet" type="text/css" href="'.$this->rooturl.'as-styles-gzip.php'.'"/>');
			elseif (as_opt('qat_compression')==1) //css compression
				$this->output('<link rel="stylesheet" type="text/css" href="'.$this->rooturl.'as-styles-commpressed.css'.'"/>');
			else // normal css load
				$this->output('<link rel="stylesheet" type="text/css" href="'.$this->rooturl.$this->css_name().'"/>');
			
			if (isset($this->content['css_src']))
				foreach ($this->content['css_src'] as $css_src)
					$this->output('<link rel="stylesheet" type="text/css" href="'.$css_src.'"/>');
					
			if (!empty($this->content['notices']))
				$this->output(
					'<style><!--',
					'.as-body-js-on .as-notice {display:none;}',
					'//--></style>'
				);
		}		
		
		function body_content()
		{
			$this->body_prefix();
			$this->notices();
			
			$this->output('<div class="as-top-header">', '');
			$this->nav('user');
			$this->output('</div>', '');

			$this->header();
			$this->output('<div class="as-body-wrapper">', '');

			$this->widgets('full', 'top');
			
			$this->output('<div class="as-sub-nav">');
			$this->nav_user_search();
			$this->nav('sub');
			$this->output('</div>');
			
			$this->widgets('full', 'high');
			$this->sidepanel();
			$this->main();
			$this->widgets('full', 'low');
			$this->output('</div> <!-- end body-wrapper -->');
			
			$this->footer();
			$this->widgets('full', 'bottom');

			$this->body_suffix();
		}
		
		function header()
		{
			$this->output('<div class="as-header">');
			
			$this->logo();
			$this->nav_main_sub();
			$this->header_clear();
			
			$this->output('</div> <!-- end as-header -->', '');
		}
		
		function nav_user_search()
		{
			$this->search();
		}
		
		function nav_main_sub()
		{
			$this->nav('main');
		}

		function nav($navtype, $level=null)
		{
			$navigation=@$this->content['navigation'][$navtype];
			if ($navtype=='main'){
				$this->output('<nav id="mobilenav"><a href="#" class="menu_show_hide">menu</a></nav>');
			}
						
			if (($navtype=='user') || isset($navigation)) {
				$this->output('<div class="as-nav-'.$navtype.'">');
				
				if ($navtype=='user')
					$this->logged_in();
					
				// reverse order of 'opposite' items since they float right
				foreach (array_reverse($navigation, true) as $key => $navlink)
					if (@$navlink['opposite']) {
						unset($navigation[$key]);
						$navigation[$key]=$navlink;
					}
				
				$this->set_context('nav_type', $navtype);
				$this->nav_list($navigation, 'nav-'.$navtype, $level);
				$this->nav_clear($navtype);
				
				$this->clear_context('nav_type');
	
				$this->output('</div>');
			}
		}

		function nav_item($key, $navlink, $class, $level=null)
		{
			$this->output('<li class="as-'.$class.'-item'.(@$navlink['opposite'] ? '-opp' : '').
				(@$navlink['selected'] ? (' as-'.$class.'-item-selected') : '').
				(@$navlink['state'] ? (' as-'.$class.'-'.$navlink['state']) : '').' as-'.$class.'-'.$key.'">');
			$this->nav_link($navlink, $class);
			
			if (array_key_exists('subnav', $navlink) && count(@$navlink['subnav']))
				$this->nav_list($navlink['subnav'], $class, 1+$level);
			
			if ($class=='nav-cat'){
				
					$neaturls=as_opt('neat_urls');
					$url=as_opt('site_url');
					$mainkey=$key;
					if ($key=='all')$key='.rss';else $key='/'.$key.'.rss';
					
					switch ($neaturls) {
						case as_url_format_index:
								$url.='index.php/feed/questions'.$key;
							break;
							
						case as_url_format_neat:
							$url.='feed/questions'.$key;
							break;
							
						case as_url_format_param:
							$url.='?qa=feed/questions'.$key;
							break;
							
						default:
							$url.='index.php?qa=feed&as_1=questions&as_2='.$mainkey.'.rss';
						
						case as_url_format_params:
							$url.='?qa=feed&as_1=questions&as_2='.$mainkey.'.rss';
							break;
					}
					$this->output('<a href="'.$url.'" class="as-cat-feed-link"><div class="as-feed-cat"></div></a>');
				}
			
			$this->output('</li>');
		}
		
		function view_count($post)
		{
			// do nothing
		}
		function theme_view_count($post)
		{
			as_html_theme_base::view_count($post);
		}
		
		function post_meta_flags($post, $class)
		{ 
			$this->theme_view_count($post);
			as_html_theme_base::post_meta_flags($post, $class);
		}
		function attribution()
		{
			// please don't remove these links
			$this->output('');
			//as_html_theme_base::attribution();
		}
		function footer()
		{
			$this->output('<div class="as-wrap-footer">');
			
			as_html_theme_base::footer();
			
			$this->output('</div> <!-- end as-footer -->', '');
		}
		
	}

/*
	Omit PHP closing tag to help avoid accidental output
*/