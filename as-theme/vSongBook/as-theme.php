<?php
/*
	vSongBook by AppSmata Solutions
	http://github.com/appsmata/

	File: as-theme/Candy/as-theme.php
	Description: Override base theme class for Candy theme


	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	More about this license: http://github.com/appsmata/license.php
*/

@define( 'THEME_DIR', dirname( __FILE__ ) . '/' );
@define( 'THEME_URL', as_opt('site_url') . 'as-theme/' . as_get_site_theme() . '/' );
@define( 'THEME_DIR_NAME', basename( THEME_DIR ) ); 
@define( 'THEME_TEMPLATES', THEME_DIR . 'templates/' );
@define( 'THEME_VERSION', "1.2" );

class as_html_theme extends as_html_theme_base
{
	// use new ranking layout
	protected $ranking_block_layout = true;
	protected $theme = 'vsongpc';

	function vsongbook_resources( $paths, $type = 'css', $external = false )
	{
		if ( count( $paths ) ) {
			foreach ( $paths as $key => $path ) {
				if ( $type === 'js' ) {
					$this->vsongbook_js( $path, $external );
				} else if ( $type === 'css' ) {
					$this->vsongbook_css( $path, $external );
				}
			}
		}
	}
	
	function head_css()
	{
		$css_paths = array(
			'css1'  	=> 'css/bootstrap.min.css',	
			'css2'     	=> 'css/font-awesome-all.css',						
		);
		$this->vsongbook_resources( $css_paths, 'css' );
		parent::head_css();		
	}

	function vsongbook_js( $path, $external = false )
	{
		if ( $external ) {
			$full_path = $path;
		} else {
			$full_path = THEME_URL . $path;
		}
		if ( !empty( $path ) ) {
			$this->output( '<script src="' . $full_path . '" type="text/javascript"></script>' );
		}
	}
	
	function vsongbook_css( $path, $external = false )
	{
		if ( $external ) {
			$full_path = $path;
		} else {
			$full_path = THEME_URL . $path;
		}
		if ( !empty( $path ) ) {
			$this->output( '<link rel="stylesheet" type="text/css" href="' . $full_path . '"/>' );
		}
	}
	
	public function body()
	{
		$this->output('<body>');

		//$this->body_script();
		$this->body_header();
		$this->body_content();
		$this->body_footer();
		$this->body_hidden();

		$this->output('</body>');
	}

	public function body_content()
	{
        //$this->body_prefix();
        
		$this->output('<div class="container-fluid" id="main-container">');

		foreach ($this->content as $key => $part) {
			$this->set_context('part', $key);
			$this->main_part($key, $part);
		}

		$this->clear_context('part');

		$this->output('</div>');

		//$this->body_suffix();
	}

	function body_hidden() 
	{
		//parent::body_hidden();
		$js_paths = array(
			'popper'     	=> 'js/popper.min.js',
			'bootstrap'     => 'js/bootstrap.min.js',
			'datastore'     => 'js/datastore.js',
			'date_utils'    => 'js/date-utils.js',
			'script'     	=> 'js/script.js'
		);
		
		$this->vsongbook_resources( $js_paths, 'js' );
	}

	public function main_part($key, $part)
	{
		if (strpos($key, 'custom') === 0)
			$this->output_raw($part);

		elseif (strpos($key, 'form') === 0)
			$this->form($part);

		elseif (strpos($key, 's_list') === 0)
			$this->s_list_and_form($part);

		elseif (strpos($key, 's_view') === 0)
			$this->q_view($part);

		elseif (strpos($key, 'vsonghome') === 0)
			$this->vsonghome($part);

		elseif (strpos($key, 'a_list') === 0)
			$this->a_list($part);

		elseif (strpos($key, 'ranking') === 0)
			$this->ranking($part);

		elseif (strpos($key, 'message_list') === 0)
			$this->message_list_and_form($part);

		elseif (strpos($key, 'nav_list') === 0) {
			$this->part_title($part);
			$this->nav_list($part['nav'], $part['type'], 1);
		}

	}

	public function vsonghome($home)
	{
		$this->output('<div class="row h-100">');
		$this->output('<div class="col-12 col-sm-5 col-md-4 d-flex flex-column" id="songlist-area" style="position:relative;">');
		
		$this->search_area( $home['booklist'] );		
		$this->song_list( $home['songlist'] );		

		$this->output('<div class="d-none d-sm-flex flex-column col-12 col-sm-7 col-md-8 p-0 h-100" id="message-area">');
		$this->output('<div class="w-100 h-100 overlay"></div>');

		$this->output('<div class="row d-flex flex-row align-items-center p-2 m-0 w-100" id="navbar">
				<div class="d-block d-sm-none">
					<i class="fas fa-arrow-left p-2 mr-2 text-white" style="font-size: 1.5rem; cursor: pointer;" onclick="showChatList()"></i>
				</div>
				<a href="#"><img src="https://via.placeholder.com/400x400" alt="Profile Photo" class="img-fluid rounded-circle mr-2" style="height:50px;" id="pic"></a>
				<div class="d-flex flex-column">
					<div class="text-white font-weight-bold" id="name"></div>
					<div class="text-white small" id="details"></div>
				</div>
				<div class="d-flex flex-row align-items-center ml-auto">
					<a href="#"><i class="fas fa-search mx-3 text-white d-none d-md-block"></i></a>
					<a href="#"><i class="fas fa-paperclip mx-3 text-white d-none d-md-block"></i></a>
					<a href="#"><i class="fas fa-ellipsis-v mr-2 mx-sm-3 text-white"></i></a>
				</div>
			</div>');

		$this->output('<div class="d-flex flex-column" id="messages"></div>');

		$this->output('<div class="d-none justify-self-end align-items-center flex-row" id="input-area">
				<a href="#"><i class="far fa-smile text-muted px-3" style="font-size:1.5rem;"></i></a>
				<input type="text" name="message" id="input" placeholder="Type a message" class="flex-grow-1 border-0 px-3 py-2 my-3 rounded shadow-sm">
				<i class="fas fa-paper-plane text-muted px-3" style="cursor:pointer;" onclick="sendMessage()"></i>
			</div>');
		$this->output('</div>');
		$this->output('</div>');
	}

	public function search_area($booklist)
	{
		$this->output('<div class="row d-flex flex-row align-items-center p-2" id="navbar">
			<input type="text" name="TxtSearch" id="TxtSearch" placeholder="Search for a Song" class="flex-grow-1 px-3 py-2 rounded shadow-sm" onkeyup="as_search_song();" onkeydown="as_search_song();">
		</div>');

		$this->output('<div class="row d-flex flex-row align-items-center p-2" id="navbar">');
		$this->output('<select name="CmbBooks" id="CmbBooks" onchange="as_select_book();" class="flex-grow-1 px-3 py-2 rounded shadow-sm">');
		
		foreach ($booklist as $bk => $book)
		{
			$this->output('<option value="'.$bk.'">'.$book.'</option>');
		}

		$this->output('</select>', '</div>');

	}
	
	public function song_list($songlist)
	{
		$this->output('<div class="row" id="songlist">');

		foreach ($songlist as $sk => $song)
		{
			$this->output('<div class="songlist-item d-flex flex-row w-100 p-2 border-bottom" onclick="generateMessageArea(this, '.$sk.')">
				<div class="w-100">
					<div class="title">'.$song[0].'</div>
					<div class="small last-message">'.$song[1].'</div>
				</div>
			</div>');
		}

		$this->output('</div>');
	}

}
