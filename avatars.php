<?php
/*
Plugin Name: Avatars
Plugin URI: 
Description:
Author: Andrew Billits (Incsub)
Version: 3.4.1
Author URI: http://incsub.com
WDP ID: 10
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

//------------------------------------------------------------------------//
//---Config---------------------------------------------------------------//
//------------------------------------------------------------------------//
$display_avatar_admin_message = 'yes'; //Options: 'yes' or 'no'
$enable_main_blog_avatar = 'yes'; //Options: 'yes' or 'no'
$blog_avatars_short_path = 'avatars/blog/';
$user_avatars_short_path = 'avatars/user/';
$blog_avatars_path = 'wp-content/avatars/blog/';
$user_avatars_path = 'wp-content/avatars/user/';
$default_avatar_rating = 'G'; //'G', 'PG', 'R', or 'X' - Leave blank to allow users to choose
$default_user_avatar = 'identicon'; //'local_default', 'gravatar_default', 'identicon', 'wavatar', 'monsterid'
$default_blog_avatar = 'identicon'; //'local_default', 'gravatar_default', 'identicon', 'wavatar', 'monsterid'
//------------------------------------------------------------------------//
//---Hook-----------------------------------------------------------------//
//------------------------------------------------------------------------//
if ($avatars_include != 'TRUE'){
	add_action('admin_menu', 'avatars_plug_pages');
	add_filter('whitelist_options', 'avatars_whitelist');
	if ($_GET['page'] == 'blog-avatar' || $_GET['page'] == 'user-avatar' || $_GET['page'] == 'edit-user-avatar' || $_GET['page'] == 'edit-blog-avatar'){
		if ($_GET['action'] == 'upload_process'){
			add_action('admin_head', 'avatars_plug_scripts');
			add_action('init','avatars_enqueue_scripts');
		}
	}
	if ($_GET['page'] == 'edit_blog_avatar' || $_GET['page'] == 'edit_user_avatar') {
	add_action('init', 'avatars_legacy_redirect');
	}
	if ($current_site != null) {
		$local_default_avatar_url = 'http://' . $current_site->domain . $current_site->path . 'wp-content/default-avatar-';
	} else if ( $current_blog != null ) {
		$local_default_avatar_url = 'http://' . $current_blog->domain . $current_blog->path . 'wp-content/default-avatar-';
	} else {
		add_action('init','avatars_local_default_url');
	}
	add_filter('wpmu_users_columns', 'avatars_site_admin_column_header');
	add_action('manage_users_custom_column','avatars_site_admin_column_content', 1, 2);
}

function avatars_local_default_url() {
	global $local_default_avatar_url;
	$local_default_avatar_url = 'http://' . $current_site->domain . $current_site->path . 'wp-content/default-avatar-';
}
//------------------------------------------------------------------------//
//---Functions------------------------------------------------------------//
//------------------------------------------------------------------------//
function avatars_site_admin_column_content($column,$user_ID) {
	global $current_site;
	if ( $column == 'avatar' ) {
		echo get_avatar( $user_ID, 32,'' );
		?>
        <br />
        (<a href="ms-admin.php?page=edit-user-avatar&uid=<?php echo $user_ID; ?>" style="text-decoration:none;"><?php _e('Edit'); ?></a>)
        <?php
	}
}

function avatars_site_admin_column_header($posts_columns) {
	$new_column = array('avatar' => __('Avatar'));
	$posts_columns = array_merge($posts_columns, $new_column);
	return $posts_columns;
}

function avatars_legacy_redirect() {
	if ($_GET['page'] == 'edit_blog_avatar') {
		echo "
		<SCRIPT LANGUAGE='JavaScript'>
		window.location='profile.php?page=blog-avatar';
		</script>
		";
	}
	if ($_GET['page'] == 'edit_user_avatar') {
		if ( current_user_can('manage_options') ) {
			echo "
			<SCRIPT LANGUAGE='JavaScript'>
			window.location='users.php?page=user-avatar';
			</script>
			";
		} else {
			echo "
			<SCRIPT LANGUAGE='JavaScript'>
			window.location='profile.php?page=user-avatar';
			</script>
			";
		}
	}
}

function avatars_plug_pages() {
	global $wpdb, $enable_main_blog_avatar;
	if ($wpdb->blogid == '1'){
		if (strtolower($enable_main_blog_avatar) == 'yes'){
			add_submenu_page('options-general.php', 'Blog Avatar', __('Blog Avatar'), 'manage_options', 'blog-avatar', 'avatars_page_edit_blog_avatar' );
		}
	} else {
		add_submenu_page('options-general.php', 'Blog Avatar', __('Blog Avatar'), 'manage_options', 'blog-avatar', 'avatars_page_edit_blog_avatar' );
	}
	if ( current_user_can('manage_options') ) {
		add_submenu_page('users.php', 'Your Avatar', __('Your Avatar'), 'read', 'user-avatar', 'avatars_page_edit_user_avatar' );	
	} else {
		add_submenu_page('profile.php', 'Your Avatar', __('Your Avatar'), 'read', 'user-avatar', 'avatars_page_edit_user_avatar' );	
	}
	if ( is_site_admin() && $_GET['page'] == 'edit-user-avatar' ) {
		add_submenu_page('ms-admin.php', 'Edit User Avatar', __('Edit User Avatar'), 'read', 'edit-user-avatar', 'avatars_page_site_admin_edit_user_avatar' );
	}
}

function avatars_plug_scripts() {
	// the cropper tool didn't seem to care for the enqueue feature so it's loaded directly.
	?>
    <script type='text/javascript' src='<?php echo get_option('siteurl'); ?>/wp-includes/js/crop/cropper.js'></script>
	<script type="text/javascript">
    
        function onEndCrop( coords, dimensions ) {
            $( 'x1' ).value = coords.x1;
            $( 'y1' ).value = coords.y1;
            $( 'x2' ).value = coords.x2;
            $( 'y2' ).value = coords.y2;
            $( 'width' ).value = dimensions.width;
            $( 'height' ).value = dimensions.height;
        }
    
        // with a supplied ratio
        Event.observe(
            window,
            'load',
            function() {
                new Cropper.Img(
                    'upload',
                    {
                        ratioDim: { x: 128, y: 128 },
                        displayOnInit: true,
                        onEndCrop: onEndCrop
                    }
                )
            }
        );
    
    </script>
	<?php
}
function avatars_enqueue_scripts() {
	wp_enqueue_script('scriptaculous');
	wp_enqueue_script('scriptaculous-root');
	wp_enqueue_script('scriptaculous-builder');
	wp_enqueue_script('scriptaculous-dragdrop');
	wp_enqueue_script('prototype');
	//wp_enqueue_script('cropper');
}

function avatars_whitelist($options) {
	$added = array( 'discussion' => array( 'avatar_default' ) );
	$options = add_option_whitelist( $added, $options );
	return $options;
}

function avatars_size_map($size) {
	if ( $size != '16' && $size != '32' && $size != '48' && $size != '96' && $size != '128' ) {
		if ( $size < 25 ) {
			$size = 16;
		} else if ( $size > 24 && $size < 41 ) {
			$size = 32;
		} else if ( $size > 40 && $size < 73 ) {
			$size = 48;
		} else if ( $size > 72 && $size < 113 ) {
			$size = 96;
		} else if ( $size > 112 ) {
			$size = 128;
		}
	}
	return $size;
}

//------------------------------------------------------------------------//
//---Output Functions-----------------------------------------------------//
//------------------------------------------------------------------------//

function get_avatar( $id_or_email, $size = '96', $default = '' ) {
	global $current_site, $default_avatar_rating, $wpdb, $user_avatars_path, $default_user_avatar, $current_site, $local_default_avatar_url;
	//if ( ! get_option('show_avatars') )
	//	return false;

	if ( !is_numeric($size) ) {
		$size = '96';
	}
	$size = avatars_size_map($size);
		
	$email = '';
	if ( is_numeric($id_or_email) ) {
		$id = (int) $id_or_email;
		$user = get_userdata($id);
		if ( $user )
			$email = $user->user_email;
	} elseif ( is_object($id_or_email) ) {
		if ( !empty($id_or_email->user_id) ) {
			$id = (int) $id_or_email->user_id;
			$user = get_userdata($id);
			if ( $user)
				$email = $user->user_email;
		} elseif ( !empty($id_or_email->comment_author_email) ) {
			$email = $id_or_email->comment_author_email;
		}
	} else {
		$email = $id_or_email;
	}
	if ( empty($default) ) {
		$default = get_option('avatar_default');
		if ( empty($default) ) {
			$default = $default_user_avatar;
		}
	}
	
	if ( $default == 'local_default' ) {
		$default = $local_default_avatar_url . $size . '.png';
	} else if ( $default == 'gravatar_default' ) {
		$default = 'http://www.gravatar.com/avatar/' . md5($email) . '?r=G&s=' . $size;
	} else if ( $default == 'identicon' ) {
		$default = 'http://www.gravatar.com/avatar/' . md5($email) . '?r=G&d=identicon&s=' . $size;
	} else if ( $default == 'wavatar' ) {
		$default = 'http://www.gravatar.com/avatar/' . md5($email) . '?r=G&d=wavatar&s=' . $size;
	} else if ( $default == 'monsterid' ) {
		$default = 'http://www.gravatar.com/avatar/' . md5($email) . '?r=G&d=monsterid&s=' . $size;
	} else {	
		$default = $local_default_avatar_url . $size . '.png';
	}
	
	if ( empty($default_avatar_rating) ) {
		$rating = get_option('avatar_rating');
	} else {
		$rating = $default_avatar_rating;
	}

	if ( !empty($id) ) {
		//user exists locally - check if avatar exists
		$file = ABSPATH . $user_avatars_path . substr(md5($id), 0, 3) . '/user-' . $id . '-' . $size . '.png';
		if ( is_file( $file ) ) {
			if ( $_GET['page'] == 'user-avatar' || $_GET['page'] == 'blog-avatar' || $_GET['page'] == 'edit-user-avatar' || $_GET['page'] == 'edit-blog-avatar') {
				$path = 'http://' . $current_site->domain . $current_site->path . 'avatar/user-' . $id . '-' . $size . '.png?rand=' . md5(time());
			} else {
				$path = 'http://' . $current_site->domain . $current_site->path . 'avatar/user-' . $id . '-' . $size . '.png';
			}
		} else {
			$path = $default;
		}
		$avatar = "<img alt='' src='{$path}' class='avatar avatar-{$size}' height='{$size}' width='{$size}' />";
	} else if ( !empty($email) ) {
		if ( avatar_email_exists($email) ) {
			//email exists locally - check if avatar exists
		 	$avatar_user_id = $wpdb->get_var("SELECT ID FROM $wpdb->users WHERE user_email = '" . $email . "'" );
			$file = ABSPATH . $user_avatars_path . substr(md5($avatar_user_id), 0, 3) . '/user-' . $avatar_user_id . '-' . $size . '.png';
			if ( is_file( $file ) ) {
			if ( $_GET['page'] == 'user-avatar' ) {
				$path = 'http://' . $current_site->domain . $current_site->path . 'avatar/user-' . $avatar_user_id . '-' . $size . '.png?rand=' . md5(time());
			} else {
				$path = 'http://' . $current_site->domain . $current_site->path . 'avatar/user-' . $avatar_user_id . '-' . $size . '.png';
			}
			} else {
				$path = $default;
			}
		} else {
			//email does not exist locally - get gravatar
			$path = 'http://www.gravatar.com/avatar/';
			$path .= md5( strtolower( $email ) );
			$path .= '?s='.$size;
			$path .= '&amp;d=' . urlencode( $default );
			$path .= '&amp;r=' . $rating;
		}
		$avatar = "<img alt='' src='{$path}' class='avatar avatar-{$size}' height='{$size}' width='{$size}' />";
	} else {
		$avatar = "<img alt='' src='{$default}' class='avatar avatar-{$size} avatar-default' height='{$size}' width='{$size}' />";
	}
	return $avatar;
}

function get_blog_avatar( $id, $size = '96', $default = '' ) {
	global $current_site, $wpdb, $blog_avatars_path, $default_blog_avatar, $current_site, $local_default_avatar_url;

	if ( !is_numeric($size) ) {
		$size = '96';
	}
	$size = avatars_size_map($size);
	if ( empty($default) ) {
		$default = get_option('avatar_default');
		if ( empty($default) ) {
			$default = $default_blog_avatar;
		}
	}
	if ( $default == 'local_default' ) {
		$default = $local_default_avatar_url . $size . '.png';
	} else if ( $default == 'gravatar_default' ) {
		$default = 'http://www.gravatar.com/avatar/' . md5($id) . '?r=G&s=' . $size;
	} else if ( $default == 'identicon' ) {
		$default = 'http://www.gravatar.com/avatar/' . md5($id) . '?r=G&d=identicon&s=' . $size;
	} else if ( $default == 'wavatar' ) {
		$default = 'http://www.gravatar.com/avatar/' . md5($id) . '?r=G&d=wavatar&s=' . $size;
	} else if ( $default == 'monsterid' ) {
		$default = 'http://www.gravatar.com/avatar/' . md5($id) . '?r=G&d=monsterid&s=' . $size;
	} else {	
		$default = $local_default_avatar_url . $size . '.png';
	}

	if ( !empty($id) ) {
		//user exists locally - check if avatar exists
		$file = ABSPATH . $blog_avatars_path . substr(md5($id), 0, 3) . '/blog-' . $id . '-' . $size . '.png';
		if ( is_file( $file ) ) {
			if ( $_GET['page'] == 'blog-avatar' || $_GET['page'] == 'edit-blog-avatar' ) {
				$path = 'http://' . $current_site->domain . $current_site->path . 'avatar/blog-' . $id . '-' . $size . '.png?rand=' . md5(time());
			} else {
				$path = 'http://' . $current_site->domain . $current_site->path . 'avatar/blog-' . $id . '-' . $size . '.png';
			}
		} else {
			$path = $default;
		}
		$avatar = "<img alt='' src='{$path}' class='avatar avatar-{$size}' height='{$size}' width='{$size}' />";
	} else {
		$avatar = "<img alt='' src='{$default}' class='avatar avatar-{$size} avatar-default' height='{$size}' width='{$size}' />";
	}
	return $avatar;
}

//------------------------------------------------------------------------//
//---Page Output Functions------------------------------------------------//
//------------------------------------------------------------------------//

function avatars_page_edit_blog_avatar() {
	global $wpdb, $user_ID, $current_site, $blog_avatars_path;

	if(!current_user_can('manage_options')) {
		echo "<p>" . __('Nice Try...') . "</p>";  //If accessed properly, this message doesn't appear.
		return;
	}
	/*
	if (isset($_GET['updated'])) {
		?>
        <div style="background-color: rgb(255, 251, 204);" id="message" class="updated fade">
            <p>
                <strong>
                <?php _e('' . urldecode($_GET['updatedmsg']) . '') ?>
                </strong>
            </p>
        </div>
		<?php
	}
	*/
	echo '<div class="wrap">';
	switch( $_GET[ 'action' ] ) {
		//---------------------------------------------------//
		default:
		?>
			<h2><?php _e('Blog Avatar') ?></h2>
			<form action="options-general.php?page=blog-avatar&action=upload_process" method="post" enctype="multipart/form-data">
			<p>
            <p><?php _e('This is your "blog" avatar. It will appear whenever your blog is listed (for example, on the front page of the site).'); ?></p>
            <?php
			echo get_blog_avatar($wpdb->blogid,'96','');
			?>
			</p>
			<h3><?php _e('Upload New Avatar'); ?></h3>
			<p>
			  <input name="avatar_file" id="avatar_file" size="20" type="file">
			  <input type="hidden" name="MAX_FILE_SIZE" value="100000" />
			</p>
			<p><?php _e('Allowed Formats:jpeg, gif, and png'); ?></p>
			<p><?php _e('If you are experiencing problems cropping your image please use the alternative upload method ("Alternative Upload" button).'); ?></p>
			<p class="submit">
			  <input name="Submit" value="<?php _e('Upload') ?>" type="submit">
			  <input name="Alternative" value="<?php _e('Alternative Upload') ?>" type="submit">
			  <input name="Reset" value="<?php _e('Reset') ?>" type="submit">
			</p>
			</form>
		<?php
		break;
		//---------------------------------------------------//
		case "upload_process":
			if ( isset( $_POST['Reset'] ) ) {
				$avatar_path = ABSPATH . $blog_avatars_path . substr(md5($wpdb->blogid), 0, 3) . '/';
				avatars_delete_temp($avatar_path . 'blog-' . $wpdb->blogid . '-16.png');
				avatars_delete_temp($avatar_path . 'blog-' . $wpdb->blogid . '-32.png');
				avatars_delete_temp($avatar_path . 'blog-' . $wpdb->blogid . '-48.png');
				avatars_delete_temp($avatar_path . 'blog-' . $wpdb->blogid . '-96.png');
				avatars_delete_temp($avatar_path . 'blog-' . $wpdb->blogid . '-128.png');
				/*
				echo "
				<SCRIPT LANGUAGE='JavaScript'>
				window.location='options-general.php?page=blog-avatar&updated=true&updatedmsg=" . urlencode(__('Avatar updated.')) . "';
				</script>
				";
				*/
				echo "
				<SCRIPT LANGUAGE='JavaScript'>
				window.location='options-general.php?page=blog-avatar&updated=true';
				</script>
				";
			} else {
				$avatar_path = ABSPATH . $blog_avatars_path . substr(md5($wpdb->blogid), 0, 3) . '/';
		
				if (is_dir($avatar_path)) {
				} else {
					mkdir($avatar_path, 0777);
				}
		
				$image_path = $avatar_path . basename($_FILES['avatar_file']['name']); 
	
				if(move_uploaded_file($_FILES['avatar_file']['tmp_name'], $image_path)) {
					//file uploaded...
					chmod($image_path, 0777);
				} else{
					echo __('There was an error uploading the file, please try again.');
				}
				list($avatar_width, $avatar_height, $avatar_type, $avatar_attr) = getimagesize($image_path);
				
				if ($_FILES['avatar_file']['type'] == "image/gif"){
					$avatar_image_type = 'gif';
				}
				if ($_FILES['avatar_file']['type'] == "image/jpeg"){
					$avatar_image_type = 'jpeg';
				}
				if ($_FILES['avatar_file']['type'] == "image/pjpeg"){
					$avatar_image_type = 'jpeg';
				}
				if ($_FILES['avatar_file']['type'] == "image/jpg"){
					$avatar_image_type = 'jpeg';
				}
				if ($_FILES['avatar_file']['type'] == "image/png"){
					$avatar_image_type = 'png';
				}
				if ($_FILES['avatar_file']['type'] == "image/x-png"){
					$avatar_image_type = 'png';
				}
				if ( isset( $_POST['Alternative'] ) ) {
					//Alternative Upload
					if ($avatar_image_type == 'jpeg'){
						$im = ImageCreateFromjpeg($avatar_path . basename( $_FILES['avatar_file']['name']));
					}
					if ($avatar_image_type == 'png'){
						$im = ImageCreateFrompng($avatar_path . basename( $_FILES['avatar_file']['name']));
					}
					if ($avatar_image_type == 'gif'){
						$im = ImageCreateFromgif($avatar_path . basename( $_FILES['avatar_file']['name']));
					}
					
					//----------------------------------------------------------------//
					//----------------------------------------------------------------//
					$im_dest = imagecreatetruecolor (128, 128);
					imagecopyresampled($im_dest, $im, 0, 0, 0, 0, 128, 128, $avatar_width, $avatar_height);
					if ($_POST['image_type'] == 'png'){
						imagesavealpha($im_dest, true);
					}
					imagepng($im_dest, $avatar_path . 'blog-' . $wpdb->blogid . '-128.png');
					//----------------------------------------------------------------//
					//----------------------------------------------------------------//
					$im_dest = imagecreatetruecolor (96, 96);
					imagecopyresampled($im_dest, $im, 0, 0, 0, 0, 96, 96, $avatar_width, $avatar_height);
					if ($_POST['image_type'] == 'png'){
						imagesavealpha($im_dest, true);
					}
					imagepng($im_dest, $avatar_path . 'blog-' . $wpdb->blogid . '-96.png');
					//----------------------------------------------------------------//
					//----------------------------------------------------------------//
					$im_dest = imagecreatetruecolor (48, 48);
					imagecopyresampled($im_dest, $im, 0, 0, 0, 0, 48, 48, $avatar_width, $avatar_height);
					if ($_POST['image_type'] == 'png'){
						imagesavealpha($im_dest, true);
					}
					imagepng($im_dest, $avatar_path . 'blog-' . $wpdb->blogid . '-48.png');
					//----------------------------------------------------------------//
					//----------------------------------------------------------------//
					$im_dest = imagecreatetruecolor (32, 32);
					imagecopyresampled($im_dest, $im, 0, 0, 0, 0, 32, 32, $avatar_width, $avatar_height);
					if ($_POST['image_type'] == 'png'){
						imagesavealpha($im_dest, true);
					}
					imagepng($im_dest, $avatar_path . 'blog-' . $wpdb->blogid . '-32.png');
					//----------------------------------------------------------------//
					//----------------------------------------------------------------//
					$im_dest = imagecreatetruecolor (16, 16);
					imagecopyresampled($im_dest, $im, 0, 0, 0, 0, 16, 16, $avatar_width, $avatar_height);
					if ($_POST['image_type'] == 'png'){
						imagesavealpha($im_dest, true);
					}
					imagepng($im_dest, $avatar_path . 'blog-' . $wpdb->blogid . '-16.png');
					//----------------------------------------------------------------//
					//----------------------------------------------------------------//
					avatars_delete_temp($avatar_path . basename( $_FILES['avatar_file']['name']));
					if ( function_exists( 'moderation_image_insert' ) ) {
						moderation_image_insert('avatar', $wpdb->blogid, $user_ID, $avatar_path . 'blog-' . $wpdb->blogid . '-128.png', 'http://' . $current_site->domain . $current_site->path . 'avatar/blog-' . $wpdb->blogid . '-128.png');
					}
					/*
					echo "
					<SCRIPT LANGUAGE='JavaScript'>
					window.location='options-general.php?page=blog-avatar&updated=true&updatedmsg=" . urlencode(__('Avatar updated.')) . "';
					</script>
					";
					*/
					echo "
					<SCRIPT LANGUAGE='JavaScript'>
					window.location='options-general.php?page=blog-avatar&updated=true';
					</script>
					";
				} else {
					//Standard Upload
					?>
					<h2><?php _e('Crop Image') ?></h2>
					<form method="post" action="options-general.php?page=blog-avatar&action=crop_process">
			
					<p><?php _e('Choose the part of the image you want to use as the avatar.'); ?></p>
					<div id="testWrap">
					<img src="<?php echo '../' . $blog_avatars_path . substr(md5($wpdb->blogid), 0, 3) . '/' . $_FILES['avatar_file']['name']; ?>" id="upload" width="<?php echo $avatar_width; ?>" height="<?php echo $avatar_height; ?>" />
					</div>
					
					<input type="hidden" name="file_path" id="file_path" value="<?php echo $avatar_path; ?>" />
					<input type="hidden" name="file_name" id="file_name" value="<?php echo basename( $_FILES['avatar_file']['name']); ?>" />
					<input type="hidden" name="image_type" id="image_type" value="<?php echo $avatar_image_type; ?>" />
					<input type="hidden" name="x1" id="x1" />
					<input type="hidden" name="y1" id="y1" />
					<input type="hidden" name="x2" id="x2" />
					<input type="hidden" name="y2" id="y2" />
					<input type="hidden" name="width" id="width" />
					<input type="hidden" name="height" id="height" />

					<p class="submit">
					<input type="submit" value="<?php _e('Crop Image'); ?>" />
					</p>
					
					</form>
					<?php
				}
			}
		break;
		//---------------------------------------------------//
		case "crop_process":
			$avatar_path = ABSPATH . $blog_avatars_path . substr(md5($wpdb->blogid), 0, 3) . '/';
	
			if (is_dir($avatar_path)) {
			} else {
				mkdir($avatar_path, 0777);
			}

			if ($_POST['image_type'] == 'jpeg'){
				$im = ImageCreateFromjpeg($_POST['file_path'] . $_POST['file_name']);
			}
			if ($_POST['image_type'] == 'png'){
				$im = ImageCreateFrompng($_POST['file_path'] . $_POST['file_name']);
			}
			if ($_POST['image_type'] == 'gif'){
				$im = ImageCreateFromgif($_POST['file_path'] . $_POST['file_name']);
			}
			
			//----------------------------------------------------------------//
			//----------------------------------------------------------------//
			$im_dest = imagecreatetruecolor (128, 128);
			$avatar_width = $_POST['x2'] - $_POST['x1'];
			$avatar_height = $_POST['y2'] - $_POST['y1'];
			imagecopyresampled($im_dest, $im, 0, 0, $_POST['x1'], $_POST['y1'], 128, 128, $avatar_width, $avatar_height);
			if ($_POST['image_type'] == 'png'){
				imagesavealpha($im_dest, true);
			}
			imagepng($im_dest, $avatar_path . 'blog-' . $wpdb->blogid . '-128.png');
			//----------------------------------------------------------------//
			//----------------------------------------------------------------//
			$im_dest = imagecreatetruecolor (96, 96);
			$avatar_width = $_POST['x2'] - $_POST['x1'];
			$avatar_height = $_POST['y2'] - $_POST['y1'];
			imagecopyresampled($im_dest, $im, 0, 0, $_POST['x1'], $_POST['y1'], 96, 96, $avatar_width, $avatar_height);
			if ($_POST['image_type'] == 'png'){
				imagesavealpha($im_dest, true);
			}
			imagepng($im_dest, $avatar_path . 'blog-' . $wpdb->blogid . '-96.png');
			//----------------------------------------------------------------//
			//----------------------------------------------------------------//
			$im_dest = imagecreatetruecolor (48, 48);
			$avatar_width = $_POST['x2'] - $_POST['x1'];
			$avatar_height = $_POST['y2'] - $_POST['y1'];
			imagecopyresampled($im_dest, $im, 0, 0, $_POST['x1'], $_POST['y1'], 48, 48, $avatar_width, $avatar_height);
			if ($_POST['image_type'] == 'png'){
				imagesavealpha($im_dest, true);
			}
			imagepng($im_dest, $avatar_path . 'blog-' . $wpdb->blogid . '-48.png');
			//----------------------------------------------------------------//
			//----------------------------------------------------------------//
			$im_dest = imagecreatetruecolor (32, 32);
			$avatar_width = $_POST['x2'] - $_POST['x1'];
			$avatar_height = $_POST['y2'] - $_POST['y1'];
			imagecopyresampled($im_dest, $im, 0, 0, $_POST['x1'], $_POST['y1'], 32, 32, $avatar_width, $avatar_height);
			if ($_POST['image_type'] == 'png'){
				imagesavealpha($im_dest, true);
			}
			imagepng($im_dest, $avatar_path . 'blog-' . $wpdb->blogid . '-32.png');
			//----------------------------------------------------------------//
			//----------------------------------------------------------------//
			$im_dest = imagecreatetruecolor (16, 16);
			$avatar_width = $_POST['x2'] - $_POST['x1'];
			$avatar_height = $_POST['y2'] - $_POST['y1'];
			imagecopyresampled($im_dest, $im, 0, 0, $_POST['x1'], $_POST['y1'], 16, 16, $avatar_width, $avatar_height);
			if ($_POST['image_type'] == 'png'){
				imagesavealpha($im_dest, true);
			}
			imagepng($im_dest, $avatar_path . 'blog-' . $wpdb->blogid . '-16.png');
			//----------------------------------------------------------------//
			//----------------------------------------------------------------//
			avatars_delete_temp($_POST['file_path'] . $_POST['file_name']);
			if ( function_exists( 'moderation_image_insert' ) ) {
				moderation_image_insert('avatar', $wpdb->blogid, $user_ID, $avatar_path . 'blog-' . $wpdb->blogid . '-128.png', 'http://' . $current_site->domain . $current_site->path . 'avatar/blog-' . $wpdb->blogid . '-128.png');
			}
			/*
			echo "
			<SCRIPT LANGUAGE='JavaScript'>
			window.location='options-general.php?page=blog-avatar&updated=true&updatedmsg=" . urlencode(__('Avatar updated.')) . "';
			</script>
			";
			*/
			echo "
			<SCRIPT LANGUAGE='JavaScript'>
			window.location='options-general.php?page=blog-avatar&updated=true';
			</script>
			";
		break;
		//---------------------------------------------------//
	}
	echo '</div>';
}

