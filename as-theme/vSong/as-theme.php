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

class as_html_theme extends as_html_theme_base
{
	
	public function head_css()
	{
		$this->output('<link rel="stylesheet" href="' . $this->rooturl . 'bootstrap.min.css?' . AS_VERSION . '"/>');
		$this->output('<link rel="stylesheet" href="' . $this->rooturl . 'fontawesome.all.css?' . AS_VERSION . '"/>');
		$this->output('<link rel="stylesheet" href="' . $this->rooturl . 'as-styles.css?' . AS_VERSION . '"/>');

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
		$this->output('<div class="row h-100">');
		$this->output('<div class="col-12 col-sm-5 col-md-4 d-flex flex-column" id="chat-list-area" style="position:relative;">');

		$this->nav_bar_i();
		$this->song_list();
		$this->preview_area();

		
		$this->output('<div class="d-flex flex-column" id="messages"></div>');

		$this->output('<div class="d-none justify-self-end align-items-center flex-row" id="input-area"></div>');
		$this->output('</div>', '</div>', '</div>');
	}

	function nav_bar_i()
	{
		$this->output('<div class="row d-flex flex-row align-items-center p-2" id="navbar">');
		$this->output('<table style="width: 100%;">');
		$this->output('<tr>
		<td><input type="text" name="search" id="input" placeholder="Search for a Song" class="flex-column border-0 px-3 py-2 rounded"
		style="width: 100%;"></td>
		</tr>');
		$this->output('<tr>
		<td>
		<select name="search" class="flex-column border-0 px-3 py-2 rounded" style="width: 100%;">
		<option value="worship">Songs of Worship</option>
		<option value="injili">Nyimbo za Injili</option>
		<option value="redemption">Redemption Songs</option>
		<option value="tenzi">Tenzi za Rohoni</option>
		</select>
		</td>
		</tr>
		</table>');
		$this->output('</div>');
	}

	function song_list()
	{
		$this->output('<div class="row" id="chat-list" style="overflow:auto;"></div>');
		$this->settings();
		$this->output('</div>');
	}

	function settings()
	{
		$this->output('<div class="d-flex flex-column w-100 h-100" id="profile-settings">
		<div class="row d-flex flex-row align-items-center p-2 m-0" style="background:#FF4500; min-height:65px;">
		<i class="fas fa-arrow-left p-2 mx-3 my-1 text-white" style="font-size: 1.5rem; cursor: pointer;" onclick="hideProfileSettings()"></i>
		<div class="text-white font-weight-bold">Profile</div>
		</div>
		<div class="d-flex flex-column" style="overflow:auto;">
		<img alt="Profile Photo" class="img-fluid rounded-circle my-5 justify-self-center mx-auto" id="profile-pic">
		<input type="file" id="profile-pic-input" class="d-none">
		<div class="bg-white px-3 py-2">
		<div class="text-muted mb-2"><label for="input-name">Your Name</label></div>
		<input type="text" name="name" id="input-name" class="w-100 border-0 py-2 profile-input">
		</div>
		<div class="text-muted p-3 small">
		This is not your username or pin. This name will be visible to your WhatsApp contacts.
		</div>
		<div class="bg-white px-3 py-2">
		<div class="text-muted mb-2"><label for="input-about">About</label></div>
		<input type="text" name="name" id="input-about" value="" class="w-100 border-0 py-2 profile-input">
		</div>
		</div>
	
		</div>');
	}

	function preview_area()
	{
		$this->output('<div class="d-none d-sm-flex flex-column col-12 col-sm-7 col-md-8 p-0 h-100" id="message-area">
				<div class="w-100 h-100 overlay"></div>

				<!-- Navbar -->
				<div class="row d-flex flex-row align-items-center p-2 m-0 w-100" id="navbar">
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
	}

	function body_hidden() 
	{
		$this->output('<script src="' . $this->rooturl . 'jquery-3.3.1.slim.min.js?' . AS_VERSION . '"></script>');
		$this->output('<script src="' . $this->rooturl . 'popper.min.js?' . AS_VERSION . '"></script>');
		$this->output('<script src="' . $this->rooturl . 'bootstrap.min.js?' . AS_VERSION . '"></script>');
		$this->output('<script src="' . $this->rooturl . 'datastore.js?' . AS_VERSION . '"></script>');
		$this->output('<script src="' . $this->rooturl . 'date-utils.js?' . AS_VERSION . '"></script>');
		$this->output('<script src="' . $this->rooturl . 'script.js?' . AS_VERSION . '"></script>');
	}

}
