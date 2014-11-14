<?php

/**
 * Plugin Name: Radish Vendor Loader
 * Description: Allows plugins to be loaded via Composer and placed in a separate directory.
 */

add_action( 'plugins_loaded', 'radish_vendor_loader_plugins_loaded' );

function radish_vendor_loader_plugins_loaded() {
	// Load PHP 5.2 compatible autoloader if required
	if (version_compare(PHP_VERSION, '5.3.0') >= 0) {
		include('vendor/autoload.php');
	} else {
		include('vendor/autoload_52.php');
	}

	register_plugin_directory( array(
		'dir' => 'vendor-plugins',
		'label' => 'Vendor',
	) );

	if ( is_admin() ) {
		new Radish_Vendor_Loader_Admin();
	} else {
		new Radish_Vendor_Loader_Core();
	}
}