function avatars_page_edit_user_avatar() {
	global $wpdb, $user_avatars_path, $user_ID, $current_site;

	if (isset($_GET['updated'])) {
		?>
        <div style="background-color: rgb(255, 251, 204);" id="message" class="updated fade">
            <p>
                <strong>
                <?php _e('' . urldecode($_GET['updatedmsg']) . '') ?>
                </strong>
            </p>
        </div>
		<?php
	}
	echo '<div class="wrap">';
	switch( $_GET[ 'action' ] ) {
		//---------------------------------------------------//
		default:
		?>
			<h2><?php _e('Your Avatar') ?></h2>
            <?php
			if ( current_user_can('manage_options') ) {
				?> 
				<form action="users.php?page=user-avatar&action=upload_process" method="post" enctype="multipart/form-data">
				<?php
            } else {
				?>
				<form action="profile.php?page=user-avatar&action=upload_process" method="post" enctype="multipart/form-data">
				<?php
            }
			?>
			<p><?php _e('This is your "user" avatar. It will appear whenever you leave comments, post in the forums and when your popular posts are displayed around the site.'); ?></p>
			<p>
            <?php
			echo get_avatar($user_ID,'96',get_option('avatar_default'));
			?>
			</p>
			<h3><?php _e('Upload New Avatar'); ?></h3			
			><p>
			  <input name="avatar_file" id="avatar_file" size="20" type="file">
			  <input type="hidden" name="MAX_FILE_SIZE" value="100000" />
			</p>
			<p><?php _e('Allowed Formats:jpeg, gif, and png'); ?></p>
			<p><?php _e('If you are experiencing problems cropping your image please use the alternative upload method ("Alternative Upload" button).'); ?></p>
			<p class="submit">
			  <input name="Submit" value="<?php _e('Upload') ?>" type="submit">
			  <input name="Alternative" value="<?php _e('Alternative Upload') ?>" type="submit">
			  <input name="Reset" value="<?php _e('Reset') ?>" type="submit">
			</p>
			</form>
		<?php
		break;
		//---------------------------------------------------//
		case "upload_process":
			if ( isset( $_POST['Reset'] ) ) {
				$avatar_path = ABSPATH . $user_avatars_path . substr(md5($user_ID), 0, 3) . '/';
				avatars_delete_temp($avatar_path . 'user-' . $user_ID . '-16.png');
				avatars_delete_temp($avatar_path . 'user-' . $user_ID . '-32.png');
				avatars_delete_temp($avatar_path . 'user-' . $user_ID . '-48.png');
				avatars_delete_temp($avatar_path . 'user-' . $user_ID . '-96.png');
				avatars_delete_temp($avatar_path . 'user-' . $user_ID . '-128.png');
				if ( current_user_can('manage_options') ) {
					echo "
					<SCRIPT LANGUAGE='JavaScript'>
					window.location='users.php?page=user-avatar&updated=true&updatedmsg=" . urlencode(__('Avatar reset.')) . "';
					</script>
					";
				} else {
					echo "
					<SCRIPT LANGUAGE='JavaScript'>
					window.location='profile.php?page=user-avatar&updated=true&updatedmsg=" . urlencode(__('Avatar reset.')) . "';
					</script>
					";
				}
			} else {
				$avatar_path = ABSPATH . $user_avatars_path . substr(md5($user_ID), 0, 3) . '/';
		
				if (is_dir($avatar_path)) {
				} else {
					mkdir($avatar_path, 0777);
				}
		
				$image_path = $avatar_path . basename($_FILES['avatar_file']['name']); 
	
				if(move_uploaded_file($_FILES['avatar_file']['tmp_name'], $image_path)) {
					//file uploaded...
					chmod($image_path, 0777);
				} else{
					echo __("There was an error uploading the file, please try again.");
				}
				list($avatar_width, $avatar_height, $avatar_type, $avatar_attr) = getimagesize($image_path);
				
				if ($_FILES['avatar_file']['type'] == "image/gif"){
					$avatar_image_type = 'gif';
				}
				if ($_FILES['avatar_file']['type'] == "image/jpeg"){
					$avatar_image_type = 'jpeg';
				}
				if ($_FILES['avatar_file']['type'] == "image/pjpeg"){
					$avatar_image_type = 'jpeg';
				}
				if ($_FILES['avatar_file']['type'] == "image/jpg"){
					$avatar_image_type = 'jpeg';
				}
				if ($_FILES['avatar_file']['type'] == "image/png"){
					$avatar_image_type = 'png';
				}
				if ($_FILES['avatar_file']['type'] == "image/x-png"){
					$avatar_image_type = 'png';
				}
				if ( isset( $_POST['Alternative'] ) ) {
					//Alternative Upload
					if ($avatar_image_type == 'jpeg'){
						$im = ImageCreateFromjpeg($avatar_path . basename( $_FILES['avatar_file']['name']));
					}
					if ($avatar_image_type == 'png'){
						$im = ImageCreateFrompng($avatar_path . basename( $_FILES['avatar_file']['name']));
					}
					if ($avatar_image_type == 'gif'){
						$im = ImageCreateFromgif($avatar_path . basename( $_FILES['avatar_file']['name']));
					}
					
					//----------------------------------------------------------------//
					//----------------------------------------------------------------//
					$im_dest = imagecreatetruecolor (128, 128);
					imagecopyresampled($im_dest, $im, 0, 0, 0, 0, 128, 128, $avatar_width, $avatar_height);
					if ($_POST['image_type'] == 'png'){
						imagesavealpha($im_dest, true);
					}
					imagepng($im_dest, $avatar_path . 'user-' . $user_ID . '-128.png');
					//----------------------------------------------------------------//
					//----------------------------------------------------------------//
					$im_dest = imagecreatetruecolor (96, 96);
					imagecopyresampled($im_dest, $im, 0, 0, 0, 0, 96, 96, $avatar_width, $avatar_height);
					if ($_POST['image_type'] == 'png'){
						imagesavealpha($im_dest, true);
					}
					imagepng($im_dest, $avatar_path . 'user-' . $user_ID . '-96.png');
					//----------------------------------------------------------------//
					//----------------------------------------------------------------//
					$im_dest = imagecreatetruecolor (48, 48);
					imagecopyresampled($im_dest, $im, 0, 0, 0, 0, 48, 48, $avatar_width, $avatar_height);
					if ($_POST['image_type'] == 'png'){
						imagesavealpha($im_dest, true);
					}
					imagepng($im_dest, $avatar_path . 'user-' . $user_ID . '-48.png');
					//----------------------------------------------------------------//
					//----------------------------------------------------------------//
					$im_dest = imagecreatetruecolor (32, 32);
					imagecopyresampled($im_dest, $im, 0, 0, 0, 0, 32, 32, $avatar_width, $avatar_height);
					if ($_POST['image_type'] == 'png'){
						imagesavealpha($im_dest, true);
					}
					imagepng($im_dest, $avatar_path . 'user-' . $user_ID . '-32.png');
					//----------------------------------------------------------------//
					//----------------------------------------------------------------//
					$im_dest = imagecreatetruecolor (16, 16);
					imagecopyresampled($im_dest, $im, 0, 0, 0, 0, 16, 16, $avatar_width, $avatar_height);
					if ($_POST['image_type'] == 'png'){
						imagesavealpha($im_dest, true);
					}
					imagepng($im_dest, $avatar_path . 'user-' . $user_ID . '-16.png');
					//----------------------------------------------------------------//
					//----------------------------------------------------------------//
					avatars_delete_temp($avatar_path . basename( $_FILES['avatar_file']['name']));
					if ( function_exists( 'moderation_image_insert' ) ) {
						moderation_image_insert('avatar', $wpdb->blogid, $user_ID, $avatar_path . 'user-' . $user_ID . '-128.png', 'http://' . $current_site->domain . $current_site->path . 'avatar/user-' . $user_ID . '-128.png');
					}
					if ( current_user_can('manage_options') ) {
						echo "
						<SCRIPT LANGUAGE='JavaScript'>
						window.location='users.php?page=user-avatar&updated=true&updatedmsg=" . urlencode(__('Avatar updated.')) . "';
						</script>
						";
					} else {
						echo "
						<SCRIPT LANGUAGE='JavaScript'>
						window.location='profile.php?page=user-avatar&updated=true&updatedmsg=" . urlencode(__('Avatar updated.')) . "';
						</script>
						";
					}
				} else {
					//Standard Upload
					?>
					<h2><?php _e('Crop Image') ?></h2>
					<?php
					if ( current_user_can('manage_options') ) {
						?> 
						<form method="post" action="users.php?page=user-avatar&action=crop_process">
						<?php
					} else {
						?>
						<form method="post" action="profile.php?page=user-avatar&action=crop_process">
						<?php
					}
					?>  
			
					<p><?php _e('Choose the part of the image you want to use as the avatar.'); ?></p>
					<div id="testWrap">
					<img src="<?php echo '../' . $user_avatars_path . substr(md5($user_ID), 0, 3) . '/' . $_FILES['avatar_file']['name']; ?>" id="upload" width="<?php echo $avatar_width; ?>" height="<?php echo $avatar_height; ?>" />
					</div>
					
					<input type="hidden" name="file_path" id="file_path" value="<?php echo $avatar_path; ?>" />
					<input type="hidden" name="file_name" id="file_name" value="<?php echo basename( $_FILES['avatar_file']['name']); ?>" />
					<input type="hidden" name="image_type" id="image_type" value="<?php echo $avatar_image_type; ?>" />
					<input type="hidden" name="x1" id="x1" />
					<input type="hidden" name="y1" id="y1" />
					<input type="hidden" name="x2" id="x2" />
					<input type="hidden" name="y2" id="y2" />
					<input type="hidden" name="width" id="width" />
					<input type="hidden" name="height" id="height" />

					<p class="submit">
					<input type="submit" value="<?php _e('Crop Image'); ?>" />
					</p>
                    
					</form>
					<?php
				}
			}
		break;
		//---------------------------------------------------//
		case "crop_process":
			$avatar_path = ABSPATH . $user_avatars_path . substr(md5($user_ID), 0, 3) . '/';
	
			if (is_dir($avatar_path)) {
			} else {
				mkdir($avatar_path, 0777);
			}

			if ($_POST['image_type'] == 'jpeg'){
				$im = ImageCreateFromjpeg($_POST['file_path'] . $_POST['file_name']);
			}
			if ($_POST['image_type'] == 'png'){
				$im = ImageCreateFrompng($_POST['file_path'] . $_POST['file_name']);
			}
			if ($_POST['image_type'] == 'gif'){
				$im = ImageCreateFromgif($_POST['file_path'] . $_POST['file_name']);
			}
			
			//----------------------------------------------------------------//
			//----------------------------------------------------------------//
			$im_dest = imagecreatetruecolor (128, 128);
			$avatar_width = $_POST['x2'] - $_POST['x1'];
			$avatar_height = $_POST['y2'] - $_POST['y1'];
			imagecopyresampled($im_dest, $im, 0, 0, $_POST['x1'], $_POST['y1'], 128, 128, $avatar_width, $avatar_height);
			if ($_POST['image_type'] == 'png'){
				imagesavealpha($im_dest, true);
			}
			imagepng($im_dest, $avatar_path . 'user-' . $user_ID . '-128.png');
			//----------------------------------------------------------------//
			//----------------------------------------------------------------//
			$im_dest = imagecreatetruecolor (96, 96);
			$avatar_width = $_POST['x2'] - $_POST['x1'];
			$avatar_height = $_POST['y2'] - $_POST['y1'];
			imagecopyresampled($im_dest, $im, 0, 0, $_POST['x1'], $_POST['y1'], 96, 96, $avatar_width, $avatar_height);
			if ($_POST['image_type'] == 'png'){
				imagesavealpha($im_dest, true);
			}
			imagepng($im_dest, $avatar_path . 'user-' . $user_ID . '-96.png');
			//----------------------------------------------------------------//
			//----------------------------------------------------------------//
			$im_dest = imagecreatetruecolor (48, 48);
			$avatar_width = $_POST['x2'] - $_POST['x1'];
			$avatar_height = $_POST['y2'] - $_POST['y1'];
			imagecopyresampled($im_dest, $im, 0, 0, $_POST['x1'], $_POST['y1'], 48, 48, $avatar_width, $avatar_height);
			if ($_POST['image_type'] == 'png'){
				imagesavealpha($im_dest, true);
			}
			imagepng($im_dest, $avatar_path . 'user-' . $user_ID . '-48.png');
			//----------------------------------------------------------------//
			//----------------------------------------------------------------//
			$im_dest = imagecreatetruecolor (32, 32);
			$avatar_width = $_POST['x2'] - $_POST['x1'];
			$avatar_height = $_POST['y2'] - $_POST['y1'];
			imagecopyresampled($im_dest, $im, 0, 0, $_POST['x1'], $_POST['y1'], 32, 32, $avatar_width, $avatar_height);
			if ($_POST['image_type'] == 'png'){
				imagesavealpha($im_dest, true);
			}
			imagepng($im_dest, $avatar_path . 'user-' . $user_ID . '-32.png');
			//----------------------------------------------------------------//
			//----------------------------------------------------------------//
			$im_dest = imagecreatetruecolor (16, 16);
			$avatar_width = $_POST['x2'] - $_POST['x1'];
			$avatar_height = $_POST['y2'] - $_POST['y1'];
			imagecopyresampled($im_dest, $im, 0, 0, $_POST['x1'], $_POST['y1'], 16, 16, $avatar_width, $avatar_height);
			if ($_POST['image_type'] == 'png'){
				imagesavealpha($im_dest, true);
			}
			imagepng($im_dest, $avatar_path . 'user-' . $user_ID . '-16.png');
			//----------------------------------------------------------------//
			//----------------------------------------------------------------//
			avatars_delete_temp($_POST['file_path'] . $_POST['file_name']);
			if ( function_exists( 'moderation_image_insert' ) ) {
				moderation_image_insert('avatar', $wpdb->blogid, $user_ID, $avatar_path . 'user-' . $user_ID . '-128.png', 'http://' . $current_site->domain . $current_site->path . 'avatar/user-' . $user_ID . '-128.png');
			}
			if ( current_user_can('manage_options') ) {
				echo "
				<SCRIPT LANGUAGE='JavaScript'>
				window.location='users.php?page=user-avatar&updated=true&updatedmsg=" . urlencode(__('Avatar updated.')) . "';
				</script>
				";
			} else {
				echo "
				<SCRIPT LANGUAGE='JavaScript'>
				window.location='profile.php?page=user-avatar&updated=true&updatedmsg=" . urlencode(__('Avatar updated.')) . "';
				</script>
				";
			}
		break;
		//---------------------------------------------------//
	}
	echo '</div>';
}

