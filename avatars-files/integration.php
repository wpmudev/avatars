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

add_filter( 'avatars_enqueue_signup_scripts', 'avatars_membership2_enqueue_scripts', 10, 2 );
function avatars_membership2_enqueue_scripts( $enqueue, $pagenow ) {
	if ( class_exists( 'MS_Model_Pages' ) && method_exists( 'MS_Model_Pages', 'get_page' ) ) {
		$ms_page = MS_Model_Pages::get_page( MS_Model_Pages::MS_PAGE_REGISTER );
		if ( is_page( $ms_page ) ) {
			return true;
		}
	}

	return $enqueue;

}