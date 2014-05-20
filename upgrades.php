<?php

function avatars_upgrade_39() {

	global $wp_filesystem;

	if ( ! function_exists( 'request_filesystem_credentials' ) )
		include_once( ABSPATH . 'wp-admin/includes/file.php' );

	$url = wp_nonce_url( '', 'avatars-nonce' );
		
	if ( false === ( $creds = request_filesystem_credentials( $url, '', false, false, null ) ) ) {
		return; // stop processing here
	}

	if ( ! WP_Filesystem( $creds ) ) {
		request_filesystem_credentials( $url, '', false, false, null );
	}

	$upload_dir = Avatars::get_avatars_upload_dir();
	$avatars_dir = $upload_dir['basedir'] . '/avatars';
	$user_avatar_dir = $avatars_dir . '/user/';
	$blog_avatar_dir = $avatars_dir . '/blog/';

	if ( $wp_filesystem->is_dir( WP_CONTENT_DIR . '/blogs.dir/avatars/user' ) ) {
		// Let's move all avatars to the right folder
		$list = $wp_filesystem->dirlist( WP_CONTENT_DIR . '/blogs.dir/avatars/user' );

		foreach ( $list as $folder => $folder_attr ) {
			if ( ! $wp_filesystem->is_dir( $user_avatar_dir . $folder ) ) {
				$wp_filesystem->mkdir( $user_avatar_dir . $folder );

				$fileslist = $wp_filesystem->dirlist( WP_CONTENT_DIR . '/blogs.dir/avatars/user/' . $folder );

				foreach ( $fileslist as $file => $file_atts ) {
					$wp_filesystem->copy( WP_CONTENT_DIR . '/blogs.dir/avatars/user/' . $folder . '/' . $file, $user_avatar_dir . $folder . '/' . $file );
				}

			}
			
		}
	}

	if ( $wp_filesystem->is_dir( WP_CONTENT_DIR . '/blogs.dir/avatars/blog' ) ) {
		// Let's move all avatars to the right folder
		$list = $wp_filesystem->dirlist( WP_CONTENT_DIR . '/blogs.dir/avatars/blog' );

		foreach ( $list as $folder => $folder_attr ) {
			if ( ! $wp_filesystem->is_dir( $blog_avatar_dir . $folder ) ) {
				$wp_filesystem->mkdir( $blog_avatar_dir . $folder );

				$fileslist = $wp_filesystem->dirlist( WP_CONTENT_DIR . '/blogs.dir/avatars/blog/' . $folder );

				foreach ( $fileslist as $file => $file_atts ) {
					$wp_filesystem->copy( WP_CONTENT_DIR . '/blogs.dir/avatars/blog/' . $folder . '/' . $file, $blog_avatar_dir . $folder . '/' . $file );
				}

			}
			
		}
	}
	
}

function avatars_upgrade_391() {
	global $wpdb,$ms_avatar;

	// Get the max user ID
	$max_id = $wpdb->get_var( "SELECT MAX(ID) FROM $wpdb->users" );

	$users_ids = range( 1, $max_id );
	$query_in = implode( ',', $users_ids );

	$results = $wpdb->get_col( "SELECT ID FROM $wpdb->users WHERE ID IN ($query_in)" );

	$delete_users_ids = array_diff( $users_ids, $results );

	if ( ! empty( $delete_users_ids ) && is_array( $delete_users_ids ) ) {
		foreach ( $delete_users_ids as $user_id ) {
			$ms_avatar->delete_user_avatar( $user_id );
		}
	}

	// Same for blogs
	$max_id = $wpdb->get_var( "SELECT MAX(ID) FROM $wpdb->blogs" );

	$blogs_ids = range( 1, $max_id );
	$query_in = implode( ',', $blogs_ids );

	$results = $wpdb->get_col( "SELECT ID FROM $wpdb->blogs WHERE ID IN ($query_in)" );

	$delete_blogs_ids = array_diff( $blogs_ids, $results );

	if ( ! empty( $delete_blogs_ids ) && is_array( $delete_blogs_ids ) ) {
		foreach ( $delete_blogs_ids as $blog_id ) {
			$ms_avatar->delete_blog_avatar( $blog_id );
		}
	}
}

function avatars_upgrade_392() {
	global $wpdb,$ms_avatar,$wp_filesystem;

	// We are going to delete temporary avatars files
	$avatars_dir = $ms_avatar->get_avatar_dir();

	$url = 'options-general.php';

	if ( ! function_exists( 'request_filesystem_credentials' ) )
		include_once( ABSPATH . '/wp-admin/includes/file.php' );

	if ( false === ( $creds = request_filesystem_credentials( $url, '', false, false, null ) ) ) {
		return; // stop processing here
	}

	if ( ! WP_Filesystem( $creds ) ) {
		request_filesystem_credentials( $url, '', false, false, null );
	}

	if ( $wp_filesystem->is_dir( $avatars_dir ) ) {
		$list = $wp_filesystem->dirlist( $avatars_dir );
		foreach ( $list as $item ) {
			if ( $wp_filesystem->is_file( $avatars_dir . '/' . $item['name'] ) );
				$wp_filesystem->delete( $avatars_dir . '/' . $item['name'] );
		}
	}

}