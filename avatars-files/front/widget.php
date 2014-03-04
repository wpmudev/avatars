<?php

/**
 * WidgetAvatar Class
 */
class WA_Widget_Avatars extends WP_Widget {
	function WA_Widget_Avatars() {
		parent::WP_Widget( false, __( 'Avatars Widget', 'avatars' ) );
	}

	function widget( $args, $instance ) {
		extract( $args );

		$title = apply_filters('widget_title', isset( $instance['title'] ) ? $instance['title'] : '');

		echo $before_widget;
		if ( $title )
			echo $before_title . $title . $after_title;
		echo get_blog_avatar( get_current_blog_id(), '128', '' );
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