<?php

/**
 * WidgetAvatar Class
 */
class WA_Widget_Avatars extends WP_Widget {


	function __construct() {
		parent::__construct( false, __( 'Avatars Widget', 'avatars' ) );
	}

	function widget( $args, $instance ) {
		extract( $args );

		$defaults = $this->get_default_instance();
		$instance = wp_parse_args( $instance, $defaults );
		
		$title = apply_filters('widget_title', isset( $instance['title'] ) ? $instance['title'] : '');

		echo $before_widget;
		if ( $title )
			echo $before_title . $title . $after_title;
		echo get_blog_avatar( get_current_blog_id(), $instance['size'], '' );
		echo $after_widget;
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = isset( $new_instance['title'] ) ? strip_tags( $new_instance['title'] ) : '';
		$instance['size'] = in_array( absint( $new_instance['size'] ), Avatars::get_avatar_sizes() ) ? absint( $new_instance['size'] ) : 128;
		return $instance;
	}

	function get_default_instance() {
		return array(
			'title' => '',
			'size' => 128
		);
	}

	function form( $instance ) {
		$defaults = $this->get_default_instance();

		$instance = wp_parse_args( $instance, $defaults );

		extract( $instance );

		$sizes = Avatars::get_avatar_sizes();

		?>
			<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e( 'Title:', 'avatars' ); ?> <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /></label></p>
			<p>
				<label for="<?php echo $this->get_field_id('size'); ?>"><?php _e( 'Avatar size:', 'avatars' ); ?> 
				<select id="<?php echo $this->get_field_id('size'); ?>" name="<?php echo $this->get_field_name('size'); ?>">
					<?php foreach ( $sizes as $avatar_size ): ?>
						<option value="<?php echo $avatar_size; ?>" <?php selected( $avatar_size == $size ); ?>><?php echo $avatar_size; ?></option>
					<?php endforeach; ?>
				</select>
		<?php
	}

} // class WA_Widget_Avatars