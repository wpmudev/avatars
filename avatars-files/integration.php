<?php

// Pro Sites integration

/**
 * Avatars was not enqueueing scripts on Pro Sites signup screen
 * @return Boolean
 */
add_filter( 'avatars_enqueue_signup_scripts', 'avatars_pro_sites_enqueue_scripts', 10, 2 );
function avatars_pro_sites_enqueue_scripts( $enqueue, $pagenow ) {
	global $psts;

	if ( is_object( $psts ) ) {
		if ( is_page( $psts->get_setting( 'checkout_page' ) ) )
			return true;
	}

	return $enqueue;
}