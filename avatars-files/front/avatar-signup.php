<?php


class Avatars_Signup {

	private $ms_avatars;

	public function __construct() {
		global $ms_avatar;

		$this->ms_avatars = $ms_avatar;

		// Signup: WordPress       
		add_action( 'wp_enqueue_scripts', array( &$this, 'enqueue_front_scripts' ) );
        add_action( 'signup_extra_fields', array( $this, 'render_signup_extra_fields' ) );
        add_action( 'signup_blogform', array( $this, 'registration_render_signup_site_extra_fields' ) );

        add_action( 'wp_ajax_nopriv_avatars_upload_signup_avatar', array( &$this, 'upload_user_signup_avatar' ) );
        add_filter( 'add_signup_meta', array( $this, 'add_signup_meta' ) );

        add_action( 'wpmu_activate_user', array( &$this, 'save_user_avatar' ), 10, 3 );


	}

	public function upload_user_signup_avatar() {
		$avatar_path = $this->ms_avatars->get_avatar_dir() . '/';
		$image_path = $avatar_path . Avatars::encode_avatar_folder( rand( 0, 1000 ) ) . '-user-avatar';

		$result = $this->upload_signup_user_image( $_FILES['user_avatar'], $avatar_path, $image_path );

		if ( 'upload-error' != $result ) {
			echo esc_url( $this->ms_avatars->get_avatar_url() . '/' . $result );
			die();
		}

		echo $result;

		die();
	}

	public function enqueue_front_scripts() {
		global $pagenow;
		if ( 'wp-signup.php' == $pagenow ) {
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'avatars-signup-js', AVATARS_PLUGIN_URL . 'js/signup.js', array( 'jquery' ) );

			$i18n = array(
				'type_error' => __( 'The select file type is invalid. File must be gif, png, jpg or jpeg.', 'avatars' )
			);
			wp_localize_script( 'avatars-signup-js', 'avatars_signup_i18n', $i18n );
		}
	}

	public function render_signup_extra_fields() {
		$user_avatar = ! empty( $_REQUEST['user-avatar-file'] ) ? $_REQUEST['user-avatar-file'] : '';
		$upload_ajax_action = add_query_arg( 'action', 'avatars_upload_signup_avatar', admin_url( 'admin-ajax.php' ) );
		$gif_url = AVATARS_PLUGIN_URL . 'images/ajax-loader.gif'
		?>
			<div id="user-avatar-wrap">
				<label for="user-avatar"><?php _e( 'Your Avatar', 'avatars' ); ?></label>
				<div id="user-avatar-container">
					<?php
						if ( ! empty( $user_avatar ) ) {
							?><img alt="" src="<?php echo $user_avatar; ?>" class="avatar avatar-96 photo avatar-default" height="96" width="96"><?php
						}
						else {
							echo get_avatar( 0, '96', get_option('avatar_default') );
						}

					?>
					<input type="hidden" id="user-avatar-filename" name="user-avatar-file" value="<?php echo $user_avatar; ?>">
					<input type="file" name="user_avatar" id="user-avatar">
				</div>
				
			</div>
			<script>
				jQuery(document).ready(function($) {
					$('#user-avatar').ajaxfileupload({
					  'action': '<?php echo $upload_ajax_action; ?>',
					  'params': {
					    'extra': 'info'
					  },
					  onStart: function() {
					  	$('#user-avatar-container img').attr('src', '<?php echo $gif_url; ?>');
					  },
					  onComplete: function(response) {
					  	console.log(response);
					  	if ( response.status == false ) {
					  		alert(response.message);
					  	}
					  	else if ( 'upload-error' == response ) {
					  		alert( '<?php _e( "There was an error uploading the file. Please try again or skip this step, you can upload an avatar after registration", "avatars" ); ?>' );
						}
						else {
							$('#user-avatar-container img').attr('src', decodeURIComponent( response ) );
					    	$('#user-avatar-filename').val(response);
						}
					  },
					  onCancel: function() {
					    console.log('no file selected');
					    $('#user-avatar-container img').attr('src', '');
					  }
					});
				});
			</script>
		<?php
	}

	public function registration_render_signup_site_extra_fields() {
		$user_avatar = ! empty( $_REQUEST['user-avatar-file'] ) ? $_REQUEST['user-avatar-file'] : '';
		?>
			<input type="hidden" id="user-avatar-filename" name="user-avatar-file" value="<?php echo $user_avatar; ?>">
		<?php
	}

	private function upload_signup_user_image( $file, $avatar_path, $image_path ) {

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


	function add_signup_meta ($meta) {
        $meta = $meta ? $meta : array();
        if ( ! empty( $_POST['user-avatar-file'] ) )
        	$meta['user_avatar'] = basename( $_POST['user-avatar-file'] );
        return $meta;
    }

    function save_user_avatar( $user_id, $pass, $meta ) {
    	if ( ! empty( $meta['user_avatar'] ) ) {
    		$source_dir = $this->ms_avatars->get_avatar_dir();
    		$image_dir = $source_dir . '/user/' . Avatars::encode_avatar_folder( $user_id );
    		wpmudev_debug($image_dir);
    		$this->upload_image( $source_dir, $image_dir, $meta['user_avatar'], 'user', $user_id );
    	}
    }

    private function upload_image( $source_dir, $destination_dir, $filename, $av_type, $avatar_id ) {


    	$image_path = $destination_dir . '/' . $filename;
    	if ( ! is_dir( $destination_dir ) )
    		wp_mkdir_p( $destination_dir );

		$ext = pathinfo( $filename, PATHINFO_EXTENSION );

		$result = rename( $source_dir . '/' . $filename, $image_path );

    	if( $result )
			chmod( $image_path, 0777 );
		else
			return false;
		wpmudev_debug($image_path);
		list( $avatar_width, $avatar_height, $avatar_type, $avatar_attr ) = getimagesize( $image_path );

		if ( $ext == "gif"){
			$avatar_image_type = 'gif';
		}
		elseif ( $ext == "jpeg"){
			$avatar_image_type = 'jpeg';
		}
		elseif ( $ext == "pjpeg"){
			$avatar_image_type = 'jpeg';
		}
		elseif ( $ext == "jpg"){
			$avatar_image_type = 'jpeg';
		}
		elseif ( $ext == "png"){
			$avatar_image_type = 'png';
		}
		elseif ( $ext == "x-png"){
			$avatar_image_type = 'png';
		}
		else {
			return false;
		}

		
		if ($avatar_image_type == 'jpeg')
			$im = ImageCreateFromjpeg( $image_path );

		if ($avatar_image_type == 'png')
			$im = ImageCreateFrompng( $image_path );

		if ($avatar_image_type == 'gif')
			$im = ImageCreateFromgif( $image_path );

		if ( ! $im ) {
			return false;
		}
		
		$sizes = $this->ms_avatars->get_avatar_sizes();
		wpmudev_debug($sizes);
		foreach( $sizes as $avatar_size ) {
			$im_dest = imagecreatetruecolor( $avatar_size, $avatar_size );
			imagecopyresampled( $im_dest, $im, 0, 0, 0, 0, $avatar_size, $avatar_size, $avatar_width, $avatar_height );
			if( 'png' == $avatar_image_type )
				imagesavealpha( $im_dest, true );
			imagepng( $im_dest, $destination_dir . "/$av_type-$avatar_id-$avatar_size.png" );
		}

		$this->ms_avatars->delete_temp( $image_path );

    }

}
