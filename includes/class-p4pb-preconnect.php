<?php
/**
 * Adds preconnect and dns-prefetch hints for external origins.
 */
class P4PB_Preconnect {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_head', [ $this, 'output_hints' ], 1 );
	}

	public function output_hints() {
		$options = get_option( 'p4pb_options', [] );
		$raw     = $options['preconnect_origins'] ?? '';
		$origins = array_filter( array_map( 'trim', explode( "\n", $raw ) ) );

		foreach ( $origins as $origin ) {
			$origin = esc_url( $origin );
			if ( ! $origin ) {
				continue;
			}
			echo '<link rel="preconnect" href="' . $origin . '" crossorigin>' . "\n";
			echo '<link rel="dns-prefetch" href="' . $origin . '">' . "\n";
		}
	}
}
