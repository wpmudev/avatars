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