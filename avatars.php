<?php
/*
Plugin Name: Avatars For Multisite
Plugin URI: http://premium.wpmudev.org/project/avatars
Description: Allows users to upload 'user avatars' and 'blog avatars' which then can appear in comments and blog / user listings around the site
Author: Andrew Billits, Ulrich Sossou (Incsub)
Author URI: http://premium.wpmudev.org/
Version: 3.5.7
Network: true
Text Domain: avatars
WDP ID: 10
*/

/*
Copyright 2007-2011 Incsub (http://incsub.com)

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

if( !is_multisite() )
	exit( __( 'The avatars plugin is only compatible with WordPress Multisite.', 'avatars' ) );

define( 'AVATARS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'AVATARS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) . 'avatars-files/' );
define( 'AVATARS_PLUGIN_URL', plugin_dir_url( __FILE__ ) . 'avatars-files/' );

//------------------------------------------------------------------------//
//---Config---------------------------------------------------------------//
//------------------------------------------------------------------------//
$enable_main_blog_avatar = 'yes'; //Options: 'yes' or 'no'
$avatars_path = 'wp-content/uploads/avatars/';
$blog_avatars_path = $avatars_path . 'blog/';
$user_avatars_path = $avatars_path . 'user/';
$default_blog_avatar = 'identicon'; //'local_default', 'gravatar_default', 'identicon', 'wavatar', 'monsterid'
$local_default_avatar_url = AVATARS_PLUGIN_URL . 'images/default-avatar-';
$local_default_avatar_path = AVATARS_PLUGIN_URL . 'images/default-avatar-';

/**
 * Plugin main class
 **/
class Avatars {

	/**
	 * Network settings parent file
	 **/
	var $network_top_menu = '';

	/**
	 * Network settings parent slug
	 **/
	var $network_top_menu_slug = '';

	/**
	 * Current version of the plugin
	 **/
	var $current_version = '3.5.4';

	/**
	 * PHP4 constructor
	 **/
	function Avatars() {
		__construct();
	}

	/**
	 * PHP5 constructor
	 **/
	function __construct() {
		global $wp_version;
		// load text domain
		if ( defined( 'WPMU_PLUGIN_DIR' ) && file_exists( WPMU_PLUGIN_DIR . '/avatars.php' ) ) {
			load_muplugin_textdomain( 'avatars', 'avatars-files/languages' );
		} else {
			load_plugin_textdomain( 'avatars', false, dirname( plugin_basename( __FILE__ ) ) . '/avatars-files/languages' );
		}

		// set network settings parent file
		if( version_compare( $wp_version , '3.0.9', '>' ) ) {
			$this->network_top_menu = 'settings.php';
			$this->network_top_menu_slug = 'network/settings.php';
		} else {
			$this->network_top_menu = 'ms-admin.php';
			$this->network_top_menu_slug = 'ms-admin.php';
		}

		// display admin notices
		add_action( 'admin_notices', array( &$this, 'admin_errors' ) );

		// load plugin functions
		add_action( 'plugins_loaded', array( &$this, 'plugins_loaded' ) );
	}

