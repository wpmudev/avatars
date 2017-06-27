<?php


class Avatars_Signup {

	private $ms_avatars;

	public function __construct() {
		global $ms_avatar;

		$this->ms_avatars = $ms_avatar;

		if ( defined( 'AVATARS_DISABLE_SIGNUP_UPLOAD' ) && AVATARS_DISABLE_SIGNUP_UPLOAD )
			return;

		// Signup: WordPress       
		add_action( 'wp_enqueue_scripts', array( &$this, 'enqueue_front_scripts' ) );
        add_action( 'signup_extra_fields', array( $this, 'render_signup_extra_fields' ) );
        add_action( 'signup_blogform', array( $this, 'registration_render_signup_site_extra_fields' ) );

        add_filter( 'add_signup_meta', array( $this, 'add_signup_meta' ) );

        add_action( 'wpmu_activate_user', array( &$this, 'save_user_avatar' ), 10, 3 );
        add_action( 'wpmu_activate_blog', array( &$this, '_save_user_avatar' ), 10, 5 );

		// Membership 2
		add_action( 'ms_model_member_create_new_user', array( $this, 'avatars_membership2_update_user_avatar' ) );
	}

	

	public function enqueue_front_scripts() {
		global $pagenow;

		$enqueue_scripts = ( 'wp-signup.php' == $pagenow );
		$enqueue_scripts = apply_filters( 'avatars_enqueue_signup_scripts', $enqueue_scripts, $pagenow );
		if ( $enqueue_scripts ) {
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
							?><img alt="" src="<?php echo AVATARS_PLUGIN_URL . '/images/default-avatar-96.png'; ?>" class="avatar avatar-96 photo avatar-default" height="96" width="96"><?php
						}

					?>
					<input type="hidden" class="user-avatar-filename" id="user-avatar-filename" name="user-avatar-file" value="<?php echo $user_avatar; ?>">
					<button id="user-avatar-field" style="display:none"><?php _e( 'Choose a file', 'avatars' ); ?></button>
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
					  	if ( response.status == false ) {
					  		alert(response.message);
					  	}
					  	else if ( 'upload-error' == response ) {
					  		alert( '<?php _e( "There was an error uploading the file. Please try with another image", "avatars" ); ?>' );
						}
						else {
							$('#user-avatar-container img').attr('src', decodeURIComponent( response ) );
					    	$('.user-avatar-filename').val(response);
						}
					  },
					  onCancel: function() {
					    $('#user-avatar-container img').attr('src', '');
					  }
					});

					$( '#user-avatar' ).hide();
					$( '#user-avatar-field' )
						.show()
						.css( 'display', 'block' )
						.css( 'margin-top', '25px' )
						.css( 'margin-bottom', '25px' )
						.on( 'click', function( e ) {
							e.preventDefault();
							$( '#user-avatar' ).trigger( 'click' );
						});
				});
			</script>
		<?php
	}

	public function registration_render_signup_site_extra_fields() {
		$user_avatar = ! empty( $_REQUEST['user-avatar-file'] ) ? $_REQUEST['user-avatar-file'] : '';
		?>
			<input type="hidden" id="user-avatar-filename" class="user-avatar-filename" name="user-avatar-file" value="<?php echo $user_avatar; ?>">
		<?php
	}

	


	function add_signup_meta ($meta) {
        $meta = $meta ? $meta : array();
        if ( ! empty( $_POST['user-avatar-file'] ) )
        	$meta['user_avatar'] = basename( $_POST['user-avatar-file'] );
        return $meta;
    }

    public function _save_user_avatar( $blog_id, $user_id, $pass, $signup_title, $meta ) {
    	$this->save_user_avatar( $user_id, $pass, $meta );
    }

    function save_user_avatar( $user_id, $pass, $meta ) {

    	if ( ! empty( $meta['user_avatar'] ) ) {
    		$source_dir = $this->ms_avatars->get_avatar_dir();
    		$image_dir = $source_dir . '/user/' . Avatars::encode_avatar_folder( $user_id );

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
			imagepng( $im_dest, $destination_dir . "/$av_type-$avatar_id-$avatar_size.png" );
		}

		$this->ms_avatars->delete_temp( $image_path );

    }

	function avatars_membership2_update_user_avatar( $model ) {
		/** @var MS_Model_Member $vars */
		if ( method_exists( $model, 'get_object_vars' ) ) {
			$vars = $model->get_object_vars();
			$user = get_user_by( 'id', $vars['id'] );
			if ( ! $user ) {
				return;
			}

			if ( ! isset( $_REQUEST['user-avatar-file'] ) ) {
				return;
			}

			$user_avatar = sanitize_text_field( basename( $_REQUEST['user-avatar-file'] ) );

			$source_dir = $this->ms_avatars->get_avatar_dir();
			$image_dir = $source_dir . '/user/' . Avatars::encode_avatar_folder( $vars['id'] );

			$this->upload_image( $source_dir, $image_dir, $user_avatar, 'user', $vars['id'] );
		}
	}


}
