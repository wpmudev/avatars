<?php
/*
Plugin Name: Avatars
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

function wp_check_filetype($filename, $mimes = null) {
	// Accepted MIME types are set here as PCRE unless provided.
	$mimes = is_array($mimes) ? $mimes : array (
		'jpg|jpeg|jpe' => 'image/jpeg',
		'gif' => 'image/gif',
		'png' => 'image/png',
		'bmp' => 'image/bmp'
	);

	$type = false;
	$ext = false;

	foreach ($mimes as $ext_preg => $mime_match) {
		$ext_preg = '!\.(' . $ext_preg . ')$!i';
		if ( preg_match($ext_preg, $filename, $ext_matches) ) {
			$type = $mime_match;
			$ext = $ext_matches[1];
			break;
		}
	}

	return compact('ext', 'type');
}


$file = $_GET[ 'file' ];

$avatars_include = 'TRUE';
require_once('mu-plugins/avatars.php' );

list($tmp_avatar_type, $tmp_avatar_id, $tmp_avatar_size) = explode('-', str_replace('.png','',$file));
//die($tmp_avatar_type . '|' . $tmp_avatar_id . '|' . $tmp_avatar_size);
if ($tmp_avatar_type == 'blog'){
	$file = $blog_avatars_short_path . substr(md5($tmp_avatar_id), 0, 3) . '/' . $file;
} else if ($tmp_avatar_type == 'user'){
	$file = $user_avatars_short_path . substr(md5($tmp_avatar_id), 0, 3) . '/' . $file;
} else {
	die ('invalid');
}
if ( !is_file( $file ) ) {
	if ($tmp_avatar_type == '') {
		header("HTTP/1.1 404 Not Found");
		die('404 &#8212; Invalid Type.');	
	} else if ($tmp_avatar_size == '') {
		header("HTTP/1.1 404 Not Found");
		die('404 &#8212; Invalid Size.');
	} else {
		if ($tmp_avatar_type == 'blog') {
			if ($default_blog_avatar == 'local_default') {
				$file = 'default-avatar-' . $tmp_avatar_size . '.png';
			} else {
				$file = 'http://www.gravatar.com/avatar/' . md5($tmp_avatar_id) . '?r=G&d=' . $default_blog_avatar . '&s=' . $tmp_avatar_size;
			}
		} else {
			if ($default_user_avatar == 'local_default') {
				$file = 'default-avatar-' . $tmp_avatar_size . '.png';
			} else {
				$file = 'http://www.gravatar.com/avatar/' . md5($tmp_avatar_id) . '?r=G&d=' . $default_user_avatar . '&s=' . $tmp_avatar_size;
			}
		}
		$default_avatar = "1";
	}
} else {
	$default_avatar = "0";
}

if ($default_avatar == "1") {
	list($width_orig, $height_orig, $type) = getimagesize($file);
	if ($type == '2'){
		//JPG
		header('Content-type: image/jpeg');
		$image = imagecreatefromjpeg($file);
		imagejpeg($image, null, 9);
	} else if ($type == '3'){
		//PNG
		header('Content-type: image/png');
		$image = imagecreatefrompng($file);
		imagepng($image);
	} else if ($type == '1'){
		//GIF
		header('Content-type: image/gif');
		$image = imagecreatefromgif($file);
		imagegif($image, null, 9);
	} else if ($type == '6'){
		//WBMP
		header('Content-type: image/vnd.wap.wbmp');
		$image = imagecreatefromwbmp($file);
		imagewbmp($image, null, 9);
	}
} else {

	$mime = wp_check_filetype( $_SERVER[ 'REQUEST_URI' ] );
	if( $mime[ 'type' ] != false ) {
		$mimetype = $mime[ 'type' ];
	} else {
		$ext = substr( $_SERVER[ 'REQUEST_URI' ], strrpos( $_SERVER[ 'REQUEST_URI' ], '.' ) + 1 );
		$mimetype = "image/$ext";
	}
	header( 'Content-type: ' . $mimetype); // always send this
	
	$timestamp = filemtime( $file );
	
	$last_modified = gmdate('D, d M Y H:i:s', $timestamp);
	$etag = '"' . md5($last_modified) . '"';
	@header( "Last-Modified: $last_modified GMT" );
	@header( 'ETag: ' . $etag );
	
	$expire = gmdate('D, d M Y H:i:s', time() + 100000000);
	@header( "Expires: $expire GMT" );
	
	// Support for Conditional GET
	if (isset($_SERVER['HTTP_IF_NONE_MATCH'])) $client_etag = stripslashes($_SERVER['HTTP_IF_NONE_MATCH']);
	else $client_etag = false;
	
	$client_last_modified = trim($_SERVER['HTTP_IF_MODIFIED_SINCE']);
	// If string is empty, return 0. If not, attempt to parse into a timestamp
	$client_modified_timestamp = $client_last_modified ? strtotime($client_last_modified) : 0;
	
	// Make a timestamp for our most recent modification...	
	$modified_timestamp = strtotime($last_modified);
	
	if ( ($client_last_modified && $client_etag) ?
		 (($client_modified_timestamp >= $modified_timestamp) && ($client_etag == $etag)) :
		 (($client_modified_timestamp >= $modified_timestamp) || ($client_etag == $etag)) ) {
		header('HTTP/1.1 304 Not Modified');
		exit;
	}
	
	// If we made it this far, just serve the file
	readfile( $file );

}
?>
