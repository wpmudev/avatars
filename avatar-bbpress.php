<?php
/*
Plugin Name: Avatars (BBPress)
Plugin URI: 
Description:
Author: Andrew Billits (Incsub)
Version: 3.4.0
Author URI: http://incsub.com
*/

/* 
Copyright 2007-2009 Incsub (http://incsub.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

/*
<?php avatar_display_bbpress(get_post_author_id( $bb_post->post_id ),'48',''); ?>
*/
//------------------------------------------------------------------------//
//---Config---------------------------------------------------------------//
//------------------------------------------------------------------------//

$wpmu_url = 'http://domain.com/';
$wpmu_users_table = 'wp_users';
$wpmu_usermeta_table = 'wp_usermeta';
$wpmu_blogs_table = 'wp_blogs';
$user_avatars_path = '/path/to/wp-content/avatars/user/';

//------------------------------------------------------------------------//
//---Hook-----------------------------------------------------------------//
//------------------------------------------------------------------------//

//------------------------------------------------------------------------//
//---Functions------------------------------------------------------------//
//------------------------------------------------------------------------//

//------------------------------------------------------------------------//
//---Output Functions-----------------------------------------------------//
//------------------------------------------------------------------------//



function get_avatar_bbpress($id,$email, $size = '96', $default = 'identicon' ) {
	global $current_site, $wpmu_url, $user_avatars_path;
	if ( $default == 'local_default' ) {
		$default = $wpmu_url . 'wp-content/default-avatar-' . $size . '.png';
	} else if ( $default == 'gravatar_default' ) {
		$default = 'http://www.gravatar.com/avatar/' . md5($email) . '?r=G&s=' . $size;
	} else if ( $default == 'identicon' ) {
		$default = 'http://www.gravatar.com/avatar/' . md5($email) . '?r=G&d=identicon&s=' . $size;
	} else if ( $default == 'wavatar' ) {
		$default = 'http://www.gravatar.com/avatar/' . md5($email) . '?r=G&d=wavatar&s=' . $size;
	} else if ( $default == 'monsterid' ) {
		$default = 'http://www.gravatar.com/avatar/' . md5($email) . '?r=G&d=monsterid&s=' . $size;
	} else {	
		$default = $wpmu_url . 'wp-content/default-avatar-' . $size . '.png';
	}

	//user exists locally - check if avatar exists
	$file = $user_avatars_path . substr(md5($id), 0, 3) . '/user-' . $id . '-' . $size . '.png';
	if ( file_exists( $file ) ) {
		$path = $wpmu_url . 'avatar/user-' . $id . '-' . $size . '.png';
	} else {
		$path = $default;
	}
	$avatar = "<img alt='' src='{$path}' class='avatar avatar-{$size}' height='{$size}' width='{$size}' />";
	return $avatar;
}

function avatar_display_bbpress($user_ID, $size='32', $depricated=''){
	global $bbdb, $wpmu_url, $wpmu_usermeta_table, $wpmu_blogs_table, $wpmu_users_table;
	
	$user_email = $bbdb->get_var("SELECT user_email FROM " . $wpmu_users_table . " WHERE ID = '" . $user_ID . "'");
	$blog_ID = $bbdb->get_var("SELECT meta_value FROM " . $wpmu_usermeta_table . " WHERE meta_key = 'primary_blog' AND user_id = '" . $user_ID . "'");
	if ($blog_ID != '') {
		$blog_domain = $bbdb->get_var("SELECT domain FROM " . $wpmu_blogs_table . " WHERE blog_id = '" . $blog_ID . "'");
		$blog_path = $bbdb->get_var("SELECT path FROM " . $wpmu_blogs_table . " WHERE blog_id = '" . $blog_ID . "'");
	}
	if ($blog_ID != ''){
		echo '<a href="http://' . $blog_domain . $blog_path . '" style="text-decoration:none">' . get_avatar_bbpress($user_ID,$user_email,$size,'identicon') . '</a>';
	} else {
		echo get_avatar_bbpress($user_ID,'',$size,'identicon');
	}
}

//------------------------------------------------------------------------//
//---Page Output Functions------------------------------------------------//
//------------------------------------------------------------------------//

?>