	/**
	 * Admin error notices.
	 *
	 * Display errors when BuddyPress is installed and/or when avatars directory is not writable
	 **/
	function admin_errors() {
		// check if BuddyPress is installed
		if( defined( 'BP_VERSION' ) ) {

			$message = sprintf( __( 'BuddyPress has it\'s own avatar system. The Avatars plugin functions have been deactivated. Please remove the files.', 'avatars' ), ABSPATH . $avatars_path );
			echo "<div class='error'><p>$message</p></div>";

		} else {

			global $avatars_path, $wp_filesystem;

			// check if old directory exists
			if ( is_dir( ABSPATH . 'wp-content/avatars/' ) && !is_dir( ABSPATH . $avatars_path ) ) {
				require_once( ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php' );
				require_once( ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php' );

				// temporarily stores existing filesystem object
				if( isset( $wp_filesystem ) )
					$orig_filesystem = wp_clone( $wp_filesystem );

				$wp_filesystem = new WP_Filesystem_Direct( false );

				// Set the permission constants if not already set.
				if ( ! defined('FS_CHMOD_DIR') )
					define('FS_CHMOD_DIR', 0755 );
				if ( ! defined('FS_CHMOD_FILE') )
					define('FS_CHMOD_FILE', 0644 );

				if ( $wp_filesystem->mkdir( ABSPATH . $avatars_path, FS_CHMOD_DIR ) ) { // create new avatars directory

					if( copy_dir( ABSPATH . 'wp-content/avatars/', ABSPATH . $avatars_path ) ) { // copy files to new directory

						if( $wp_filesystem->delete( ABSPATH . 'wp-content/avatars/', true ) ) // attempt delete of old folder
							$message = sprintf( __( 'The Avatars plugin now store files in %s. Your old folder has been moved.', 'avatars' ), ABSPATH . $avatars_path );
						else
							$message = sprintf( __( 'The Avatars plugin now store files in %s. Your old folder has been copied. Please verify that everything is working fine and delete the old folder manually.', 'avatars' ), ABSPATH . $avatars_path );

					} else { // unsuccessful copy, warns user

							$message = sprintf( __( 'The Avatars plugin now store files in %s. Please make sure that directory is writable by the server.', 'avatars' ), ABSPATH . $avatars_path );

					}

				} else {

					$message = sprintf( __( 'The Avatars plugin now store files in %s. Please make sure its parent directory is writable by the server.', 'avatars' ), ABSPATH . $avatars_path );

				}

				// we are finished with our custom filesystem object, let's unset it
				unset( $wp_filesystem );

				// restore original filesystem object
				if( isset( $orig_filesystem ) )
					$wp_filesystem = wp_clone( $orig_filesystem );

				echo "<div class='error'><p>$message</p></div>";
			}

			// check if plugin directory exists
			if ( ! wp_mkdir_p( ABSPATH . $avatars_path ) ) {
				$message = sprintf( __( 'The Avatars plugin was unable to create directory %s. Is its parent directory writable by the server?', 'avatars' ), ABSPATH . $avatars_path );
				echo "<div class='error'><p>$message</p></div>";
			}

		}
	}

	/**
	 * Load Avatars functions after all other plugins are loaded
	 **/
	function plugins_loaded() {
		if( !defined( 'BP_VERSION' ) ) {
			// add local avatar to the defaults list
			add_filter( 'avatar_defaults', array( &$this, 'defaults' ) );

			// add avatar to user profile page
			add_action( 'show_user_profile', array( &$this, 'to_profile' ) );
			add_action( 'edit_user_profile', array( &$this, 'to_profile' ) );

			// process avatar uploads
			add_action( 'admin_init', array( &$this, 'process' ) );

			// url rewriting
			add_action( 'init', array( &$this, 'flush_rules' ) );
			add_filter( 'query_vars', array( &$this, 'query_var' ) );
			add_action( 'template_redirect', array( &$this, 'load_avatar' ), -1 );
			add_action( 'generate_rewrite_rules', array( &$this, 'rewrite_rules' ) );

			// settings pages
			add_action( 'network_admin_menu', array( &$this, 'network_admin_page' ) );
			add_action( 'admin_menu', array( &$this, 'plug_pages' ) );
			add_action( 'user_admin_menu', array( &$this, 'user_plug_pages' ) );
			add_action( 'custom_menu_order', array( &$this, 'admin_menu' ) );
			add_filter( 'whitelist_options', array( &$this, 'whitelist' ) );

			// add necessary javascripts
			$page = isset( $_GET['page'] ) ? $_GET['page'] : '';
			if ( $page == 'blog-avatar' || $page == 'user-avatar' || $page == 'edit-user-avatar' || $page == 'edit-blog-avatar' ) {
				if ( isset($_GET['action']) && $_GET['action'] == 'upload_process' ) {
					add_action( 'admin_head', array( &$this, 'plug_scripts' ) );
					add_action( 'init', array( &$this, 'enqueue_scripts' ) );
				}
			}
		}
	}

	/**
	 * Add local avatar in the defaults list.
	 **/
	function defaults( $avatar_defaults ) {
		$avatar_defaults['local_default'] = __( 'Local (Avatars plugin)', 'avatars' );
		return $avatar_defaults;
	}

	/**
	 * Avatars rewrite rules.
	 **/
	function rewrite_rules( $wp_rewrite ) {
	  $new_rules = array(
		 'avatar/(.+)' => 'index.php?avatar=' . $wp_rewrite->preg_index(1));
	  $wp_rewrite->rules = $new_rules + $wp_rewrite->rules;
	}

	/**
	 * Flush rewrite rules if the avatar rule was not previously added.
	 **/
	function flush_rules() {
		$rules = get_option( 'rewrite_rules' );

		if ( ! isset( $rules['avatar/(.+)'] ) ) {
			global $wp_rewrite;
			$wp_rewrite->flush_rules();
		}
	}

	/**
	 * Add avatar query var.
	 **/
	function query_var( $vars ) {
		$vars[] = 'avatar';
		return $vars;
	}

	/**
	 * Load avatar if requested.
	 **/
	function load_avatar() {
		if( $file = get_query_var('avatar') ) {
			global $blog_avatars_path, $user_avatars_path, $default_blog_avatar, $local_default_avatar_path;
			$default_user_avatar = get_option( 'default_avatar' );
			require_once( AVATARS_PLUGIN_DIR . 'avatar.php' );
			exit;
		}
	}

	/**
	 * Add admin pages.
	 **/
	function plug_pages() {
		global $wpdb, $enable_main_blog_avatar;
		if ( $wpdb->blogid == '1' ) {
			if ( strtolower( $enable_main_blog_avatar ) == 'yes' ) {
				add_submenu_page('options-general.php', __( 'Blog Avatar', 'avatars' ), __( 'Blog Avatar', 'avatars' ), 'manage_options', 'blog-avatar', array( &$this, 'page_edit_blog_avatar' ) );
			}
		} else {
			add_submenu_page('options-general.php', __( 'Blog Avatar', 'avatars' ), __( 'Blog Avatar', 'avatars' ), 'manage_options', 'blog-avatar', array( &$this, 'page_edit_blog_avatar' ) );
		}
		if ( current_user_can('edit_users') ) {
			add_submenu_page('users.php', __( 'Your Avatar', 'avatars' ), __( 'Your Avatar', 'avatars' ), 'manage_options', 'user-avatar', array( &$this, 'page_edit_user_avatar' ) );
		} else if (is_super_admin()) {
			add_submenu_page('profile.php', __( 'Your Avatar', 'avatars' ), __( 'Your Avatar', 'avatars' ), 'read', 'user-avatar', array( &$this, 'page_edit_user_avatar' ) );
		}
		if ( is_super_admin() && isset( $_GET['page'] ) && $_GET['page'] == 'edit-user-avatar' ) {
			add_action( 'admin_page_edit', 'page_site_admin_edit_user_avatar' );
			if( !version_compare( $wp_version , '3.0.9', '>' ) )
				add_submenu_page( $this->network_top_menu, __( 'Edit User Avatar', 'avatars' ), __( 'Edit User Avatar', 'avatars' ), 'manage_network_options', 'edit-user-avatar', array( &$this, 'page_site_admin_edit_user_avatar' ) );
		}
	}
	
	function user_plug_pages() {
		add_submenu_page('profile.php', __( 'Your Avatar', 'avatars' ), __( 'Your Avatar', 'avatars' ), 'exist', 'user-avatar', array( &$this, 'page_edit_user_avatar' ) );
	}

	/**
	 * Add network admin page.
	 **/
	function network_admin_page() {
		global $wp_version;
		if( version_compare( $wp_version , '3.0.9', '>' ) )
			add_submenu_page( $this->network_top_menu, __( 'Edit User Avatar', 'avatars' ), __( 'Edit User Avatar', 'avatars' ), 'manage_network_options', 'edit-user-avatar', array( &$this, 'page_site_admin_edit_user_avatar' ) );
	}

	/**
	 * Find an admin menu item by its page slug.
	 **/
	function array_find_r( $needle, $haystack ) {
		if( !is_array( $haystack ) )
			return false;
		foreach( $haystack as $key => $value ) {
			if( isset( $value[2] ) && $value[2] == $needle ) {
				return $key;
			} elseif( is_array( $value ) ) {
				$result = $this->array_find_r( $needle, $value );
				if( is_numeric( $result ) )
					return $result;
			}
		}
		return false;
	}

	/**
	 * Unset admin menus.
	 **/
	function admin_menu() {
		global $submenu;

		$key = $this->array_find_r( 'edit-user-avatar', $submenu );
		if( isset( $submenu['ms-admin.php'][$key] ) )
			unset( $submenu['ms-admin.php'][$key] );
		if( isset( $submenu['settings.php'][$key] ) )
			unset( $submenu['settings.php'][$key] );

		$key = $this->array_find_r( 'user-avatar', $submenu );
		//unset( $submenu['users.php'][$key] );
		//unset( $submenu['profile.php'][$key] );
	}

	/**
	 * Add avatar to user profile page.
	 **/
	function to_profile( $profileuser ) {
		global $submenu_file;
	?>
		<table class="form-table">
			<tbody>
				<tr>
					<th><label for="avatar">Avatar</label></th>
					<td><span class="description"><?php _e( 'This is your "user" avatar. It will appear whenever you leave comments, post in the forums and when your popular posts are displayed around the site.', 'avatars' ); ?><br>
					<?php echo get_avatar( $profileuser->ID ); ?><br>
					<?php
					if( IS_PROFILE_PAGE )
						if ( is_user_admin() )
							echo '<a href="' . admin_url( "user/$submenu_file?page=user-avatar" ) . '">' . __( 'Change Avatar', 'avatars' ) . '</a></td>';
						else
							echo '<a href="' . admin_url( "$submenu_file?page=user-avatar" ) . '">' . __( 'Change Avatar', 'avatars' ) . '</a></td>';
					else
						echo '<a href="' . admin_url( "$this->network_top_menu_slug?page=edit-user-avatar&uid=$profileuser->ID" ) . '">' . __( 'Change Avatar', 'avatars' ) . '</a></td>';
					?>
				</tr>
			</tbody>
		</table>
	<?php
	}

	/**
	 * Admin javascript.
	 **/
	function plug_scripts() {
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

	/**
	 * Enqueue javascript.
	 **/
	function enqueue_scripts() {
		wp_enqueue_script('scriptaculous');
		wp_enqueue_script('scriptaculous-root');
		wp_enqueue_script('scriptaculous-builder');
		wp_enqueue_script('scriptaculous-dragdrop');
		wp_enqueue_script('prototype');
	}

	/**
	 * Whilelist plugin options.
	 **/
	function whitelist( $options ) {
		$added = array( 'discussion' => array( 'avatar_default' ) );
		$options = add_option_whitelist( $added, $options );
		return $options;
	}

	/**
	 * Map a numeric value to a supported avatar size.
	 **/
	function size_map( $size ) {
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

	/**
	 * Process avatar upload.
	 **/
	function process() {
		global $plugin_page, $wpdb, $user_ID, $blog_avatars_path, $user_avatars_path;

		$action = isset( $_GET[ 'action' ] ) ? $_GET[ 'action' ] : '';

		// blog avatar processing
		if( 'blog-avatar' == $plugin_page ) {
			switch( $action ) {
				case 'upload_process':
					if ( isset( $_POST['Reset'] ) ) {
						$avatar_path = ABSPATH . $blog_avatars_path . substr(md5($wpdb->blogid), 0, 3) . '/';
						$this->delete_temp( $avatar_path . 'blog-' . $wpdb->blogid . '-16.png');
						$this->delete_temp( $avatar_path . 'blog-' . $wpdb->blogid . '-32.png');
						$this->delete_temp( $avatar_path . 'blog-' . $wpdb->blogid . '-48.png');
						$this->delete_temp( $avatar_path . 'blog-' . $wpdb->blogid . '-96.png');
						$this->delete_temp( $avatar_path . 'blog-' . $wpdb->blogid . '-128.png');

						wp_redirect( admin_url( 'options-general.php?page=blog-avatar&updated=true' ) );
						exit;
					} elseif ( isset( $_POST['Alternative'] ) ) {
						// Alternative Upload

						$avatar_path = ABSPATH . $blog_avatars_path . substr(md5($wpdb->blogid), 0, 3) . '/';

						if (is_dir($avatar_path)) {
						} else {
							wp_mkdir_p( $avatar_path );
						}

						$image_path = $avatar_path . basename($_FILES['avatar_file']['name']);

						if(move_uploaded_file($_FILES['avatar_file']['tmp_name'], $image_path)) {
							//file uploaded...
							chmod($image_path, 0777);
						} else{
							echo __( 'There was an error uploading the file, please try again.', 'avatars' );
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

						if ($avatar_image_type == 'jpeg'){
							$im = ImageCreateFromjpeg($avatar_path . basename( $_FILES['avatar_file']['name']));
						}
						if ($avatar_image_type == 'png'){
							$im = ImageCreateFrompng($avatar_path . basename( $_FILES['avatar_file']['name']));
						}
						if ($avatar_image_type == 'gif'){
							$im = ImageCreateFromgif($avatar_path . basename( $_FILES['avatar_file']['name']));
						}

						if (!$im) {
							echo __( 'There was an error uploading the file, please try again.', 'avatars' );
							return false;
						}

						foreach( array( 16, 32, 48, 96, 128 ) as $avatar_size ) {
							$im_dest = imagecreatetruecolor( $avatar_size, $avatar_size );
							imagecopyresampled( $im_dest, $im, 0, 0, 0, 0, $avatar_size, $avatar_size, $avatar_width, $avatar_height );
							if( 'png' == $avatar_image_type )
								imagesavealpha( $im_dest, true );
							imagepng( $im_dest, $avatar_path . "blog-$wpdb->blogid-$avatar_size.png" );
						}

						$this->delete_temp( $avatar_path . basename( $_FILES['avatar_file']['name']));
						if ( function_exists( 'moderation_image_insert' ) ) {
							moderation_image_insert('avatar', $wpdb->blogid, $user_ID, $avatar_path . 'blog-' . $wpdb->blogid . '-128.png', 'http://' . $current_site->domain . $current_site->path . 'avatar/blog-' . $wpdb->blogid . '-128.png');
						}

						wp_redirect( admin_url( 'options-general.php?page=blog-avatar&updated=true' ) );
						exit;
					}
				break;

				case 'crop_process':
					$avatar_path = ABSPATH . $blog_avatars_path . substr(md5($wpdb->blogid), 0, 3) . '/';

					if (is_dir($avatar_path)) {
					} else {
						wp_mkdir_p( $avatar_path );
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

					if (!$im) {
						echo __( 'There was an error uploading the file, please try again.', 'avatars' );
						return false;
					}

					foreach( array( 16, 32, 48, 96, 128 ) as $avatar_size ) {
						$im_dest = imagecreatetruecolor( $avatar_size, $avatar_size );
						$avatar_width = $_POST['x2'] - $_POST['x1'];
						$avatar_height = $_POST['y2'] - $_POST['y1'];
						imagecopyresampled( $im_dest, $im, 0, 0, $_POST['x1'], $_POST['y1'], $avatar_size, $avatar_size, $avatar_width, $avatar_height );
						if ($_POST['image_type'] == 'png'){
							imagesavealpha( $im_dest, true );
						}
						imagepng( $im_dest, $avatar_path . "blog-$wpdb->blogid-$avatar_size.png" );
					}

					$this->delete_temp( $_POST['file_path'] . $_POST['file_name']);
					if ( function_exists( 'moderation_image_insert' ) ) {
						moderation_image_insert('avatar', $wpdb->blogid, $user_ID, $avatar_path . 'blog-' . $wpdb->blogid . '-128.png', 'http://' . $current_site->domain . $current_site->path . 'avatar/blog-' . $wpdb->blogid . '-128.png');
					}

					wp_redirect( admin_url( 'options-general.php?page=blog-avatar&updated=true' ) );
					exit;
				break;

				default:
				break;
			}
		}

		if( 'user-avatar' == $plugin_page ) {
			switch( $action ) {

				case 'upload_process':
					if ( isset( $_POST['Reset'] ) ) {
						$avatar_path = ABSPATH . $user_avatars_path . substr(md5($user_ID), 0, 3) . '/';
						$this->delete_temp( $avatar_path . 'user-' . $user_ID . '-16.png');
						$this->delete_temp( $avatar_path . 'user-' . $user_ID . '-32.png');
						$this->delete_temp( $avatar_path . 'user-' . $user_ID . '-48.png');
						$this->delete_temp( $avatar_path . 'user-' . $user_ID . '-96.png');
						$this->delete_temp( $avatar_path . 'user-' . $user_ID . '-128.png');
						if ( current_user_can('manage_options') ) {
							wp_redirect( admin_url( 'users.php?page=user-avatar&updated=true&updatedmsg=' . urlencode( __( 'Avatar reset.', 'avatars' ) ) ) );
							exit;
						} else {
							wp_redirect( admin_url( 'profile.php?page=user-avatar&updated=true&updatedmsg=' . urlencode( __( 'Avatar reset.', 'avatars' ) ) ) );
							exit;
						}
					} elseif ( isset( $_POST['Alternative'] ) ) {
						$avatar_path = ABSPATH . $user_avatars_path . substr(md5($user_ID), 0, 3) . '/';

						if ( is_dir( $avatar_path ) ) {
						} else {
							wp_mkdir_p( $avatar_path );
						}

						$image_path = $avatar_path . basename($_FILES['avatar_file']['name']);

						if( move_uploaded_file( $_FILES['avatar_file']['tmp_name'], $image_path ) ) {
							//file uploaded...
							chmod( $image_path, 0777 );
						} else{
							echo __( "There was an error uploading the file, please try again.", 'avatars' );
						}
						list($avatar_width, $avatar_height, $avatar_type, $avatar_attr) = getimagesize($image_path);

						if ($_FILES['avatar_file']['type'] == "image/gif") {
							$avatar_image_type = 'gif';
						}
						if ($_FILES['avatar_file']['type'] == "image/jpeg") {
							$avatar_image_type = 'jpeg';
						}
						if ($_FILES['avatar_file']['type'] == "image/pjpeg") {
							$avatar_image_type = 'jpeg';
						}
						if ($_FILES['avatar_file']['type'] == "image/jpg") {
							$avatar_image_type = 'jpeg';
						}
						if ($_FILES['avatar_file']['type'] == "image/png") {
							$avatar_image_type = 'png';
						}
						if ($_FILES['avatar_file']['type'] == "image/x-png") {
							$avatar_image_type = 'png';
						}

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

						if (!$im) {
							echo __( 'There was an error uploading the file, please try again.', 'avatars' );
							return false;
						}

						foreach( array( 16, 32, 48, 96, 128 ) as $avatar_size ) {
							$im_dest = imagecreatetruecolor( $avatar_size, $avatar_size );
							imagecopyresampled( $im_dest, $im, 0, 0, 0, 0, $avatar_size, $avatar_size, $avatar_width, $avatar_height );
							if( 'png' == $avatar_image_type )
								imagesavealpha( $im_dest, true );
							imagepng( $im_dest, $avatar_path . "user-$user_ID-$avatar_size.png" );
						}

						$this->delete_temp( $avatar_path . basename( $_FILES['avatar_file']['name']));
						if ( function_exists( 'moderation_image_insert' ) ) {
							moderation_image_insert('avatar', $wpdb->blogid, $user_ID, $avatar_path . 'user-' . $user_ID . '-128.png', 'http://' . $current_site->domain . $current_site->path . 'avatar/user-' . $user_ID . '-128.png');
						}
						if ( current_user_can('manage_options') ) {
							wp_redirect( admin_url( 'users.php?page=user-avatar&updated=true&updatedmsg=' . urlencode( __( 'Avatar updated.', 'avatars' ) ) ) );
							exit;
						} else {
							wp_redirect( admin_url( 'profile.php?page=user-avatar&updated=true&updatedmsg=' . urlencode( __( 'Avatar updated.', 'avatars' ) ) ) );
							exit;
						}
					}
				break;

				case 'crop_process':
					$avatar_path = ABSPATH . $user_avatars_path . substr(md5($user_ID), 0, 3) . '/';

					if (is_dir($avatar_path)) {
					} else {
						wp_mkdir_p( $avatar_path );
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

					if (!$im) {
						echo __( 'There was an error uploading the file, please try again.', 'avatars' );
						return false;
					}

					foreach( array( 16, 32, 48, 96, 128 ) as $avatar_size ) {
						$im_dest = imagecreatetruecolor( $avatar_size, $avatar_size );
						$avatar_width = $_POST['x2'] - $_POST['x1'];
						$avatar_height = $_POST['y2'] - $_POST['y1'];
						imagecopyresampled( $im_dest, $im, 0, 0, $_POST['x1'], $_POST['y1'], $avatar_size, $avatar_size, $avatar_width, $avatar_height );
						if( 'png' == $_POST['image_type'] )
							imagesavealpha( $im_dest, true );
						imagepng( $im_dest, $avatar_path . "user-$user_ID-$avatar_size.png" );
					}

					$this->delete_temp( $_POST['file_path'] . $_POST['file_name'] );
					if ( function_exists( 'moderation_image_insert' ) ) {
						moderation_image_insert('avatar', $wpdb->blogid, $user_ID, $avatar_path . 'user-' . $user_ID . '-128.png', 'http://' . $current_site->domain . $current_site->path . 'avatar/user-' . $user_ID . '-128.png');
					}
					if ( current_user_can('manage_options') ) {
						wp_redirect( admin_url( 'users.php?page=user-avatar&updated=true&updatedmsg=' . urlencode( __( 'Avatar updated.', 'avatars' ) ) ) );
						exit;
					} else {
						wp_redirect( admin_url( 'profile.php?page=user-avatar&updated=true&updatedmsg=' . urlencode( __( 'Avatar updated.', 'avatars' ) ) ) );
						exit;
					}
				break;

			}
		}

		if( 'edit-user-avatar' == $plugin_page ) {
			switch( $action ) {

				case 'upload_process':
					if ( isset( $_POST['Reset'] ) ) {
						$avatar_path = ABSPATH . $user_avatars_path . substr(md5($_GET['uid']), 0, 3) . '/';
						$this->delete_temp( $avatar_path . 'user-' . $_GET['uid'] . '-16.png');
						$this->delete_temp( $avatar_path . 'user-' . $_GET['uid'] . '-32.png');
						$this->delete_temp( $avatar_path . 'user-' . $_GET['uid'] . '-48.png');
						$this->delete_temp( $avatar_path . 'user-' . $_GET['uid'] . '-96.png');
						$this->delete_temp( $avatar_path . 'user-' . $_GET['uid'] . '-128.png');
						wp_redirect( admin_url( "$this->network_top_menu_slug?page=edit-user-avatar&uid={$_GET[uid]}&updated=true&updatedmsg=" . urlencode( __( 'Avatar reset.', 'avatars' ) ) ) );
						exit;

					} elseif ( isset( $_POST['Alternative'] ) ) {
						// Alternative Upload

						$avatar_path = ABSPATH . $user_avatars_path . substr(md5($_GET['uid']), 0, 3) . '/';

						if ( !is_dir($avatar_path) ) {
							wp_mkdir_p( $avatar_path );
						}

						$image_path = $avatar_path . basename($_FILES['avatar_file']['name']);

						if(move_uploaded_file($_FILES['avatar_file']['tmp_name'], $image_path)) {
							//file uploaded...
							chmod($image_path, 0777);
						} else{
							echo __( 'There was an error uploading the file, please try again.', 'avatars' );
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

						if ($avatar_image_type == 'jpeg'){
							$im = ImageCreateFromjpeg($avatar_path . basename( $_FILES['avatar_file']['name']));
						}
						if ($avatar_image_type == 'png'){
							$im = ImageCreateFrompng($avatar_path . basename( $_FILES['avatar_file']['name']));
						}
						if ($avatar_image_type == 'gif'){
							$im = ImageCreateFromgif($avatar_path . basename( $_FILES['avatar_file']['name']));
						}

						if (!$im) {
							echo __( 'There was an error uploading the file, please try again.', 'avatars' );
							return false;
						}

						foreach( array( 16, 32, 48, 96, 128 ) as $avatar_size ) {
							$im_dest = imagecreatetruecolor( $avatar_size, $avatar_size );
							imagecopyresampled( $im_dest, $im, 0, 0, 0, 0, $avatar_size, $avatar_size, $avatar_width, $avatar_height );
							if( 'png' == $avatar_image_type )
								imagesavealpha( $im_dest, true );
							imagepng( $im_dest, $avatar_path . "user-$_GET[uid]-$avatar_size.png" );
						}

						$this->delete_temp( $avatar_path . basename( $_FILES['avatar_file']['name']));

						wp_redirect( admin_url( "$this->network_top_menu_slug?page=edit-user-avatar&uid={$_GET[uid]}&updated=true&updatedmsg=" . urlencode( __( 'Avatar updated.', 'avatars' ) ) ) );
						exit;
					}
				break;
				//---------------------------------------------------//
				case 'crop_process':
					$avatar_path = ABSPATH . $user_avatars_path . substr(md5($_GET['uid']), 0, 3) . '/';

					if( !is_dir($avatar_path) ) {
						wp_mkdir_p( $avatar_path );
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

					if (!$im) {
						echo __( 'There was an error uploading the file, please try again.', 'avatars' );
						return false;
					}

					foreach( array( 16, 32, 48, 96, 128 ) as $avatar_size ) {
						$im_dest = imagecreatetruecolor( $avatar_size, $avatar_size );
						$avatar_width = $_POST['x2'] - $_POST['x1'];
						$avatar_height = $_POST['y2'] - $_POST['y1'];
						imagecopyresampled( $im_dest, $im, 0, 0, $_POST['x1'], $_POST['y1'], $avatar_size, $avatar_size, $avatar_width, $avatar_height );
						if ($_POST['image_type'] == 'png'){
							imagesavealpha( $im_dest, true );
						}
						imagepng( $im_dest, $avatar_path . "user-$_GET[uid]-$avatar_size.png" );
					}

					$this->delete_temp( $_POST['file_path'] . $_POST['file_name']);

					wp_redirect( admin_url( "$this->network_top_menu_slug?page=edit-user-avatar&uid={$_GET[uid]}&updated=true&updatedmsg=" . urlencode( __( 'Avatar updated.', 'avatars' ) ) ) );
					exit;
				break;

			}
		}

	}

	/**
	 * Return content for Edit Blog Avatar page.
	 **/
	function page_edit_blog_avatar() {
		global $wpdb, $blog_avatars_path;

		if( !current_user_can('manage_options') ) {
			echo '<p>' . __( 'Nice Try...', 'avatars' ) . '</p>';  //If accessed properly, this message doesn't appear.
			return;
		}

		echo '<div class="wrap">';
		$action = isset( $_GET[ 'action' ] ) ? $_GET[ 'action' ] : '';

		if( 'upload_process' == $action && ! isset( $_POST['Alternative'] ) ) {
			$avatar_path = ABSPATH . $blog_avatars_path . substr(md5($wpdb->blogid), 0, 3) . '/';

			if (is_dir($avatar_path)) {
			} else {
				wp_mkdir_p( $avatar_path );
			}

			$image_path = $avatar_path . basename( $_FILES['avatar_file']['name'] );

			if(move_uploaded_file($_FILES['avatar_file']['tmp_name'], $image_path)) {
				//file uploaded...
				chmod($image_path, 0777);
			} else{
				echo __( 'There was an error uploading the file, please try again.', 'avatars' );
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
			// Standard Upload
			?>
			<h2><?php _e( 'Crop Image', 'avatars' ) ?></h2>
			<form method="post" action="options-general.php?page=blog-avatar&action=crop_process">

			<p><?php _e( 'Choose the part of the image you want to use as the avatar.', 'avatars' ); ?></p>
			<div id="testWrap">
			<img src="<?php echo '../' . $blog_avatars_path . substr(md5($wpdb->blogid), 0, 3) . '/' . $_FILES['avatar_file']['name']; ?>" id="upload" width="<?php echo $avatar_width; ?>" height="<?php echo $avatar_height; ?>" />
			</div>

			<input type="hidden" name="file_path" id="file_path" value="<?php echo $avatar_path; ?>" />
			<input type="hidden" name="file_name" id="file_name" value="<?php echo basename( $_FILES['avatar_file']['name'] ); ?>" />
			<input type="hidden" name="image_type" id="image_type" value="<?php echo $avatar_image_type; ?>" />
			<input type="hidden" name="x1" id="x1" />
			<input type="hidden" name="y1" id="y1" />
			<input type="hidden" name="x2" id="x2" />
			<input type="hidden" name="y2" id="y2" />
			<input type="hidden" name="width" id="width" />
			<input type="hidden" name="height" id="height" />

			<p class="submit">
			<input type="submit" value="<?php _e( 'Crop Image', 'avatars' ); ?>" />
			</p>

			</form>
			<?php
		} else {
			?>
			<h2><?php _e( 'Blog Avatar', 'avatars' ) ?></h2>
			<form action="options-general.php?page=blog-avatar&action=upload_process" method="post" enctype="multipart/form-data">
				<p><?php _e( 'This is your "blog" avatar. It will appear whenever your blog is listed (for example, on the front page of the site).', 'avatars' ); ?></p>
				<p><?php echo get_blog_avatar( $wpdb->blogid, '96', '' ); ?></p>

				<h3><?php _e( 'Upload New Avatar', 'avatars' ); ?></h3>
				<p>
				  <input name="avatar_file" id="avatar_file" size="20" type="file">
				  <input type="hidden" name="MAX_FILE_SIZE" value="100000" />
				</p>
				<p><?php _e( 'Allowed Formats: jpeg, gif, and png', 'avatars' ); ?></p>
				<p><?php _e( 'If you are experiencing problems cropping your image please use the alternative upload method ("Alternative Upload" button).', 'avatars' ); ?></p>
				<p class="submit">
				  <input name="Submit" value="<?php _e( 'Upload', 'avatars' ) ?>" type="submit">
				  <input name="Alternative" value="<?php _e( 'Alternative Upload', 'avatars' ) ?>" type="submit">
				  <input name="Reset" value="<?php _e( 'Reset', 'avatars' ) ?>" type="submit">
				</p>
			</form>
			<?php
		}
		echo '</div>';
	}

	/**
	 * Return content for Edit User Avatar page.
	 **/
	function page_edit_user_avatar() {
		global $wpdb, $user_avatars_path, $user_ID, $current_site;

		if (isset($_GET['updated'])) {
			?>
			<div style="background-color: rgb(255, 251, 204);" id="message" class="updated fade">
				<p>
					<strong>
					<?php echo isset( $_GET['updatedmsg'] ) ? $_GET['updatedmsg'] : ''; ?>
					</strong>
				</p>
			</div>
			<?php
		}
		echo '<div class="wrap">';
		$action = isset( $_GET[ 'action' ] ) ? $_GET[ 'action' ] : '';

		if( 'upload_process' == $action && ! isset( $_POST['Alternative'] ) ) {

			$avatar_path = ABSPATH . $user_avatars_path . substr(md5($user_ID), 0, 3) . '/';

			if ( ! is_dir( $avatar_path ) ) {
				wp_mkdir_p( $avatar_path );
			}

			$image_path = $avatar_path . basename($_FILES['avatar_file']['name']);

			if( move_uploaded_file( $_FILES['avatar_file']['tmp_name'], $image_path ) ) {
				//file uploaded...
				chmod( $image_path, 0777 );
			} else{
				echo __( "There was an error uploading the file, please try again.", 'avatars' );
			}
			list($avatar_width, $avatar_height, $avatar_type, $avatar_attr) = getimagesize($image_path);

			if ($_FILES['avatar_file']['type'] == "image/gif") {
				$avatar_image_type = 'gif';
			}
			if ($_FILES['avatar_file']['type'] == "image/jpeg") {
				$avatar_image_type = 'jpeg';
			}
			if ($_FILES['avatar_file']['type'] == "image/pjpeg") {
				$avatar_image_type = 'jpeg';
			}
			if ($_FILES['avatar_file']['type'] == "image/jpg") {
				$avatar_image_type = 'jpeg';
			}
			if ($_FILES['avatar_file']['type'] == "image/png") {
				$avatar_image_type = 'png';
			}
			if ($_FILES['avatar_file']['type'] == "image/x-png") {
				$avatar_image_type = 'png';
			}

			// Standard Upload
			?>
			<h2><?php _e( 'Crop Image', 'avatars' ) ?></h2>
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

			<p><?php _e( 'Choose the part of the image you want to use as the avatar.', 'avatars' ); ?></p>
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
			<input type="submit" value="<?php _e( 'Crop Image', 'avatars' ); ?>" />
			</p>

			</form>
			<?php

		} else {
			?>
			<h2><?php _e( 'Your Avatar', 'avatars' ) ?></h2>
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
			<p>
			<?php
			echo get_avatar($user_ID,'96',get_option('avatar_default'));
			?>
			</p>
			<h3><?php _e( 'Upload New Avatar', 'avatars' ); ?></h3
			><p>
			  <input name="avatar_file" id="avatar_file" size="20" type="file">
			  <input type="hidden" name="MAX_FILE_SIZE" value="100000" />
			</p>
			<p><?php _e( 'Allowed Formats:jpeg, gif, and png', 'avatars' ); ?></p>
			<p><?php _e( 'If you are experiencing problems cropping your image please use the alternative upload method ("Alternative Upload" button).', 'avatars' ); ?></p>
			<p class="submit">
			  <input name="Submit" value="<?php _e( 'Upload', 'avatars' ) ?>" type="submit">
			  <input name="Alternative" value="<?php _e( 'Alternative Upload', 'avatars' ) ?>" type="submit">
			  <input name="Reset" value="<?php _e( 'Reset', 'avatars' ) ?>" type="submit">
			</p>
			</form>
			<?php
		}
		echo '</div>';
	}

	/**
	 * Return content for Edit User Avatar page for super admin.
	 **/
	function page_site_admin_edit_user_avatar() {
		global $wpdb, $user_avatars_path;

		if (isset($_GET['updated'])) {
			?>
			<div style="background-color: rgb(255, 251, 204);" id="message" class="updated fade">
				<p>
					<strong>
					<?php echo isset( $_GET['updatedmsg'] ) ? $_GET['updatedmsg'] : ''; ?>
					</strong>
				</p>
			</div>
			<?php
		}
		echo '<div class="wrap">';
		$action = isset( $_GET['action'] ) ? $_GET['action'] : '';

		if( 'upload_process' == $action && ! isset( $_POST['Alternative'] ) ) {
			// Standard Upload
			$avatar_path = ABSPATH . $user_avatars_path . substr(md5($_GET['uid']), 0, 3) . '/';

			if ( !is_dir($avatar_path) ) {
				wp_mkdir_p( $avatar_path );
			}

			$image_path = $avatar_path . basename($_FILES['avatar_file']['name']);

			if(move_uploaded_file($_FILES['avatar_file']['tmp_name'], $image_path)) {
				//file uploaded...
				chmod($image_path, 0777);
			} else{
				echo __( "There was an error uploading the file, please try again.", 'avatars' );
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

			?>
			<h2><?php _e( 'Crop Image', 'avatars' ) ?></h2>
			<form method="post" action="<?php echo $this->network_top_menu ?>?page=edit-user-avatar&uid=<?php echo $_GET['uid']; ?>&action=crop_process">

			<p><?php _e( 'Choose the part of the image you want to use as the avatar.', 'avatars' ); ?></p>
			<div id="testWrap">
			<img src="<?php echo site_url( $user_avatars_path . substr(md5($_GET['uid']), 0, 3) . '/' . $_FILES['avatar_file']['name'] ); ?>" id="upload" width="<?php echo $avatar_width; ?>" height="<?php echo $avatar_height; ?>" />
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
			<input type="submit" value="<?php _e( 'Crop Image', 'avatars' ); ?>" />
			</p>

			</form>
			<?php

		} else {

			?>
			<h2><?php _e( 'User Avatar', 'avatars' ) ?></h2>
			<form action="<?php echo $this->network_top_menu ?>?page=edit-user-avatar&action=upload_process&uid=<?php echo $_GET['uid']; ?>" method="post" enctype="multipart/form-data">
				<p><?php echo get_avatar( $_GET['uid'], '96', get_option( 'avatar_default' ) ); ?></p>
				<h3><?php _e( 'Upload New Avatar', 'avatars' ); ?></h3>
				<p>
				  <input name="avatar_file" id="avatar_file" size="20" type="file">
				  <input type="hidden" name="MAX_FILE_SIZE" value="100000" />
				</p>
				<p><?php _e( 'Allowed Formats:jpeg, gif, and png', 'avatars' ); ?></p>
				<p><?php _e( 'If you are experiencing problems cropping the image please use the alternative upload method ("Alternative Upload" button).', 'avatars' ); ?></p>
				<p class="submit">
				  <input name="Submit" value="<?php _e( 'Upload', 'avatars' ) ?>" type="submit">
				  <input name="Alternative" value="<?php _e( 'Alternative Upload', 'avatars' ) ?>" type="submit">
				  <input name="Reset" value="<?php _e( 'Reset', 'avatars' ) ?>" type="submit">
				</p>
			</form>
			<?php

		}

		echo '</div>';
	}

	/**
	 * Checks whether the given email exists.
	 */
	function email_exists( $email ) {
		if ( $user = get_user_by('email', $email ) )
			return $user->ID;

		return false;
	}

	/**
	 * Delete temporary file.
	 **/
	function delete_temp( $file ) {
		chmod( $file, 0777 );
		if( unlink( $file ) )
			return true;
		else
			return false;
	}

	/**
	 * Return user avatar.
	 **/
	function get_avatar( $id_or_email, $size = '96', $default = '', $alt = false ) {
		global $current_site, $current_screen, $user_avatars_path, $local_default_avatar_url;

		if ( ! get_option('show_avatars') )
			return false;

		if ( false === $alt )
			$safe_alt = '';
		else
			$safe_alt = esc_attr( $alt );

		if ( !is_numeric( $size ) )
			$size = '96';

		$size = $this->size_map( $size );

		$email = '';
		if ( is_numeric( $id_or_email ) ) {
			$id = (int) $id_or_email;
			$user = get_userdata( $id );
			if ( $user )
				$email = $user->user_email;
		} elseif ( is_object( $id_or_email ) ) {
			// No avatar for pingbacks or trackbacks
			$allowed_comment_types = apply_filters( 'get_avatar_comment_types', array( 'comment' ) );
			if ( ! empty( $id_or_email->comment_type ) && ! in_array( $id_or_email->comment_type, (array) $allowed_comment_types ) )
				return false;

			if ( !empty( $id_or_email->user_id ) ) {
				$id = (int) $id_or_email->user_id;
				$user = get_userdata( $id );
				if ( $user )
					$email = $user->user_email;
			} elseif ( !empty( $id_or_email->comment_author_email ) ) {
				$email = $id_or_email->comment_author_email;
			}
		} else {
			$email = $id_or_email;
		}

		if ( empty( $default ) ) {
			$avatar_default = get_option( 'avatar_default' );
			if ( empty( $avatar_default ) )
				$default = 'local_default';
			else
				$default = $avatar_default;
		}

		if ( !empty( $email ) )
			$email_hash = md5( strtolower( $email ) );

		if ( is_ssl() ) {
			$host = 'https://secure.gravatar.com';
		} else {
			if ( !empty( $email ) )
				$host = sprintf( "http://%d.gravatar.com", ( hexdec( $email_hash{0} ) % 2 ) );
			else
				$host = 'http://0.gravatar.com';
		}

		if( 'local_default' == $default )
			$default = $local_default_avatar_url . $size . '.png';
		elseif( 'mystery' == $default )
			$default = "$host/avatar/ad516503a11cd5ca435acc9bb6523536?s={$size}"; // ad516503a11cd5ca435acc9bb6523536 == md5('unknown@gravatar.com')
		elseif( 'blank' == $default )
			$default = includes_url( 'images/blank.gif' );
		elseif( !empty( $email ) && 'gravatar_default' == $default )
			$default = '';
		elseif( 'gravatar_default' == $default )
			$default = "$host/avatar/s={$size}";
		elseif( empty( $email ) )
			$default = "$host/avatar/?d=$default&amp;s={$size}";
		elseif( strpos( $default, 'http://' ) === 0 )
			$default = add_query_arg( 's', $size, $default );

		if ( !empty($email) ) {
			if ( $avatar_user_id = $this->email_exists( $email ) ) { // email exists locally - check if file exists
				$file = ABSPATH . $user_avatars_path . substr( md5( $avatar_user_id ), 0, 3 ) . '/user-' . $avatar_user_id . '-' . $size . '.png';

				if ( is_file( $file ) && ! ( isset( $current_screen->id ) && 'options-discussion' == $current_screen->id ) ) { // if file exists and we are not on the discussion options page
					if ( isset( $_GET['page'] ) && 'user-avatar' == $_GET['page'] )
						$out = 'http://' . $current_site->domain . $current_site->path . 'avatar/user-' . $avatar_user_id . '-' . $size . '.png?rand=' . md5( time() );
					else
						$out = 'http://' . $current_site->domain . $current_site->path . 'avatar/user-' . $avatar_user_id . '-' . $size . '.png';
				}
			}

			if( empty( $out ) ) {
				$out = "$host/avatar/";
				$out .= $email_hash;
				$out .= '?s='.$size;
				$out .= '&amp;d=' . urlencode( $default );

				$rating = get_option('avatar_rating');
				if ( !empty( $rating ) )
					$out .= "&amp;r={$rating}";
			}

			$avatar = "<img alt='{$safe_alt}' src='{$out}' class='avatar avatar-{$size} photo' height='{$size}' width='{$size}' />";
		} else {
			$avatar = "<img alt='{$safe_alt}' src='{$default}' class='avatar avatar-{$size} photo avatar-default' height='{$size}' width='{$size}' />";
		}

		return apply_filters( 'get_avatar', $avatar, $id_or_email, $size, $default, $alt );
	}

	/**
	 * Return blog avatar.
	 **/
	function get_blog_avatar( $id, $size = '96', $default = '', $alt = '' ) {
		global $current_site, $blog_avatars_path, $default_blog_avatar, $local_default_avatar_url;

		if ( false === $alt )
			$safe_alt = '';
		else
			$safe_alt = esc_attr( $alt );

		if ( !is_numeric( $size ) ) {
			$size = '96';
		}
		$size = $this->size_map( $size );
		if ( empty( $default ) ) {
			$default = get_option('avatar_default');
			if ( empty( $default ) ) {
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
			$default = $local_default_avatar_url . $size . '.png';;
		}

		if ( !empty($id) ) {
			//user exists locally - check if avatar exists
			$file = ABSPATH . $blog_avatars_path . substr(md5($id), 0, 3) . '/blog-' . $id . '-' . $size . '.png';
			if ( is_file( $file ) ) {
				if ( isset( $_GET['page'] ) && ( $_GET['page'] == 'blog-avatar' || $_GET['page'] == 'edit-blog-avatar' ) ) {
					$path = 'http://' . $current_site->domain . $current_site->path . 'avatar/blog-' . $id . '-' . $size . '.png?rand=' . md5(time());
				} else {
					$path = 'http://' . $current_site->domain . $current_site->path . 'avatar/blog-' . $id . '-' . $size . '.png';
				}
			} else {
				$path = $default;
			}
			$avatar = "<img alt='{$safe_alt}' src='{$path}' class='avatar avatar-{$size}' height='{$size}' width='{$size}' />";
		} else {
			$avatar = "<img alt='{$safe_alt}' src='{$default}' class='avatar avatar-{$size} avatar-default' height='{$size}' width='{$size}' />";
		}

		return $avatar;
	}

	/**
	 * Display blog avatar by user ID.
	 **/
	function display_posts( $user_ID, $size = '32', $deprecated = '' ) {
		global $wpdb;

		$blog_ID = get_usermeta( $user_ID, 'primary_blog' );
		if ( !empty( $blog_ID ) ) {
			$blog = $wpdb->get_var( $wpdb->prepare( "SELECT domain, path FROM {$wpdb->base_prefix}blogs WHERE blog_id = '%s'", $blog_ID ) );

			echo '<a href="http://' . $blog->domain . $blog->path . '" style="text-decoration:none">' .  get_avatar( $user_ID, $size, get_option( 'avatar_default' ) ) . '</a>';
		} else {
			echo get_avatar( $user_ID, $size, get_option( 'avatar_default' ) );
		}
	}

	/**
	 * Display blog avatar by user email.
	 **/
	function display_comments( $user_email, $size = '32', $deprecated = '' ) {
		global $wpdb;

		if ( !get_option('show_avatars') )
			return false;

		if ( $user_ID = $this->email_exists( $user_email ) ) {
			$blog_ID = get_usermeta( $user_ID, 'primary_blog' );
			if ( !empty( $blog_ID ) ) {
				$blog = $wpdb->get_var( $wpdb->prepare( "SELECT domain, path FROM {$wpdb->base_prefix}blogs WHERE blog_id = '%s'", $blog_ID ) );

				echo '<a href="http://' . $blog_domain . $blog_path . '" style="text-decoration:none">' . get_avatar( $user_email, $size, get_option('avatar_default') ) . '</a>';
			} else {
				// no primary blog definued
				echo get_avatar( $user_email, $size, get_option('avatar_default') );
			}
		} else {
			// not a local user - just grab a gravatar
			echo get_avatar( $user_email, $size, get_option('avatar_default') );
		}
	}

}
$ms_avatar =& new Avatars();

/**
 * WidgetAvatar Class
 */
class WA_Widget_Avatars extends WP_Widget {
	function WA_Widget_Avatars() {
		parent::WP_Widget( false, __( 'Avatars Widget', 'avatars' ) );
	}

	function widget( $args, $instance ) {
		global $wpdb;
		extract( $args );

		$title = apply_filters('widget_title', isset( $instance['title'] ) ? $instance['title'] : '');

		echo $before_widget;
		if ( $title )
			echo $before_title . $title . $after_title;
		echo get_blog_avatar( $wpdb->blogid, '128', '' );
		echo $after_widget;
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = isset( $new_instance['title'] ) ? strip_tags( $new_instance['title'] ) : '';
		return $instance;
	}

	function form( $instance ) {
		$title = isset( $instance['title'] ) ? esc_attr( $instance['title'] ) : '';
		?>
			<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e( 'Title:', 'avatars' ); ?> <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /></label></p>
		<?php
	}

} // class WA_Widget_Avatars

/**
 * Register widget.
 **/
function widget_avatars_init() {
	return register_widget( 'WA_Widget_Avatars' );
}
add_action( 'widgets_init', 'widget_avatars_init' );

/**
 * Return user avatar.
 **/
if( !function_exists( 'get_avatar' ) ):
function get_avatar( $id_or_email, $size = '96', $default = '', $alt = false ) {
	global $ms_avatar;
	return $ms_avatar->get_avatar( $id_or_email, $size, $default, $alt );
}
endif;

/**
 * Return blog avatar.
 **/
if( !function_exists( 'get_blog_avatar' ) ):
function get_blog_avatar( $id, $size = '96', $default = '', $alt = false ) {
	global $ms_avatar;
	return $ms_avatar->get_blog_avatar( $id, $size, $default, $alt );
}
endif;

/**
 * Display blog avatar by user ID.
 **/
function avatar_display_posts( $user_ID, $size = '32', $deprecated = '' ) {
	global $ms_avatar;
	return $ms_avatar->display_posts( $user_ID, $size, $deprecated );
}

/**
 * Display blog avatar by user email.
 **/
function avatar_display_comments( $user_email, $size = '32', $deprecated = '' ) {
	global $ms_avatar;
	return $ms_avatar->display_comments( $user_email, $size, $deprecated );
}


/**
 * Show notification if WPMUDEV Update Notifications plugin is not installed
 **/
if ( !function_exists( 'wdp_un_check' ) ) {
	add_action( 'admin_notices', 'wdp_un_check', 5 );
	add_action( 'network_admin_notices', 'wdp_un_check', 5 );

	function wdp_un_check() {
		if ( !class_exists( 'WPMUDEV_Update_Notifications' ) && current_user_can( 'edit_users' ) )
			echo '<div class="error fade"><p>' . __('Please install the latest version of <a href="http://premium.wpmudev.org/project/update-notifications/" title="Download Now &raquo;">our free Update Notifications plugin</a> which helps you stay up-to-date with the most stable, secure versions of WPMU DEV themes and plugins. <a href="http://premium.wpmudev.org/wpmu-dev/update-notifications-plugin-information/">More information &raquo;</a>', 'wpmudev') . '</p></div>';
	}
}
