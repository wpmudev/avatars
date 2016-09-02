<?php

/**
 * Return user avatar.
 **/
if( !function_exists( 'get_avatar' ) ):
	function get_avatar( $id_or_email, $size = '96', $default = '', $alt = false, $args = array() ) {
		global $ms_avatar;
		return $ms_avatar->get_avatar( $id_or_email, $size, $default, $alt, $args );
	}
endif;

/**
 * Return blog avatar.
 **/
if( !function_exists( 'get_blog_avatar' ) ):
	function get_blog_avatar( $id, $size = '96', $default = '', $alt = false ) {
		global $ms_avatar;
		return $ms_avatar->get_blog_avatar( $id, $size, $default, $alt );
	}
endif;

if( !function_exists( 'get_blog_avatar_url' ) ):
	function get_blog_avatar_url( $id, $size = '96', $default = '' ) {
		global $ms_avatar;
		return $ms_avatar->get_blog_avatar_url( $id, $size, $default );
	}
endif;



/**
 * Display blog avatar by user ID.
 **/
function avatar_display_posts( $user_ID, $size = '32', $deprecated = '' ) {
	global $ms_avatar;
	return $ms_avatar->display_posts( $user_ID, $size, $deprecated );
}

/**
 * Display blog avatar by user email.
 **/
function avatar_display_comments( $user_email, $size = '32', $deprecated = '' ) {
	global $ms_avatar;
	return $ms_avatar->display_comments( $user_email, $size, $deprecated );
}