function avatars_page_site_admin_edit_user_avatar() {
	global $wpdb, $user_avatars_path;

	if (isset($_GET['updated'])) {
		?>
        <div style="background-color: rgb(255, 251, 204);" id="message" class="updated fade">
            <p>
                <strong>
                <?php _e('' . urldecode($_GET['updatedmsg']) . '') ?>
                </strong>
            </p>
        </div>
		<?php
	}
	echo '<div class="wrap">';
	switch( $_GET[ 'action' ] ) {
		//---------------------------------------------------//
		default:
		?>
			<h2><?php _e('User Avatar') ?></h2>
			<form action="ms-admin.php?page=edit-user-avatar&action=upload_process&uid=<?php echo $_GET['uid']; ?>" method="post" enctype="multipart/form-data">
			<p>
            <?php
			echo get_avatar($_GET['uid'],'96',get_option('avatar_default'));
			?>
			</p>
			<h3><?php _e('Upload New Avatar'); ?></h3			
			><p>
			  <input name="avatar_file" id="avatar_file" size="20" type="file">
			  <input type="hidden" name="MAX_FILE_SIZE" value="100000" />
			</p>
			<p><?php _e('Allowed Formats:jpeg, gif, and png'); ?></p>
			<p><?php _e('If you are experiencing problems cropping the image please use the alternative upload method ("Alternative Upload" button).'); ?></p>
			<p class="submit">
			  <input name="Submit" value="<?php _e('Upload') ?>" type="submit">
			  <input name="Alternative" value="<?php _e('Alternative Upload') ?>" type="submit">
			  <input name="Reset" value="<?php _e('Reset') ?>" type="submit">
			</p>
			</form>
		<?php
		break;
		//---------------------------------------------------//
		case "upload_process":
			if ( isset( $_POST['Reset'] ) ) {
				$avatar_path = ABSPATH . $user_avatars_path . substr(md5($_GET['uid']), 0, 3) . '/';
				avatars_delete_temp($avatar_path . 'user-' . $_GET['uid'] . '-16.png');
				avatars_delete_temp($avatar_path . 'user-' . $_GET['uid'] . '-32.png');
				avatars_delete_temp($avatar_path . 'user-' . $_GET['uid'] . '-48.png');
				avatars_delete_temp($avatar_path . 'user-' . $_GET['uid'] . '-96.png');
				avatars_delete_temp($avatar_path . 'user-' . $_GET['uid'] . '-128.png');
				echo "
				<SCRIPT LANGUAGE='JavaScript'>
				window.location='ms-admin.php?page=edit-user-avatar&uid=" . $_GET['uid'] . "&updated=true&updatedmsg=" . urlencode(__('Avatar reset.')) . "';
				</script>
				";
			} else {
				$avatar_path = ABSPATH . $user_avatars_path . substr(md5($_GET['uid']), 0, 3) . '/';
		
				if (is_dir($avatar_path)) {
				} else {
					mkdir($avatar_path, 0777);
				}
		
				$image_path = $avatar_path . basename($_FILES['avatar_file']['name']); 
	
				if(move_uploaded_file($_FILES['avatar_file']['tmp_name'], $image_path)) {
					//file uploaded...
					chmod($image_path, 0777);
				} else{
					echo __("There was an error uploading the file, please try again.");
				}
				list($avatar_width, $avatar_height, $avatar_type, $avatar_attr) = getimagesize($image_path);
				
				if ($_FILES['avatar_file']['type'] == "image/gif"){
					$avatar_image_type = 'gif';
				}
				if ($_FILES['avatar_file']['type'] == "image/jpeg"){
					$avatar_image_type = 'jpeg';
				}
				if ($_FILES['avatar_file']['type'] == "image/pjpeg"){
					$avatar_image_type = 'jpeg';
				}
				if ($_FILES['avatar_file']['type'] == "image/jpg"){
					$avatar_image_type = 'jpeg';
				}
				if ($_FILES['avatar_file']['type'] == "image/png"){
					$avatar_image_type = 'png';
				}
				if ($_FILES['avatar_file']['type'] == "image/x-png"){
					$avatar_image_type = 'png';
				}
				if ( isset( $_POST['Alternative'] ) ) {
					//Alternative Upload
					if ($avatar_image_type == 'jpeg'){
						$im = ImageCreateFromjpeg($avatar_path . basename( $_FILES['avatar_file']['name']));
					}
					if ($avatar_image_type == 'png'){
						$im = ImageCreateFrompng($avatar_path . basename( $_FILES['avatar_file']['name']));
					}
					if ($avatar_image_type == 'gif'){
						$im = ImageCreateFromgif($avatar_path . basename( $_FILES['avatar_file']['name']));
					}
					
					//----------------------------------------------------------------//
					//----------------------------------------------------------------//
					$im_dest = imagecreatetruecolor (128, 128);
					imagecopyresampled($im_dest, $im, 0, 0, 0, 0, 128, 128, $avatar_width, $avatar_height);
					if ($_POST['image_type'] == 'png'){
						imagesavealpha($im_dest, true);
					}
					imagepng($im_dest, $avatar_path . 'user-' . $_GET['uid'] . '-128.png');
					//----------------------------------------------------------------//
					//----------------------------------------------------------------//
					$im_dest = imagecreatetruecolor (96, 96);
					imagecopyresampled($im_dest, $im, 0, 0, 0, 0, 96, 96, $avatar_width, $avatar_height);
					if ($_POST['image_type'] == 'png'){
						imagesavealpha($im_dest, true);
					}
					imagepng($im_dest, $avatar_path . 'user-' . $_GET['uid'] . '-96.png');
					//----------------------------------------------------------------//
					//----------------------------------------------------------------//
					$im_dest = imagecreatetruecolor (48, 48);
					imagecopyresampled($im_dest, $im, 0, 0, 0, 0, 48, 48, $avatar_width, $avatar_height);
					if ($_POST['image_type'] == 'png'){
						imagesavealpha($im_dest, true);
					}
					imagepng($im_dest, $avatar_path . 'user-' . $_GET['uid'] . '-48.png');
					//----------------------------------------------------------------//
					//----------------------------------------------------------------//
					$im_dest = imagecreatetruecolor (32, 32);
					imagecopyresampled($im_dest, $im, 0, 0, 0, 0, 32, 32, $avatar_width, $avatar_height);
					if ($_POST['image_type'] == 'png'){
						imagesavealpha($im_dest, true);
					}
					imagepng($im_dest, $avatar_path . 'user-' . $_GET['uid'] . '-32.png');
					//----------------------------------------------------------------//
					//----------------------------------------------------------------//
					$im_dest = imagecreatetruecolor (16, 16);
					imagecopyresampled($im_dest, $im, 0, 0, 0, 0, 16, 16, $avatar_width, $avatar_height);
					if ($_POST['image_type'] == 'png'){
						imagesavealpha($im_dest, true);
					}
					imagepng($im_dest, $avatar_path . 'user-' . $_GET['uid'] . '-16.png');
					//----------------------------------------------------------------//
					//----------------------------------------------------------------//
					avatars_delete_temp($avatar_path . basename( $_FILES['avatar_file']['name']));
					echo "
					<SCRIPT LANGUAGE='JavaScript'>
					window.location='ms-admin.php?page=edit-user-avatar&uid=" . $_GET['uid'] . "&updated=true&updatedmsg=" . urlencode(__('Avatar updated.')) . "';
					</script>
					";
				} else {
					//Standard Upload
					?>
					<h2><?php _e('Crop Image') ?></h2>
					<form method="post" action="ms-admin.php?page=edit-user-avatar&uid=<?php echo $_GET['uid']; ?>&action=crop_process">
			
					<p><?php _e('Choose the part of the image you want to use as the avatar.'); ?></p>
					<div id="testWrap">
					<img src="<?php echo '../' . $user_avatars_path . substr(md5($_GET['uid']), 0, 3) . '/' . $_FILES['avatar_file']['name']; ?>" id="upload" width="<?php echo $avatar_width; ?>" height="<?php echo $avatar_height; ?>" />
					</div>
					
					<input type="hidden" name="file_path" id="file_path" value="<?php echo $avatar_path; ?>" />
					<input type="hidden" name="file_name" id="file_name" value="<?php echo basename( $_FILES['avatar_file']['name']); ?>" />
					<input type="hidden" name="image_type" id="image_type" value="<?php echo $avatar_image_type; ?>" />
					<input type="hidden" name="x1" id="x1" />
					<input type="hidden" name="y1" id="y1" />
					<input type="hidden" name="x2" id="x2" />
					<input type="hidden" name="y2" id="y2" />
					<input type="hidden" name="width" id="width" />
					<input type="hidden" name="height" id="height" />

					<p class="submit">
					<input type="submit" value="<?php _e('Crop Image'); ?>" />
					</p>
                    
					</form>
					<?php
				}
			}
		break;
		//---------------------------------------------------//
		case "crop_process":
			$avatar_path = ABSPATH . $user_avatars_path . substr(md5($_GET['uid']), 0, 3) . '/';
	
			if (is_dir($avatar_path)) {
			} else {
				mkdir($avatar_path, 0777);
			}

			if ($_POST['image_type'] == 'jpeg'){
				$im = ImageCreateFromjpeg($_POST['file_path'] . $_POST['file_name']);
			}
			if ($_POST['image_type'] == 'png'){
				$im = ImageCreateFrompng($_POST['file_path'] . $_POST['file_name']);
			}
			if ($_POST['image_type'] == 'gif'){
				$im = ImageCreateFromgif($_POST['file_path'] . $_POST['file_name']);
			}
			
			//----------------------------------------------------------------//
			//----------------------------------------------------------------//
			$im_dest = imagecreatetruecolor (128, 128);
			$avatar_width = $_POST['x2'] - $_POST['x1'];
			$avatar_height = $_POST['y2'] - $_POST['y1'];
			imagecopyresampled($im_dest, $im, 0, 0, $_POST['x1'], $_POST['y1'], 128, 128, $avatar_width, $avatar_height);
			if ($_POST['image_type'] == 'png'){
				imagesavealpha($im_dest, true);
			}
			imagepng($im_dest, $avatar_path . 'user-' . $_GET['uid'] . '-128.png');
			//----------------------------------------------------------------//
			//----------------------------------------------------------------//
			$im_dest = imagecreatetruecolor (96, 96);
			$avatar_width = $_POST['x2'] - $_POST['x1'];
			$avatar_height = $_POST['y2'] - $_POST['y1'];
			imagecopyresampled($im_dest, $im, 0, 0, $_POST['x1'], $_POST['y1'], 96, 96, $avatar_width, $avatar_height);
			if ($_POST['image_type'] == 'png'){
				imagesavealpha($im_dest, true);
			}
			imagepng($im_dest, $avatar_path . 'user-' . $_GET['uid'] . '-96.png');
			//----------------------------------------------------------------//
			//----------------------------------------------------------------//
			$im_dest = imagecreatetruecolor (48, 48);
			$avatar_width = $_POST['x2'] - $_POST['x1'];
			$avatar_height = $_POST['y2'] - $_POST['y1'];
			imagecopyresampled($im_dest, $im, 0, 0, $_POST['x1'], $_POST['y1'], 48, 48, $avatar_width, $avatar_height);
			if ($_POST['image_type'] == 'png'){
				imagesavealpha($im_dest, true);
			}
			imagepng($im_dest, $avatar_path . 'user-' . $_GET['uid'] . '-48.png');
			//----------------------------------------------------------------//
			//----------------------------------------------------------------//
			$im_dest = imagecreatetruecolor (32, 32);
			$avatar_width = $_POST['x2'] - $_POST['x1'];
			$avatar_height = $_POST['y2'] - $_POST['y1'];
			imagecopyresampled($im_dest, $im, 0, 0, $_POST['x1'], $_POST['y1'], 32, 32, $avatar_width, $avatar_height);
			if ($_POST['image_type'] == 'png'){
				imagesavealpha($im_dest, true);
			}
			imagepng($im_dest, $avatar_path . 'user-' . $_GET['uid'] . '-32.png');
			//----------------------------------------------------------------//
			//----------------------------------------------------------------//
			$im_dest = imagecreatetruecolor (16, 16);
			$avatar_width = $_POST['x2'] - $_POST['x1'];
			$avatar_height = $_POST['y2'] - $_POST['y1'];
			imagecopyresampled($im_dest, $im, 0, 0, $_POST['x1'], $_POST['y1'], 16, 16, $avatar_width, $avatar_height);
			if ($_POST['image_type'] == 'png'){
				imagesavealpha($im_dest, true);
			}
			imagepng($im_dest, $avatar_path . 'user-' . $_GET['uid'] . '-16.png');
			//----------------------------------------------------------------//
			//----------------------------------------------------------------//
			avatars_delete_temp($_POST['file_path'] . $_POST['file_name']);
			echo "
			<SCRIPT LANGUAGE='JavaScript'>
			window.location='ms-admin.php?page=edit-user-avatar&uid=" . $_GET['uid'] . "&updated=true&updatedmsg=" . urlencode(__('Avatar updated.')) . "';
			</script>
			";
		break;
		//---------------------------------------------------//
	}
	echo '</div>';
}


//------------------------------------------------------------------------//
//---Support Functions----------------------------------------------------//
//------------------------------------------------------------------------//

function avatars_delete_temp($file) {
	chmod($file, 0777);
	if(unlink($file))
	{ 
		return true; 
	}else{ 
		return false; 
	} 
}

function avatar_email_exists( $email ) {
	if ( $user = get_user_by_email($email) )
		return $user->ID;

	return false;
}

?>