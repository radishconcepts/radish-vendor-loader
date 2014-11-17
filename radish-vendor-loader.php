<?php

/**
 * Plugin Name: Radish Vendor Loader
 * Description: Allows plugins to be loaded via Composer and placed in a separate directory.
 */

add_action( 'plugins_loaded', 'radish_vendor_loader_plugins_loaded' );

function radish_vendor_loader_plugins_loaded() {
	global $radish_vendor_loader;
	$radish_vendor_loader = new Radish_Vendor_Loader();
}

class Radish_Vendor_Loader {
	public function __construct() {
		$this->do_autoload();

		if ( is_admin() ) {
			$class = new Radish_Vendor_Loader_Admin( $this );
		} else {
			$class = new Radish_Vendor_Loader_Core( $this );
		}

		$class->register_plugin_directory( array(
			'dir' => 'vendor-plugins',
			'label' => 'Vendor',
		) );
	}

	private function do_autoload() {
		if ( ! $this->already_loaded() ) {
			// Check if the vendors folder exists
			if ( ! file_exists( 'vendor/autoload.php' ) ) {
				die('Install Composer dependencies before using this plugin.');
			}

			// Load PHP 5.2 compatible autoloader if required
			if ( version_compare( PHP_VERSION, '5.3.0' ) >= 0 ) {
				include( 'vendor/autoload.php' );
			} else {
				include( 'vendor/autoload_52.php' );
			}
		}
	}

	private function already_loaded() {
		return ( class_exists( 'Radish_Vendor_Loader_Core' ) );
	}
}