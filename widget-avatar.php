<?php
/*
Plugin Name: Avatars (Widget)
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

function widget_avatar_init() {
	global $wpdb, $current_site;
	// Check for the required API functions
	if ( !function_exists('register_sidebar_widget') || !function_exists('register_widget_control') )
		return;

	// This prints the widget
	function widget_avatar($args) {
		global $wpdb, $current_site;
		extract($args);
		$defaults = array('count' => 10, 'username' => 'wordpress');
		$options = (array) get_option('widget_avatar');

		foreach ( $defaults as $key => $value )
			if ( !isset($options[$key]) )
				$options[$key] = $defaults[$key];


		?>
		<?php echo $before_widget; ?>
			<?php echo $before_title . "" . $after_title; ?>
			<center>
            <?php
			echo get_blog_avatar($wpdb->blogid,'128','');
			?>
			</center>
		<?php echo $after_widget; ?>
<?php
	}

	// Tell Dynamic Sidebar about our new widget and its control
	register_sidebar_widget(array(__('Blog Avatar'), 'widgets'), 'widget_avatar');
	
}

// Delay plugin execution to ensure Dynamic Sidebar has a chance to load first
add_action('widgets_init', 'widget_avatar_init');

?>