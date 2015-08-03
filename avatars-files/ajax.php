<?php

add_action( 'wp_ajax_nopriv_avatars_upload_signup_avatar', 'avatars_upload_user_signup_avatar' );
function avatars_upload_user_signup_avatar() {
	global $ms_avatar;
	$avatar_path = $ms_avatar->get_avatar_dir() . '/';
	$image_path = $avatar_path . Avatars::encode_avatar_folder( rand( 0, 1000 ) ) . '-user-avatar';

	$result = avatars_upload_signup_user_image( $_FILES['user_avatar'], $avatar_path, $image_path );

	if ( 'upload-error' != $result ) {
		echo esc_url( $ms_avatar->get_avatar_url() . '/' . $result );
		die();
	}

	echo $result;

	die();
}

function avatars_upload_signup_user_image( $file, $avatar_path, $image_path ) {

	$type = $file['type'];
	$file_name = $file['name'];
	$tmp_name = $file['tmp_name'];

	if ( $type == "image/gif"){
		$avatar_image_type = 'gif';
		$ext = '.gif';
	}
	if ( $type == "image/jpeg"){
		$avatar_image_type = 'jpeg';
		$ext = '.jpeg';
	}
	if ( $type == "image/pjpeg"){
		$avatar_image_type = 'jpeg';
		$ext = '.jpeg';
	}
	if ( $type == "image/jpg"){
		$avatar_image_type = 'jpeg';
		$ext = '.jpeg';
	}
	if ( $type == "image/png"){
		$avatar_image_type = 'png';
		$ext = '.png';
	}
	if ( $type == "image/x-png"){
		$avatar_image_type = 'png';
		$ext = '.png';
	}

	$image_path .= $ext;

	if( move_uploaded_file( $tmp_name, $image_path ) )
		chmod( $image_path, 0777 );
	else
		return 'upload-error';

	$im = false;
	try {
		list( $avatar_width, $avatar_height, $avatar_type, $avatar_attr ) = getimagesize( $image_path );


		if ($avatar_image_type == 'jpeg')
			$im = @ImageCreateFromjpeg( $image_path );

		if ($avatar_image_type == 'png')
			$im = @ImageCreateFrompng( $image_path );

		if ($avatar_image_type == 'gif')
			$im = @ImageCreateFromgif( $image_path );
	}
	catch( Exception $e ) {
		return 'upload-error';
	}

	if (!$im) {
		return 'upload-error';
	}

	return basename( $image_path );


}