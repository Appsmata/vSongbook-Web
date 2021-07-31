<?php
// Background customizations
global $p_url;
$bg_file=as_opt('qat_bg_image');
// background image
if ($bg_file=='Default Background')
	$bg_image='';
elseif ($bg_file=='NO Background')
	$bg_image='none';
else 
	$bg_image='url("' . $p_url . '/' . $bg_file . '.png")';
// background color
if (as_opt('qat_bg_color_on'))
	$bg_color=as_opt('qat_bg_color');
// background font family
$b_fontfamily=as_opt('qat_b_font');

// Navigation
$main_nav_fontfamily=as_opt('qat_main_nav_font');
$sub_nav_fontfamily=as_opt('qat_sub_nav_font');
$user_nav_fontfamily=as_opt('qat_user_nav_font');
// content typography
$q_list_title_fontfamily=as_opt('qat_q_list_title_font');
$q_list_excerp_fontfamily=as_opt('qat_q_list_excerp_font');
$q_title_fontfamily=as_opt('qat_q_title_font');
$q_content_fontfamily=as_opt('qat_q_content_font');
$a_content_fontfamily=as_opt('qat_a_content_font');
$c_content_fontfamily=as_opt('qat_c_content_font');
$s_bar_fontfamily=as_opt('qat_s_bar_font');
$sb_bar_fontfamily=as_opt('qat_sb_bar_font');

$fs_b=as_opt('qat_fs_b');
$fs_main_nav=as_opt('qat_fs_main_nav');
$fs_sub_nav=as_opt('qat_fs_sub_nav');
$fs_user_nav=as_opt('qat_fs_user_nav');
$fs_q_list_title=as_opt('qat_fs_q_list_title');
$fs_q_list_excerp=as_opt('qat_fs_q_list_excerp');
$fs_q_title=as_opt('qat_fs_q_title');
$fs_q_content=as_opt('qat_fs_q_content');
$fs_a_content=as_opt('qat_fs_a_content');
$fs_c_content=as_opt('qat_fs_c_content');
$fs_s_bar=as_opt('qat_fs_s_bar');
$fs_sb_bar=as_opt('qat_fs_sb_bar');

$css = '';
$css .= 'body {';
	if (!(empty($bg_color))) $css .=  'background-color: ' . $bg_color .' !important;';
	if (!(empty($bg_image))) $css .=  'background-image: ' . $bg_image .' !important;';
	if (!(empty($b_fontfamily))) $css .=  'font-family: ' . $b_fontfamily .' !important;';
	if (!(empty($fs_b))) $css .=  'font-size: ' . $fs_b .'px !important;';
$css .=  '}';

$css .=  '.as-nav-main-link {';
	if (!(empty($main_nav_fontfamily))) $css .=  'font-family: ' . $main_nav_fontfamily .' !important;';
	if (!(empty($fs_main_nav))) $css .=  'font-size: ' . $fs_main_nav .'px !important;';
	
$css .=  '}';
$css .=  '.as-nav-sub {';
	if (!(empty($sub_nav_fontfamily))) $css .=  'font-family: ' . $sub_nav_fontfamily .' !important;';
	if (!(empty($fs_sub_nav))) $css .=  'font-size: ' . $fs_sub_nav .'px !important;';
$css .=  '}';
$css .=  '.as-nav-user {';
	if (!(empty($user_nav_fontfamily))) $css .=  'font-family: ' . $user_nav_fontfamily .' !important;';
	if (!(empty($fs_user_nav))) $css .=  'font-size: ' . $fs_user_nav .'px !important;';
$css .=  '}';

$css .=  '.as-q-item-title a {';
	if (!(empty($q_list_title_fontfamily))) $css .=  'font-family: ' . $q_list_title_fontfamily .' !important;';
	if (!(empty($fs_q_list_title))) $css .=  'font-size: ' . $fs_q_list_title .'px !important;';
$css .=  '}';
$css .=  '.as-q-item-content {';
	if (!(empty($q_list_excerp_fontfamily))) $css .=  'font-family: ' . $q_list_excerp_fontfamily .' !important;';
	if (!(empty($fs_q_list_excerp))) $css .=  'font-size: ' . $fs_q_list_excerp .'px !important;';
$css .=  '}';
$css .=  '.as-main h1 .entry-title {';
	if (!(empty($q_title_fontfamily))) $css .=  'font-family: ' . $q_title_fontfamily .' !important;';
	if (!(empty($fs_q_title))) $css .=  'font-size: ' . $fs_q_title .'px !important;';
$css .=  '}';
$css .=  '.as-q-view-main .entry-content {';
	if (!(empty($q_content_fontfamily))) $css .=  'font-family: ' . $q_content_fontfamily .' !important;';
	if (!(empty($fs_q_content))) $css .=  'font-size: ' . $fs_q_content .'px !important;';
$css .=  '}';
$css .=  '.as-a-item-content .entry-content {';
	if (!(empty($a_content_fontfamily))) $css .=  'font-family: ' . $a_content_fontfamily .' !important;';
	if (!(empty($fs_a_content))) $css .=  'font-size: ' . $fs_a_content .'px !important;';
$css .=  '}';
$css .=  '.as-c-item-content .entry-content {';
	if (!(empty($c_content_fontfamily))) $css .=  'font-family: ' . $c_content_fontfamily .' !important;';
	if (!(empty($fs_c_content))) $css .=  'font-size: ' . $fs_c_content .'px !important;';
$css .=  '}';

$css .=  '.as-sidepanel {';
	if (!(empty($sb_bar_fontfamily))) $css .=  'font-family: ' . $sb_bar_fontfamily .' !important;';
	if (!(empty($fs_s_bar))) $css .=  'font-size: ' . $fs_s_bar .'px !important;';
$css .=  '}';
$css .=  '.as-sidebar {';
	if (!(empty($qat_fs_sb_bar))) $css .=  'font-family: ' . $qat_fs_sb_bar .' !important;';
	if (!(empty($fs_sb_bar))) $css .=  'font-size: ' . $fs_sb_bar .'px !important;';
$css .=  '}';

as_opt('qat_custom_style', $css);


/*
	Omit PHP closing tag to help avoid accidental output
*/