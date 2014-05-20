<?php
$file = basename($file);

list( $tmp_avatar_type, $tmp_avatar_id, $tmp_avatar_size ) = explode( '-', str_replace( '.png', '', $file ) );

if ( $tmp_avatar_type == 'blog' ) {
	$file = $blog_avatars_path . substr(md5($tmp_avatar_id), 0, 3) . '/' . $file;
} else if( $tmp_avatar_type == 'user' ) {
	$file = $user_avatars_path . substr(md5($tmp_avatar_id), 0, 3) . '/' . $file;
} else {
	wp_die( __( 'Invalid Request', 'avatars' ) );
}
if ( !is_file( $file ) ) {
	if ( $tmp_avatar_type == '' ) {
		header("HTTP/1.1 404 Not Found");
		wp_die( __( '404 &#8212; Invalid Avatar Type.', 'avatars' ) );
	} elseif ($tmp_avatar_size == '') {
		header("HTTP/1.1 404 Not Found");
		wp_die( __( '404 &#8212; Invalid Avatar Size.', 'avatars' ) );
	} else {
		if ( $tmp_avatar_type == 'blog' ) {
			if ( $default_blog_avatar == 'local_default' ) {
				$file = $local_default_avatar_path . $tmp_avatar_size . '.png';
			} else {
				$file = 'http://www.gravatar.com/avatar/' . md5($tmp_avatar_id) . '?r=G&d=' . $default_blog_avatar . '&s=' . $tmp_avatar_size;
			}
		} else {
			if ( empty( $default_user_avatar ) || 'local_default' == $default_user_avatar ) {
				$file = $local_default_avatar_path . $tmp_avatar_size . '.png';
			} else {
				$file = 'http://www.gravatar.com/avatar/' . md5($tmp_avatar_id) . '?r=G&d=' . $default_user_avatar . '&s=' . $tmp_avatar_size;
			}
		}
		$default_avatar = "1";
	}
} else {
	$default_avatar = "0";
}

if ( $default_avatar == "1" ) {
	list( $width_orig, $height_orig, $type ) = getimagesize($file);
	if ($type == '2') {
		//JPG
		header('Content-type: image/jpeg');
		$image = imagecreatefromjpeg($file);
		imagejpeg($image, null, 9);
	} else if ($type == '3') {
		//PNG
		header('Content-type: image/png');
		$image = imagecreatefrompng($file);
		imagepng($image);
	} else if ($type == '1') {
		//GIF
		header('Content-type: image/gif');
		$image = imagecreatefromgif($file);
		imagegif($image, null, 9);
	} else if ($type == '6') {
		//WBMP
		header('Content-type: image/vnd.wap.wbmp');
		$image = imagecreatefromwbmp($file);
		imagewbmp($image, null, 9);
	}
} else {
	@header( 'Content-type: image/png' );

	$timestamp = filemtime( $file );

	$last_modified = gmdate('D, d M Y H:i:s', $timestamp);
	$etag = '"' . md5($last_modified) . '"';
	@header( "Last-Modified: $last_modified GMT" );
	@header( 'ETag: ' . $etag );

	$expire = gmdate('D, d M Y H:i:s', time() + 100000000);
	@header( "Expires: $expire GMT" );

	$client_etag = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? stripslashes( $_SERVER['HTTP_IF_NONE_MATCH'] ) : false;

	$client_last_modified = isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ? trim( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) : false;
	$client_modified_timestamp = $client_last_modified ? strtotime( $client_last_modified ) : 0;

	// Make a timestamp for our most recent modification...
	$modified_timestamp = strtotime( $last_modified );

	if ( ($client_last_modified && $client_etag) ?
		 (($client_modified_timestamp >= $modified_timestamp) && ($client_etag == $etag)) :
		 (($client_modified_timestamp >= $modified_timestamp) || ($client_etag == $etag)) ) {
		header('HTTP/1.1 304 Not Modified');
		exit;
	}

	// If we made it this far, just serve the file
	readfile( $file );

}
