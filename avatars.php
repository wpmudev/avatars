<?php
/*
Plugin Name: Avatars For Multisite
Plugin URI: http://premium.wpmudev.org/project/avatars
Description: Allows users to upload 'user avatars' and 'blog avatars' which then can appear in comments and blog / user listings around the site
Author: WPMU DEV
Author URI: http://premium.wpmudev.org/
Version: 4.1.7
Network: true
Text Domain: avatars
WDP ID: 10
*/

/*
Copyright 2007-2014 Incsub (http://incsub.com)

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

/*
jQuery Ajax File Upload script by Jordan Feldstein (https://github.com/jfeldstein/jQuery.AjaxFileUpload.js/blob/master/README)
See avatars-files/js/signup.js for more information
*/

if( !is_multisite() )
	exit( __( 'The avatars plugin is only compatible with WordPress Multisite.', 'avatars' ) );

define( 'AVATARS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'AVATARS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) . 'avatars-files/' );
define( 'AVATARS_PLUGIN_URL', plugin_dir_url( __FILE__ ) . 'avatars-files/' );

require_once( AVATARS_PLUGIN_DIR . 'helpers.php' );

if ( defined( 'DOING_AJAX' ) && DOING_AJAX )
	include_once( 'avatars-files/ajax.php' );

global $wpmudev_notices;
$wpmudev_notices[] = array( 'id'=> 10,'name'=> 'Avatars', 'screens' => array( 'settings_page_blog-avatar', 'users_page_user-avatar', 'settings_page_edit-user-avatar-network' ) );
include_once( 'externals/wpmudev-dash-notification.php' );

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
	var $current_version = '4.1.6';

	private $avatars_dir;
	public $user_avatar_dir;
	public $blog_avatar_dir;

	private $avatars_url;
	private $user_avatar_url;
	private $blog_avatar_url;

	private $default_blog_avatar = 'identicon';

	private $local_default_avatar_url;
	private $local_default_avatar_dir;

	private $nginx;


	/**
	 * PHP5 constructor
	 **/
	function __construct() {
		global $wp_version;

		include_once( 'avatars-files/integration.php' );

		$this->network_top_menu = 'settings.php';
		$this->network_top_menu_slug = 'network/settings.php';

		$this->nginx = defined( 'AVATARS_USE_NGINX' ) && AVATARS_USE_NGINX == true;

		$upload_dir = self::get_avatars_upload_dir();

		$this->avatars_dir = $upload_dir['basedir'] . '/avatars';
		$this->avatars_url = $upload_dir['baseurl'] . '/avatars';

		$this->user_avatar_dir = $this->avatars_dir . '/user/';
		$this->blog_avatar_dir = $this->avatars_dir . '/blog/';

		$this->user_avatar_url = $this->avatars_url . '/user/';
		$this->blog_avatar_url = $this->avatars_url . '/blog/';

		$this->local_default_avatar_url = AVATARS_PLUGIN_URL . 'images/default-avatar-';
		$this->local_default_avatar_path = AVATARS_PLUGIN_DIR . 'images/default-avatar-';

		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		// Do we need to upgrade?
		add_action( 'init', array( &$this, 'maybe_upgrade' ) );

		// load plugin functions
		add_action( 'plugins_loaded', array( &$this, 'init_plugin' ) );

		// Load plugin language domain
		add_action( 'init', array( $this, 'load_textdomain' ) );		

	}

	public function get_avatar_dir() {
		return $this->avatars_dir;
	}

	public function get_avatar_url() {
		return $this->avatars_url;
	}

	public function activate() {
		update_site_option( 'avatars_plugin_version', $this->current_version );
	}

	public function deactivate() {
		update_site_option( 'avatars_plugin_version' );
	}

	public function load_textdomain() {
		$domain = 'avatars';
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

		load_textdomain( $domain, trailingslashit( WP_LANG_DIR ) . $domain . '/' . $domain . '-' . $locale . '.mo' );
		load_plugin_textdomain( $domain, false, dirname( plugin_basename( __FILE__ ) ) . '/avatars-files/languages' );

	}

	public static function get_avatars_upload_dir() {
		$main_blog_id = defined( 'BLOG_ID_CURRENT_SITE' ) ? BLOG_ID_CURRENT_SITE : 1;
		switch_to_blog( $main_blog_id );
		$upload_dir = wp_upload_dir();
		restore_current_blog();

		if ( preg_match('/blogs.dir.*$/', $upload_dir['basedir'] ) )
			$upload_dir['basedir'] = preg_replace( '/blogs.dir.*$/', 'uploads', $upload_dir['basedir'] );

		if ( preg_match('/blogs.dir.*$/', $upload_dir['baseurl'] ) )
			$upload_dir['baseurl'] = preg_replace( '/blogs.dir.*$/', 'uploads', $upload_dir['baseurl'] );

		return $upload_dir;
	}


	/**
	 * Admin error notices.
	 *
	 * Display errors when BuddyPress is installed and/or when avatars directory is not writable
	 **/
	function admin_errors() {

		// check if BuddyPress is installed
		if( defined( 'BP_VERSION' ) ) {

			$message = sprintf( __( 'BuddyPress has it\'s own avatar system. The Avatars plugin functions have been deactivated. Please remove the files.', 'avatars' ), $this->avatars_dir );
			echo "<div class='error'><p>$message</p></div>";

		} else {

			global $wp_filesystem;

			// check if old directory exists
			if ( is_dir( WP_CONTENT_DIR . '/avatars' ) && ! is_dir( $this->avatars_dir ) ) {
				

				require_once( ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php' );
				require_once( ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php' );

				// temporarily stores existing filesystem object
				if( isset( $wp_filesystem ) )
					$orig_filesystem = clone $wp_filesystem;

				$wp_filesystem = new WP_Filesystem_Direct( false );

				// Set the permission constants if not already set.
				if ( ! defined('FS_CHMOD_DIR') )
					define('FS_CHMOD_DIR', 0755 );
				if ( ! defined('FS_CHMOD_FILE') )
					define('FS_CHMOD_FILE', 0644 );

				if ( $wp_filesystem->mkdir( $this->avatars_dir, FS_CHMOD_DIR ) ) { // create new avatars directory

					if( copy_dir( WP_CONTENT_DIR . '/avatars', $this->avatars_dir ) ) { // copy files to new directory

						if( $wp_filesystem->delete( WP_CONTENT_DIR . '/avatars', true ) ) // attempt delete of old folder
							$message = sprintf( __( 'The Avatars plugin now store files in %s. Your old folder has been moved.', 'avatars' ), $this->avatars_dir );
						else
							$message = sprintf( __( 'The Avatars plugin now store files in %s. Your old folder has been copied. Please verify that everything is working fine and delete the old folder manually.', 'avatars' ), $this->avatars_dir );

					} else { // unsuccessful copy, warns user

							$message = sprintf( __( 'The Avatars plugin now store files in %s. Please make sure that directory is writable by the server.', 'avatars' ), $this->avatars_dir );

					}

				} else {

					$message = sprintf( __( 'The Avatars plugin now store files in %s. Please make sure its parent directory is writable by the server.', 'avatars' ), $this->avatars_dir );

				}

				// we are finished with our custom filesystem object, let's unset it
				unset( $wp_filesystem );

				// restore original filesystem object
				if( isset( $orig_filesystem ) )
					$wp_filesystem = clone $orig_filesystem;

				echo "<div class='error'><p>$message</p></div>";
			}

			// check if plugin directory exists
			if ( ! wp_mkdir_p( $this->avatars_dir ) ) {
				$message = sprintf( __( 'The Avatars plugin was unable to create directory %s. Is its parent directory writable by the server?', 'avatars' ), $this->avatars_dir );
				echo "<div class='error'><p>$message</p></div>";
			}

		}
	}

	/**
	 * Load Avatars functions after all other plugins are loaded
	 **/
	function init_plugin() {

		if( ! defined( 'BP_VERSION' ) ) {

			require_once( AVATARS_PLUGIN_DIR . 'front/widget.php' );

			require_once( AVATARS_PLUGIN_DIR . 'front/avatar-signup.php' );
			new Avatars_Signup();

			// add local avatar to the defaults list
			add_filter( 'avatar_defaults', array( &$this, 'defaults' ) );

			// add avatar to user profile page
			add_action( 'show_user_profile', array( &$this, 'to_profile' ) );
			add_action( 'edit_user_profile', array( &$this, 'to_profile' ) );

			// process avatar uploads
			add_action( 'admin_init', array( &$this, 'process' ) );

			// url rewriting
			add_action( 'init', array( &$this, 'flush_rules' ) );
			
			add_action( 'generate_rewrite_rules', array( &$this, 'rewrite_rules' ) );
			add_filter( 'query_vars', array( &$this, 'query_var' ) );
			add_action( 'template_redirect', array( &$this, 'load_avatar' ), -1 );
			

			// settings pages
			add_action( 'network_admin_menu', array( &$this, 'network_admin_page' ) );
			add_action( 'admin_menu', array( &$this, 'plug_pages' ) );
			add_action( 'user_admin_menu', array( &$this, 'user_plug_pages' ) );
			add_action( 'custom_menu_order', array( &$this, 'admin_menu' ) );
			add_filter( 'whitelist_options', array( &$this, 'whitelist' ) );

			// Delete user avatar when a user is deleted
			add_action( 'wpmu_delete_user', array( &$this, 'delete_user_avatar' ) );

			// Delete a blog avatar when a blog is deleted
			add_action( 'delete_blog', array( &$this, 'delete_blog_avatar' ) );

			// display admin notices
			add_action( 'admin_notices', array( &$this, 'admin_errors' ) );
				
			// Init widgets
			add_action( 'widgets_init', array( $this, 'widget_init' ) );

			// add necessary javascripts
			$page = isset( $_GET['page'] ) ? $_GET['page'] : '';
			if ( $page == 'blog-avatar' || $page == 'user-avatar' || $page == 'edit-user-avatar' || $page == 'edit-blog-avatar' ) {
				if ( isset($_GET['action']) && $_GET['action'] == 'upload_process' ) {
					add_action('admin_print_scripts', array($this, 'enqueue_scripts'), 1);
					add_action('admin_print_scripts', array($this, 'plug_scripts'), 99);
					//add_action( 'init', array( &$this, 'enqueue_scripts' ) );
				}
			}
		}
	}

	public function maybe_upgrade() {

		$version_saved = get_site_option( 'avatars_plugin_version', '3.8.1' );

		if ( $version_saved == $this->current_version )
			return;

		require_once( 'upgrades.php' );

		if ( version_compare( $version_saved, '3.9', '<' ) ) {
			avatars_upgrade_39();
		}

		if ( version_compare( $version_saved, '3.9.1', '<' ) ) {
			avatars_upgrade_391();
		}

		if ( version_compare( $version_saved, '3.9.3', '<' ) ) {
			avatars_upgrade_392();
		}

		update_site_option( 'avatars_plugin_version', $this->current_version );
	}

	private function delete_avatar_files( $id, $type ) {
		global $wp_filesystem;

		$sizes = self::get_avatar_sizes();

		$url = 'options-general.php';

		if ( ! function_exists( 'request_filesystem_credentials' ) )
			include_once( ABSPATH . '/wp-admin/includes/file.php' );

		if ( false === ( $creds = request_filesystem_credentials( $url, '', false, false, null ) ) )
			return; // stop processing here

		if ( ! WP_Filesystem( $creds ) )
			request_filesystem_credentials( $url, '', false, false, null );

		$folder = Avatars::encode_avatar_folder( $id );
		$dir = $type == 'user' ? $this->user_avatar_dir . $folder : $this->blog_avatar_dir . $folder;

		foreach ( $sizes as $size ) {
			$file = $dir . '/' . $type . '-' . $id . '-' . $size . '.png';
			if ( $wp_filesystem->is_file( $file ) )
				$wp_filesystem->delete( $file );

		}
	}

	public function delete_user_avatar( $user_id ) {
		$this->delete_avatar_files( $user_id, 'user' );
	}

	public function delete_blog_avatar( $blog_id ) {
		$this->delete_avatar_files( $blog_id, 'blog' );
	}

	public function widget_init() {
		return register_widget( 'WA_Widget_Avatars' );
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
			'avatar/(.+)' => 'index.php?avatar=' . $wp_rewrite->preg_index(1)
		);
		$wp_rewrite->rules = $new_rules + $wp_rewrite->rules;
	}

	/**
	 * Flush rewrite rules if the avatar rule was not previously added.
	 **/
	function flush_rules() {
		$rules = get_option( 'rewrite_rules' );

		if ( ! isset( $rules['avatar/(.+)'] ) )
			flush_rewrite_rules();
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
			$blog_avatars_path = $this->blog_avatar_dir;
			$user_avatars_path = $this->user_avatar_dir;
			$default_blog_avatar = $this->default_blog_avatar;
			$local_default_avatar_path = $this->local_default_avatar_path;
			$default_user_avatar = get_option( 'default_avatar' );
			require_once( AVATARS_PLUGIN_DIR . 'avatar.php' );
			exit;
		}
	}

	/**
	 * Add admin pages.
	 **/
	function plug_pages() {
		global $wpdb, $wp_version;

		add_options_page( __( 'Site Avatar', 'avatars' ), __( 'Site Avatar', 'avatars' ), 'manage_options', 'blog-avatar', array( &$this, 'page_edit_blog_avatar' ) );
		
		if ( current_user_can( 'edit_users' ) )
			add_submenu_page( 'users.php', __( 'Your Avatar', 'avatars' ), __( 'Your Avatar', 'avatars' ), 'manage_options', 'user-avatar', array( &$this, 'page_edit_user_avatar' ) );
		else
			add_submenu_page( 'profile.php', __( 'Your Avatar', 'avatars' ), __( 'Your Avatar', 'avatars' ), 'read', 'user-avatar', array( &$this, 'page_edit_user_avatar' ) );
		
		if ( is_super_admin() && isset( $_GET['page'] ) && $_GET['page'] == 'edit-user-avatar' ) {
			add_action( 'admin_page_edit', 'page_site_admin_edit_user_avatar' );
			add_submenu_page( $this->network_top_menu, __( 'Edit User Avatar', 'avatars' ), __( 'Edit User Avatar', 'avatars' ), 'manage_network_options', 'edit-user-avatar', array( &$this, 'page_site_admin_edit_user_avatar' ) );
		}
	}
	
	function user_plug_pages() {
		add_submenu_page( 'profile.php', __( 'Your Avatar', 'avatars' ), __( 'Your Avatar', 'avatars' ), 'exist', 'user-avatar', array( &$this, 'page_edit_user_avatar' ) );
	}

	/**
	 * Add network admin page.
	 **/
	function network_admin_page() {
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
		<h3><?php _e('Avatar Settings', 'avatars');?></h3>
		<table class="form-table">
			<tbody>
				<tr>
					<th><label for="avatar">Avatar</label></th>
					<td><p><?php _e( 'This is your "user" avatar. It will appear whenever you leave comments, post in the forums and when your popular posts are displayed around the site.', 'avatars' ); ?></p>
					<p><?php echo get_avatar( $profileuser->ID ); ?><br></p>
					<?php
					if( IS_PROFILE_PAGE )
						if ( is_user_admin() )
							echo '<a class="button" href="' . admin_url( "user/$submenu_file?page=user-avatar" ) . '">' . __( 'Change Avatar', 'avatars' ) . '</a></td>';
						else
							echo '<a class="button" href="' . admin_url( "$submenu_file?page=user-avatar" ) . '">' . __( 'Change Avatar', 'avatars' ) . '</a></td>';
					else
						echo '<a class="button" href="' . admin_url( "$this->network_top_menu_slug?page=edit-user-avatar&uid=$profileuser->ID" ) . '">' . __( 'Change Avatar', 'avatars' ) . '</a></td>';
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
		<script type="text/javascript">
			jQuery(document).ready(function() {

				function onEndCrop( coords ) {
					jQuery( '#x1' ).val(coords.x);
					jQuery( '#y1' ).val(coords.y);
					jQuery( '#width' ).val(coords.w);
					jQuery( '#height' ).val(coords.h);
				}

				var xinit = 256;
				var yinit = 256;
				var ratio = xinit / yinit;
				var ximg = jQuery('#upload').width();
				var yimg = jQuery('#upload').height();

				if ( yimg < yinit || ximg < xinit ) {
					if ( ximg / yimg > ratio ) {
						yinit = yimg;
						xinit = yinit * ratio;
					} else {
						xinit = ximg;
						yinit = xinit / ratio;
					}
				}

				jQuery('#upload').imgAreaSelect({
					handles: true,
					keys: true,
					show: true,
					x1: 0,
					y1: 0,
					x2: xinit,
					y2: yinit,
					aspectRatio: '1:1',
					onInit: function () {
						jQuery('#width').val(xinit);
						jQuery('#height').val(yinit);
					},
					onSelectChange: function(img, c) {
						jQuery('#x1').val(c.x1);
						jQuery('#y1').val(c.y1);
						jQuery('#width').val(c.width);
						jQuery('#height').val(c.height);
					}
				});
			});

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
		wp_enqueue_script('imgareaselect');
		wp_enqueue_style('imgareaselect');
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
		if ( $size != '16' && $size != '32' && $size != '48' && $size != '96' && $size != '128' && $size != '192' && $size != '256' ) {
			if ( $size < 25 ) {
				$size = 16;
			} else if ( $size > 24 && $size < 41 ) {
				$size = 32;
			} else if ( $size > 40 && $size < 73 ) {
				$size = 48;
			} else if ( $size > 72 && $size < 113 ) {
				$size = 96;
			} else if ( $size > 112 && $size < 153 ) {
				$size = 128;
			} else if ( $size > 152 && $size < 213 ) {
				$size = 192;
			} else if ( $size > 212 ) {
				$size = 256;
			}
		}
		return $size;
	}

	/**
	 * Crops an image
	 * 
	 * @param String $type user or blog
	 * @param Integer $id ID of the user or blog
	 * @param String $tmp_file Temporary file
	 * @param Integer $x1 The start x position to crop from.
	 * @param Integer $y1 The start y position to crop from.
	 * @param Integer $width The width to crop.
	 * @param Integer $height The height to crop.
	 * @param String $avatar_path Destination path
	 * 
	 */
	function crop_image( $type = 'user', $id, $tmp_file, $x1, $y1, $width, $height, $avatar_path ) {

		if ( ! in_array( $type, array( 'user', 'blog' ) ) )
			$type = 'user';

		// Avatar possible sizes
		$sizes = self::get_avatar_sizes();
		foreach ( $sizes as $avatar_size ) {
			// Destination filename
			$dst_file = $avatar_path . "$type-$id-$avatar_size.png";

			$this->delete_temp( $dst_file );

			$cropped = wp_crop_image( $tmp_file, $x1, $y1, $width, $height, $avatar_size, $avatar_size, false, $dst_file );

			if ( ! $cropped || is_wp_error( $cropped ) )
				wp_die( __( 'Image could not be processed. Please go back and try again.' ), __( 'Image Processing Error' ) );
		}

		$this->delete_temp( $tmp_file );

		
	}

	public static function encode_avatar_folder( $id ) {
		return substr( md5( $id ), 0, 3 );
	}

	public static function get_avatar_sizes() {
		return array( 16, 32, 48, 96, 128, 192, 256 );
	}

	private function upload_image( $file, $avatar_path, $image_path, $avatar_id, $av_type ) {

		$type = $file['type'];
		$file_name = $file['name'];
		$tmp_name = $file['tmp_name'];

		if( move_uploaded_file( $tmp_name, $image_path ) ) {
			chmod( $image_path, 0777 );
		}
		else {
			_e( 'There was an error uploading the file, please try again.', 'avatars' );
			wp_die();
		}

		list( $avatar_width, $avatar_height, $avatar_type, $avatar_attr ) = getimagesize( $image_path );

		if ( $type == "image/gif"){
			$avatar_image_type = 'gif';
		}
		if ( $type == "image/jpeg"){
			$avatar_image_type = 'jpeg';
		}
		if ( $type == "image/pjpeg"){
			$avatar_image_type = 'jpeg';
		}
		if ( $type == "image/jpg"){
			$avatar_image_type = 'jpeg';
		}
		if ( $type == "image/png"){
			$avatar_image_type = 'png';
		}
		if ( $type == "image/x-png"){
			$avatar_image_type = 'png';
		}

		if ($avatar_image_type == 'jpeg')
			$im = ImageCreateFromjpeg( $avatar_path . basename( $file_name ) );

		if ($avatar_image_type == 'png')
			$im = ImageCreateFrompng( $avatar_path . basename( $file_name ) );

		if ($avatar_image_type == 'gif')
			$im = ImageCreateFromgif( $avatar_path . basename( $file_name ) );

		if (!$im) {
			echo __( 'There was an error uploading the file, please try again.', 'avatars' );
			return false;
		}

		$sizes = self::get_avatar_sizes();

		foreach( $sizes as $avatar_size ) {
			$im_dest = imagecreatetruecolor( $avatar_size, $avatar_size );
			imagecopyresampled( $im_dest, $im, 0, 0, 0, 0, $avatar_size, $avatar_size, $avatar_width, $avatar_height );
			if( 'png' == $avatar_image_type )
				imagesavealpha( $im_dest, true );
			imagepng( $im_dest, $avatar_path . "$av_type-$avatar_id-$avatar_size.png" );
		}

		$this->delete_temp( $avatar_path . basename( $file_name ) );
	}

	/**
	 * Process avatar upload.
	 **/
	function process() {
		global $plugin_page, $current_site;

		$user_ID = get_current_user_id();
		$action = isset( $_GET[ 'action' ] ) ? $_GET[ 'action' ] : '';

		// blog avatar processing
		if( 'blog-avatar' == $plugin_page ) {
			$blog_id = get_current_blog_id();
			$avatar_path = $this->blog_avatar_dir . self::encode_avatar_folder( $blog_id ) . '/';
			switch( $action ) {
				case 'upload_process':
					if ( isset( $_POST['Reset'] ) ) {

						$this->delete_temp( $avatar_path . 'blog-' . $blog_id . '-16.png');
						$this->delete_temp( $avatar_path . 'blog-' . $blog_id . '-32.png');
						$this->delete_temp( $avatar_path . 'blog-' . $blog_id . '-48.png');
						$this->delete_temp( $avatar_path . 'blog-' . $blog_id . '-96.png');
						$this->delete_temp( $avatar_path . 'blog-' . $blog_id . '-128.png');
						$this->delete_temp( $avatar_path . 'blog-' . $blog_id . '-192.png');
						$this->delete_temp( $avatar_path . 'blog-' . $blog_id . '-256.png');

						wp_redirect( admin_url( 'options-general.php?page=blog-avatar&updated=true' ) );
						exit;

					} elseif ( isset( $_POST['Alternative'] ) ) {
						// Alternative Upload

						$uploaded_file = $_FILES['avatar_file'];
						$wp_filetype = wp_check_filetype_and_ext( $uploaded_file['tmp_name'], $uploaded_file['name'], false );

						if ( ! wp_match_mime_types( 'image', $wp_filetype['type'] ) )
							wp_die( '<div class="error"><p>' . __( 'The uploaded file is not a valid image. Please try again.' ) . '</p></div>' );


						if ( ! is_dir( $avatar_path ) )
							wp_mkdir_p( $avatar_path );

						$image_path = $avatar_path . basename( $_FILES['avatar_file']['name'] );

						$this->upload_image( $_FILES['avatar_file'], $avatar_path, $image_path, $blog_id, 'blog' );

						if ( function_exists( 'moderation_image_insert' ) ) {
							$protocol = is_ssl() ? 'https://' : 'http://';
							moderation_image_insert('avatar', $blog_id, $user_ID, $avatar_path . 'blog-' . $blog_id . '-128.png', $protocol . $current_site->domain . $current_site->path . 'avatar/blog-' . $blog_id . '-128.png');
						}

						wp_redirect( admin_url( 'options-general.php?page=blog-avatar&updated=true' ) );
						exit;
					}
				break;

				case 'crop_process':
					$avatar_path = $this->blog_avatar_dir . self::encode_avatar_folder( $blog_id ) . '/';

					$filename = stripslashes_deep( $_POST['file_name'] );
					$tmp_file = $avatar_path . $filename;

					$this->crop_image( 'blog', $blog_id, $tmp_file, (int)$_POST['x1'], (int)$_POST['y1'], (int)$_POST['width'], (int)$_POST['height'], $avatar_path );
					$protocol = is_ssl() ? 'https://' : 'http://';
					if ( function_exists( 'moderation_image_insert' ) ) {
						moderation_image_insert('avatar', $blog_id, $user_ID, $avatar_path . 'blog-' . $blog_id . '-128.png', $protocol . $current_site->domain . $current_site->path . 'avatar/blog-' . $blog_id . '-128.png');
					}

					wp_redirect( admin_url( 'options-general.php?page=blog-avatar&updated=true' ) );
					exit;
				break;

				default:
				break;
			}
		}

		if( 'user-avatar' == $plugin_page || 'edit-user-avatar' == $plugin_page ) {

			
			$user_ID = 'edit-user-avatar' == $plugin_page ? $_GET['uid'] : get_current_user_id();
			$avatar_path = $this->user_avatar_dir . self::encode_avatar_folder( $user_ID ) . '/';

			switch( $action ) {
				case 'upload_process':
					if ( isset( $_POST['Reset'] ) ) {

						$this->delete_temp( $avatar_path . 'user-' . $user_ID . '-16.png');
						$this->delete_temp( $avatar_path . 'user-' . $user_ID . '-32.png');
						$this->delete_temp( $avatar_path . 'user-' . $user_ID . '-48.png');
						$this->delete_temp( $avatar_path . 'user-' . $user_ID . '-96.png');
						$this->delete_temp( $avatar_path . 'user-' . $user_ID . '-128.png');
						$this->delete_temp( $avatar_path . 'user-' . $user_ID . '-192.png');
						$this->delete_temp( $avatar_path . 'user-' . $user_ID . '-256.png');

						if ( 'user-avatar' == $plugin_page ) {
							$link = add_query_arg(
								array(
									'updated' => 'true',
									'updatedmsg' => urlencode( __( 'Avatar reset.', 'avatars' ) )
								)
							);
							$link = remove_query_arg( 'action' );
						}
						else {
							$link = admin_url( "$this->network_top_menu_slug?page=edit-user-avatar&uid={$user_ID}&updated=true&updatedmsg=" . urlencode( __( 'Avatar reset.', 'avatars' ) ) );
						}

						wp_redirect( $link );
						exit;
						
					} elseif ( isset( $_POST['Alternative'] ) ) {

						if ( ! is_dir( $avatar_path ) )
							wp_mkdir_p( $avatar_path );

						$image_path = $avatar_path . basename($_FILES['avatar_file']['name']);

						$this->upload_image( $_FILES['avatar_file'], $avatar_path, $image_path, $user_ID, 'user' );

						if ( function_exists( 'moderation_image_insert' ) && 'user-avatar' == $plugin_page ) {
							$protocol = is_ssl() ? 'https://' : 'http://';
							moderation_image_insert('avatar', get_current_blog_id(), $user_ID, $avatar_path . 'user-' . $user_ID . '-128.png', $protocol . $current_site->domain . $current_site->path . 'avatar/user-' . $user_ID . '-128.png');
						}

						if ( 'user-avatar' == $plugin_page ) {
							$link = add_query_arg(
								array(
									'updated' => 'true',
									'updatedmsg' => urlencode( __( 'Avatar updated', 'avatars' ) )
								)
							);

							$link = remove_query_arg( 'action' );
						}
						else {
							$link = admin_url( "$this->network_top_menu_slug?page=edit-user-avatar&uid={$user_ID}&updated=true&updatedmsg=" . urlencode( __( 'Avatar updated.', 'avatars' ) ) );
						}
						
						wp_redirect( $link );
						exit;
					}
				break;

				case 'crop_process':

					$filename = stripslashes_deep( $_POST['file_name'] );
					$tmp_file = $avatar_path . $filename;

					$this->crop_image( 'user', $user_ID, $tmp_file, (int)$_POST['x1'], (int)$_POST['y1'], (int)$_POST['width'], (int)$_POST['height'], $avatar_path );
					
					if ( function_exists( 'moderation_image_insert' ) && 'user-avatar' == $plugin_page ) {
						$protocol = is_ssl() ? 'https://' : 'http://';
						moderation_image_insert( 'avatar', get_current_blog_id(), $user_ID, $avatar_path . 'user-' . $user_ID . '-128.png', $protocol . $current_site->domain . $current_site->path . 'avatar/user-' . $user_ID . '-128.png');
					}		
					
					if ( 'user-avatar' == $plugin_page ) {
						$link = add_query_arg(
							array(
								'updated' => 'true',
								'updatedmsg' => urlencode( __( 'Avatar updated', 'avatars' ) )
							)
						);

						$link = remove_query_arg( 'action' );
					}
					else {
						$link = admin_url( "$this->network_top_menu_slug?page=edit-user-avatar&uid={$user_ID}&updated=true&updatedmsg=" . urlencode( __( 'Avatar updated.', 'avatars' ) ) );
					}
					
					wp_redirect( $link );
					exit;
				break;

			}
		}
	}

	/**
	 * Return content for Edit Blog Avatar page.
	 **/
	function page_edit_blog_avatar() {

		$blog_id = get_current_blog_id();
		if( !current_user_can('manage_options') ) {
			echo '<p>' . __( 'Nice Try...', 'avatars' ) . '</p>';  //If accessed properly, this message doesn't appear.
			return;
		}

		echo '<div class="wrap">';
		$action = isset( $_GET[ 'action' ] ) ? $_GET[ 'action' ] : '';

		if( 'upload_process' == $action && ! isset( $_POST['Alternative'] ) ) {

			$uploaded_file = $_FILES['avatar_file'];
			$wp_filetype = wp_check_filetype_and_ext( $uploaded_file['tmp_name'], $uploaded_file['name'], false );
			if ( ! wp_match_mime_types( 'image', $wp_filetype['type'] ) )
				wp_die( '<div class="error"><p>' . __( 'The uploaded file is not a valid image. Please try again.' ) . '</p></div>' );

			$avatar_path = $this->blog_avatar_dir . substr(md5($blog_id), 0, 3) . '/';

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
			<img src="<?php echo $this->blog_avatar_url . self::encode_avatar_folder( $blog_id ) . '/' . $_FILES['avatar_file']['name']; ?>" id="upload" width="<?php echo $avatar_width; ?>" height="<?php echo $avatar_height; ?>" />
			</div>

			<input type="hidden" name="file_path" id="file_path" value="<?php echo $avatar_path; ?>" />
			<input type="hidden" name="file_name" id="file_name" value="<?php echo basename( $_FILES['avatar_file']['name'] ); ?>" />
			<input type="hidden" name="image_type" id="image_type" value="<?php echo $avatar_image_type; ?>" />
			<input type="hidden" name="x1" id="x1" value="0"/>
			<input type="hidden" name="y1" id="y1" value="0"/>
			<input type="hidden" name="width" id="width" />
			<input type="hidden" name="height" id="height" />

			<p class="submit">
			<input type="submit" class="button-primary" value="<?php _e( 'Crop Image', 'avatars' ); ?>" />
			</p>

			</form>
			<?php
		} else {
			?>
			<h2><?php _e( 'Site Avatar', 'avatars' ) ?></h2>
			<form action="options-general.php?page=blog-avatar&action=upload_process" method="post" enctype="multipart/form-data">
				<p><?php _e( 'This is your "site" avatar. It will appear whenever your blog is listed (for example, on the front page of the site).', 'avatars' ); ?></p>
				<p><?php echo get_blog_avatar( $blog_id, '96', '' ); ?></p>

				<h3><?php _e( 'Upload New Avatar', 'avatars' ); ?></h3>
				<p>
				  <input name="avatar_file" id="avatar_file" size="20" type="file">
				  <input type="hidden" name="MAX_FILE_SIZE" value="100000" />
				</p>
				<p><?php _e( 'Allowed Formats: jpeg, gif, and png', 'avatars' ); ?></p>
				<p><?php _e( 'If you are experiencing problems cropping your image please use the alternative upload method ("Alternative Upload" button).', 'avatars' ); ?></p>
				<p class="submit">
				  <input class="button-primary" name="Submit" value="<?php _e( 'Upload', 'avatars' ) ?>" type="submit">
				  <input class="button" name="Alternative" value="<?php _e( 'Alternative Upload', 'avatars' ) ?>" type="submit">
				  <input class="button-secondary" name="Reset" value="<?php _e( 'Reset', 'avatars' ) ?>" type="submit">
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
		global $current_site;

		$user_ID = get_current_user_id();
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

			$avatar_dir = $this->user_avatar_dir . self::encode_avatar_folder( $user_ID ) . '/';

			if ( ! is_dir( $avatar_dir ) )
				wp_mkdir_p( $avatar_dir );

			$image_path = $avatar_dir . basename($_FILES['avatar_file']['name']);
			
			if( move_uploaded_file( $_FILES['avatar_file']['tmp_name'], $image_path ) ) {
				chmod( $image_path, 0777 );
			} else{
				echo __( "There was an error uploading the file, please try again.", 'avatars' );
				wp_die();
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

			$link = add_query_arg( 'action','crop_process' );
			?>
			<form method="post" action="<?php echo $link; ?>">

			<p><?php _e( 'Choose the part of the image you want to use as the avatar.', 'avatars' ); ?></p>
			<div id="testWrap">
			<img src="<?php echo $this->user_avatar_url . self::encode_avatar_folder( $user_ID ) . '/' . $_FILES['avatar_file']['name']; ?>" id="upload" width="<?php echo $avatar_width; ?>" height="<?php echo $avatar_height; ?>" />
			</div>

			<input type="hidden" name="file_path" id="file_path" value="<?php echo $avatar_dir; ?>" />
			<input type="hidden" name="file_name" id="file_name" value="<?php echo basename( $_FILES['avatar_file']['name']); ?>" />
			<input type="hidden" name="image_type" id="image_type" value="<?php echo $avatar_image_type; ?>" />
			<input type="hidden" name="x1" id="x1" value="0"/>
			<input type="hidden" name="y1" id="y1" value="0" />
			<input type="hidden" name="width" id="width" />
			<input type="hidden" name="height" id="height" />

			<p class="submit">
			<input class="button-primary" type="submit" value="<?php _e( 'Crop Image', 'avatars' ); ?>" />
			</p>

			</form>
			<?php

		} else {
			?>
			<h2><?php _e( 'Your Avatar', 'avatars' ) ?></h2>
			<?php

			$link = add_query_arg( 'action','upload_process' );
			?>
			<form action="<?php echo $link; ?>" method="post" enctype="multipart/form-data">
			<p>
			<?php
			echo get_avatar( $user_ID, '96', get_option('avatar_default') );
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
			  <input class="button-primary" name="Submit" value="<?php _e( 'Upload', 'avatars' ) ?>" type="submit">
			  <input class="button" name="Alternative" value="<?php _e( 'Alternative Upload', 'avatars' ) ?>" type="submit">
			  <input class="button-secondary" name="Reset" value="<?php _e( 'Reset', 'avatars' ) ?>" type="submit">
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

			$uploaded_file = $_FILES['avatar_file'];
			$wp_filetype = wp_check_filetype_and_ext( $uploaded_file['tmp_name'], $uploaded_file['name'], false );

			if ( ! wp_match_mime_types( 'image', $wp_filetype['type'] ) )
				wp_die( '<div class="error"><p>' . __( 'The uploaded file is not a valid image. Please try again.' ) . '</p></div>' );

			// Standard Upload
			$avatar_dir = $this->user_avatar_dir . Avatars::encode_avatar_folder( $_GET['uid'] ) . '/';

			if ( !is_dir($avatar_dir) ) {
				wp_mkdir_p( $avatar_dir );
			}

			$image_path = $avatar_dir . basename($_FILES['avatar_file']['name']);

			if(move_uploaded_file($_FILES['avatar_file']['tmp_name'], $image_path)) {
				//file uploaded...
				chmod($image_path, 0777);
			} else{
				echo __( "There was an error uploading the file, please try again.", 'avatars' );
				wp_die();
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
			<img src="<?php echo $this->user_avatar_url . Avatars::encode_avatar_folder( $_GET['uid'] ) . '/' . $_FILES['avatar_file']['name']; ?>" id="upload" width="<?php echo $avatar_width; ?>" height="<?php echo $avatar_height; ?>" />
			</div>

			<input type="hidden" name="file_path" id="file_path" value="<?php echo $avatar_dir; ?>" />
			<input type="hidden" name="file_name" id="file_name" value="<?php echo basename( $_FILES['avatar_file']['name']); ?>" />
			<input type="hidden" name="image_type" id="image_type" value="<?php echo $avatar_image_type; ?>" />
			<input type="hidden" name="x1" id="x1" value="0" />
			<input type="hidden" name="y1" id="y1" value="0" />
			<input type="hidden" name="width" id="width" />
			<input type="hidden" name="height" id="height" />

			<p class="submit">
			<input class="button-primary" type="submit" value="<?php _e( 'Crop Image', 'avatars' ); ?>" />
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
				  <input class="button-primary" name="Submit" value="<?php _e( 'Upload', 'avatars' ) ?>" type="submit">
				  <input class="button" name="Alternative" value="<?php _e( 'Alternative Upload', 'avatars' ) ?>" type="submit">
				  <input class="button-secondary" name="Reset" value="<?php _e( 'Reset', 'avatars' ) ?>" type="submit">
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
		global $wp_filesystem;

		$url = 'options-general.php';

		if ( ! function_exists( 'request_filesystem_credentials' ) )
			include_once( ABSPATH . '/wp-admin/includes/file.php' );

		if ( false === ( $creds = request_filesystem_credentials( $url, '', false, false, null ) ) ) {
			return; // stop processing here
		}

		if ( ! WP_Filesystem( $creds ) ) {
			request_filesystem_credentials( $url, '', false, false, null );
		}

		if ( $wp_filesystem->is_file( $file ) )
			$wp_filesystem->delete( $file );
	}


	/**
	 * Return user avatar.
	 **/
	function get_avatar( $id_or_email, $size = '96', $default = '', $alt = false, $args = false ) {
		global $current_site, $current_screen;

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

		$force_default = isset( $args['force_default'] ) && $args['force_default'];

		if ( 'local_default' == $default ) {
			$default = $this->local_default_avatar_url . $size . '.png';
		} elseif ( 'mystery' == $default ) {
			$default = "$host/avatar/ad516503a11cd5ca435acc9bb6523536?s={$size}";
		} // ad516503a11cd5ca435acc9bb6523536 == md5('unknown@gravatar.com')
		elseif ( 'blank' == $default ) {
			$default = includes_url( 'images/blank.gif' );
		} elseif ( ! $force_default && ! empty( $email ) && 'gravatar_default' == $default ) {
			$default = '';
		} elseif ( 'gravatar_default' == $default ) {
			$default = "$host/avatar/s={$size}";
		} elseif ( empty( $email ) || $force_default ) {
			$default = "$host/avatar/?d=$default&amp;s={$size}";
		} elseif ( strpos( $default, 'http://' ) === 0 ) {
			$default = add_query_arg( 's', $size, $default );
		}


		if ( !empty($email) && ! $force_default ) {
			if ( $avatar_user_id = $this->email_exists( $email ) ) { // email exists locally - check if file exists
				$file = $this->user_avatar_dir . Avatars::encode_avatar_folder( $avatar_user_id ) . '/user-' . $avatar_user_id . '-' . $size . '.png';

				if ( is_file( $file ) && ! ( isset( $current_screen->id ) && 'options-discussion' == $current_screen->id ) ) { // if file exists and we are not on the discussion options page
					if (defined('AVATARS_USE_ACTUAL_FILES') && AVATARS_USE_ACTUAL_FILES) {
						$info = wp_upload_dir();
						$avatars_url = trailingslashit($info['baseurl']) . basename(dirname($this->user_avatar_dir));

						$out = preg_replace('/' . preg_quote(dirname($this->user_avatar_dir) , '/') . '/', $avatars_url, $file);
					} else {

						$protocol = ( is_ssl() ) ? 'https://' : 'http://';

						if ( ! $this->nginx ) {
							$out = $protocol . $current_site->domain . $current_site->path . 'avatar/user-' . $avatar_user_id . '-' . $size . '.png';
						}
						else {
							$out = $protocol . $current_site->domain . $current_site->path;
							$out = add_query_arg( 'avatar', 'user-' . $avatar_user_id . '-' . $size . '.png', $out );
						}
					}

					if ( isset( $_GET['page'] ) && 'user-avatar' == $_GET['page'] )
						$out = add_query_arg( 'rand', md5(time()), $out );					
					/*
					if ( isset( $_GET['page'] ) && 'user-avatar' == $_GET['page'] )
						$out = 'http://' . $current_site->domain . $current_site->path . 'avatar/user-' . $avatar_user_id . '-' . $size . '.png?rand=' . md5( time() );
					else
						$out = 'http://' . $current_site->domain . $current_site->path . 'avatar/user-' . $avatar_user_id . '-' . $size . '.png';
					*/
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

			if ( isset( $args['return_path'] ) && $args['return_path'] )
				return $out;

		} else {
			$avatar = "<img alt='{$safe_alt}' src='{$default}' class='avatar avatar-{$size} photo avatar-default' height='{$size}' width='{$size}' />";

			if ( isset( $args['return_path'] ) && $args['return_path'] )
				return $default;
		}

		return apply_filters( 'get_avatar', $avatar, $id_or_email, $size, $default, $alt );
	}

	/**
	 * Return blog avatar.
	 **/
	function get_blog_avatar( $id, $size = '96', $default = '', $alt = '' ) {
		if ( false === $alt )
			$safe_alt = '';
		else
			$safe_alt = esc_attr( $alt );

		if ( !is_numeric( $size ) ) {
			$size = '96';
		}
		$size = $this->size_map( $size );

		$avatar_url = $this->get_blog_avatar_url( $id, $size, $default );

		$class = apply_filters( 'avatars_img_class', "avatar avatar-" . $size );
		$avatar = "<img alt='{$safe_alt}' src='{$avatar_url}' class='{$class}' height='{$size}' width='{$size}' />";

		return $avatar;
	}

	function get_blog_avatar_url( $id, $size = 96, $default = '' ) {
		global $current_site;

		$_default = $default;

		if ( empty( $default ) ) {
			$default = get_option('avatar_default');
			if ( empty( $default ) ) {
				$default = $this->default_blog_avatar;
			}
		}

		if ( $default == 'local_default' )
			$default = $this->local_default_avatar_url . $size . '.png';
		else if ( $default == 'gravatar_default' )
			$default = 'https://www.gravatar.com/avatar/' . md5($id) . '?r=G&s=' . $size;
		elseif( 'blank' == $default )
			$default = includes_url( 'images/blank.gif' );
		else if ( $default == 'identicon' )
			$default = 'https://www.gravatar.com/avatar/' . md5($id) . '?r=G&d=identicon&s=' . $size;
		else if ( $default == 'wavatar' )
			$default = 'https://www.gravatar.com/avatar/' . md5($id) . '?r=G&d=wavatar&s=' . $size;
		else if ( $default == 'monsterid' )
			$default = 'https://www.gravatar.com/avatar/' . md5($id) . '?r=G&d=monsterid&s=' . $size;
		else if ( $default == 'mystery' )
			$default = 'https://www.gravatar.com/avatar/' . md5($id) . '?r=G&d=mm&s=' . $size;
		else {
			$admin_email = get_bloginfo( 'admin_email' );
			$default = $this->get_avatar( $admin_email, $size, $_default, false, array( 'return_path' => true ) );
		}

		if ( !empty($id) ) {
			//user exists locally - check if avatar exists

			$file = $this->blog_avatar_dir . self::encode_avatar_folder( $id ) . '/blog-' . $id . '-' . $size . '.png';

			if ( is_file( $file ) ) {
				if (defined('AVATARS_USE_ACTUAL_FILES') && AVATARS_USE_ACTUAL_FILES) {
					$info = wp_upload_dir();
					$avatars_url = trailingslashit($info['baseurl']) . basename(dirname($this->blog_avatar_dir));
					$path = preg_replace('/' . preg_quote(ABSPATH . dirname($this->blog_avatar_dir) , '/') . '/', $avatars_url, $file);
				} else {
					$protocol = ( is_ssl() ) ? 'https://' : 'http://';
					if ( ! $this->nginx ) {
						$path = $protocol . $current_site->domain . $current_site->path . 'avatar/blog-' . $id . '-' . $size . '.png';
					}
					else {
						$path = $protocol . $current_site->domain . $current_site->path;
						$path = add_query_arg( 'avatar', 'blog-' . $id . '-' . $size . '.png', $path );
					}
				}
				if ( isset( $_GET['page'] ) && 'blog-avatar' == $_GET['page']  || isset( $_GET['page'] ) && $_GET['page'] == 'edit-blog-avatar' )
					$path = add_query_arg( 'rand', md5(time()), $path );

			} else {
				$path = $default;
			}

			return $path;

		} else {
			add_filter( 'avatars_img_class', 'add_default_img_class' );
			return $default;
		}
	}

	function add_default_img_class( $class ) {
		return $class . ' avatar-default';
	}

	/**
	 * Display blog avatar by user ID.
	 **/
	function display_posts( $user_ID, $size = '32', $deprecated = '' ) {
		$blog_ID = get_user_meta( $user_ID, 'primary_blog', true );
		if ( !empty( $blog_ID ) ) {
			$blog_url = get_site_url($blog_ID);
			echo '<a href="' . esc_url($blog_url) . '" style="text-decoration:none">' .  get_avatar( $user_ID, $size, get_option( 'avatar_default' ) ) . '</a>';
		} else {
			echo get_avatar( $user_ID, $size, get_option( 'avatar_default' ) );
		}
	}

	/**
	 * Display blog avatar by user email.
	 **/
	function display_comments( $user_email, $size = '32', $deprecated = '' ) {

		if ( !get_option('show_avatars') )
			return false;

		if ( $user_ID = $this->email_exists( $user_email ) ) {
			$blog_ID = get_user_meta( $user_ID, 'primary_blog', true );
			if ( !empty( $blog_ID ) ) {
				$blog_url = get_site_url($blog_ID);
				echo '<a href="' . esc_url($blog_url) . '" style="text-decoration:none">' . get_avatar( $user_email, $size, get_option('avatar_default') ) . '</a>';
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

global $ms_avatar;
$ms_avatar = new Avatars();
