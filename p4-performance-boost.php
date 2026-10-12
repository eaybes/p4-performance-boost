<?php
/**
 * Plugin Name: Planet4 Performance Boost
 * Description: Safe performance optimizations for Planet4: preconnect hints, LCP image priority and 3rd-party script deferral (with a never-delay whitelist for GTM / OptiMonk).
 * Version: 1.1.0
 * Author: Elad Aybes
 * Text Domain: p4pb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'P4PB_VERSION',    '1.1.0' );
define( 'P4PB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'P4PB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once P4PB_PLUGIN_DIR . 'includes/class-p4pb-admin.php';
require_once P4PB_PLUGIN_DIR . 'includes/class-p4pb-preconnect.php';
require_once P4PB_PLUGIN_DIR . 'includes/class-p4pb-lcp.php';
require_once P4PB_PLUGIN_DIR . 'includes/class-p4pb-script-delay.php';

add_action( 'plugins_loaded', function () {
	P4PB_Admin::instance();

	// Kill switch for debugging: add ?p4pb=off to any front-end URL.
	if ( ! is_admin() && isset( $_GET['p4pb'] ) && 'off' === $_GET['p4pb'] ) {
		return;
	}

	$options = P4PB_Admin::get_options();

	if ( ! empty( $options['enable_preconnect'] ) ) {
		P4PB_Preconnect::instance();
	}

	if ( ! empty( $options['enable_lcp'] ) ) {
		P4PB_LCP::instance();
	}

	if ( ! empty( $options['enable_script_delay'] ) ) {
		P4PB_Script_Delay::instance();
	}
} );
