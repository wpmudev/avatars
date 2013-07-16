<?php


class Avatars_Signup {

	private $ms_avatars;

	public function __construct() {
		global $ms_avatar;

		$this->ms_avatars = $ms_avatar;

		// Signup: WordPress       
		add_action( 'wp_enqueue_scripts', array( &$this, 'enqueue_front_scripts' ) );
        add_action( 'signup_extra_fields', array( $this, 'render_signup_extra_fields' ) );

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
			$params = array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'gifurl' => AVATARS_PLUGIN_URL . 'images/ajax-loader.gif'
			);
			wp_localize_script( 'avatars-signup-js', 'export_to_text_js', $params );
		}
	}

	public function render_signup_extra_fields() {
		?>
			<label for="user-avatar"><?php _e( 'Your Avatar', 'avatars' ); ?></label>
			<div id="user-avatar-container">
			<?php
				echo get_avatar( 0, '96', get_option('avatar_default') );
			?>
			</div><br/>
			<input type="hidden" id="user-avatar-filename" name="user-avatar-file" value="">
			<input type="file" name="user_avatar" id="user-avatar"><br/><br/>
			
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

		list( $avatar_width, $avatar_height, $avatar_type, $avatar_attr ) = getimagesize( $image_path );


		if ($avatar_image_type == 'jpeg')
			$im = ImageCreateFromjpeg( $image_path );

		if ($avatar_image_type == 'png')
			$im = ImageCreateFrompng( $image_path );

		if ($avatar_image_type == 'gif')
			$im = ImageCreateFromgif( $image_path );

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
    	if ( ! empty( $meta['user-avatar'] ) ) {
    		$source_dir = $this->ms_avatars->get_avatar_dir();
    		$image_dir = $source_dir . '/user/' . Avatars::encode_avatar_folder( $user_id );

    		$this->upload_image( $source_dir, $image_dir, $meta['user-avatar'] );
    	}
    }

    private function upload_image( $source_dir, $destination_dir, $filename, $type ) {
    	$image_path = $destination_dir . '/' . $filename;

		$ext = pathinfo( $filename, PATHINFO_EXTENSION );

    	if( move_uploaded_file( $source_dir, $image_path ) )
			chmod( $image_path, 0777 );
		else
			return false;

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

		foreach( $sizes as $avatar_size ) {
			$im_dest = imagecreatetruecolor( $avatar_size, $avatar_size );
			imagecopyresampled( $im_dest, $im, 0, 0, 0, 0, $avatar_size, $avatar_size, $avatar_width, $avatar_height );
			if( 'png' == $avatar_image_type )
				imagesavealpha( $im_dest, true );
			imagepng( $im_dest, $avatar_path . "$av_type-$avatar_id-$avatar_size.png" );
		}

		$this->ms_avatars->delete_temp( $source_dir . '/' . basename( $file_name ) );

    }

}

$avatars_signup = new Avatars_Signup();