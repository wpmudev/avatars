<?php

function wpmudev_debug( $message ) {
	$dg = Ignacio_WPMUDEV_Debugger::get_instance();
	$dg->debug( print_r( $message, true ) );
}

function wpmudev_send_debug() {
	$dg = Ignacio_WPMUDEV_Debugger::get_instance();
	$dg->send();
}

class Ignacio_WPMUDEV_Debugger {
	public $trace = array();
	static $instance = null;
	public $email = 'ignacio.incsub@gmail.com';

	public function get_instance() {
		if ( self::$instance == null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function debug( $message ) {
        $file = __DIR__.'/wpmudev.log';
        $bTrace = debug_backtrace(); // assoc array
    
        /* Build the string containing the complete log line. */
        $line = PHP_EOL.sprintf('[%s, <%s>, (%d)]==> %s', 
            date("Y/m/d h:i:s", mktime()),
            basename($bTrace[0]['file']), 
            $bTrace[0]['line'], 
            $message );
        
        file_put_contents($file,$line,FILE_APPEND);

        return true;
	}

	public function send() {
		if ( is_user_logged_in() && $user = wp_get_current_user() ) {
			if ( $user->user_login == 'campus' || $user->user_login == 'ignacio' ) {
				ob_start();
				var_dump($this->trace);
				$result = ob_get_clean();
				wp_mail( $this->email, 'Trace', $result );
			}
		}
	}
}