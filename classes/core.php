<?php

class Radish_Vendor_Loader_Core {
	/** @var Radish_Vendor_Loader */
	protected $loader;

	public function __construct( $loader ) {
		$this->loader = $loader;

		add_action( 'plugins_loaded', array( $this, 'setup_vendors_type' ), 0 );
		add_action( 'plugins_loaded', array( $this, 'load_plugins' ), 0 );
		add_filter( 'plugins_url', array( $this, 'plugins_url' ), 10, 3 );
	}

	public function plugins_url( $url, $path, $plugin ) {
		$dir = dirname( plugin_basename( $plugin ) );
		$dir_parts = explode('/', $dir );
		$dir_name = end( $dir_parts );
		$dir = str_replace( $dir_name, '', $dir );
		$vendor_plugins = get_option( 'active_plugins_vendor' );

		foreach ( $vendor_plugins as $vendor_plugin ) {
			if ( strstr( $plugin, $vendor_plugin ) ) {
				$url = str_replace( array( '/plugins/', '/mu-plugins/' ), '/vendor-plugins/', $url );
				$url = str_replace( $dir, '', $url );
			}
		}

		return $url;
	}

	public function setup_vendors_type() {
		$this->register_plugin_directory( array(
			'dir' => 'vendor-plugins',
			'label' => 'Vendor',
		) );
	}

	/**
	 * Loads additional plugins from custom directories.
	 * To add a directory, you must do so in a plugin (hooked into `plugins_loaded` with a low priority).
	 *
	 * @since  0.1
	 * @return void
	 */
	public function load_plugins() {
		global $wp_plugin_directories;

		empty( $wp_plugin_directories ) AND $wp_plugin_directories = array();

		foreach ( array_keys( $wp_plugin_directories ) as $key ) {
			$active = get_option( "active_plugins_{$key}", array() );

			foreach( $active as $a ) {
				if ( file_exists( "{$wp_plugin_directories[ $key ]['dir']}/{$a}" ) ) {
					include_once( "{$wp_plugin_directories[ $key ]['dir']}/{$a}" );
				}
			}
		}
	}

	/**
	 * Get the valid plugins from the custom directory
	 *
	 * @since  0.1
	 * @param  string $dir_key The `key` of our custom plugin directory
	 * @return array A list of the plugins
	 */
	public function get_plugins_from_cache( $dir_key ) {
		global $wp_plugin_directories;

		// invalid dir key? bail
		if ( ! isset( $wp_plugin_directories[ $dir_key ] ) ) {
			return array();
		}

		$plugin_root = $wp_plugin_directories[ $dir_key ]['dir'];

		if ( ! $cache_plugins = wp_cache_get( 'plugins', 'plugins') ) {
			$cache_plugins = array();
		}

		if ( isset( $cache_plugins[ $dir_key ] ) ) {
			return $cache_plugins[ $dir_key ];
		}

		$wp_plugins = array();

		$plugins_dir = @ opendir( $plugin_root );
		$plugin_files = array();
		if ( $plugins_dir ) {
			while ( false !== ( $file = readdir( $plugins_dir ) ) ) {
				if ( '.' === substr( $file, 0, 1 ) ) {
					continue;
				}

				if ( is_dir( "{$plugin_root}/{$file}" ) ) {
					$plugins_subdir = @ opendir( "{$plugin_root}/{$file}" );

					if ( $plugins_subdir ) {
						while ( ( $subfile = readdir( $plugins_subdir ) ) !== false ) {
							if ( '.' === substr( $subfile, 0, 1 ) ) {
								continue;
							}

							if ( '.php' === substr( $subfile, -4 ) ) {
								$plugin_files[] = "{$file}/{$subfile}";
							}
						}

						closedir( $plugins_subdir );
					}
				} else {
					'.php' === substr( $file, -4 ) AND $plugin_files[] = $file;
				}
			}

			closedir( $plugins_dir );
		}

		if ( empty( $plugin_files ) ) {
			return $wp_plugins;
		}

		foreach ( $plugin_files as $plugin_file ) {
			if ( ! is_readable( "{$plugin_root}/{$plugin_file}" ) ) {
				continue;
			}

			// Do not apply markup/translate as it'll be cached.
			$plugin_data = get_plugin_data( "{$plugin_root}/{$plugin_file}", false, false );

			if ( empty ( $plugin_data['Name'] ) ) {
				continue;
			}

			$wp_plugins[ trim( $plugin_file ) ] = $plugin_data;
		}

		uasort( $wp_plugins, '_sort_uname_callback' );

		// Setup cache, if we ain't already got one.
		// If we got one, we already returned the cached plugins.
		$cache_plugins[ $dir_key ] = $wp_plugins;
		wp_cache_set( 'plugins', $cache_plugins, 'plugins' );

		return $wp_plugins;
	}

	/**
	 * Custom plugin activation function.
	 *
	 * @since  0.1
	 * @return void
	 */
	public function activate_plugin( $plugin, $context, $silent = false ) {
		$plugin = trim( $plugin );

		$redirect = add_query_arg( 'plugin_status', $context, admin_url( 'plugins.php' ) );
		$redirect = apply_filters( 'custom_plugin_redirect', $redirect );

		$current = get_option( "active_plugins_{$context}", array() );

		$valid = $this->validate_plugin( $plugin, $context );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		if ( ! in_array( $plugin, $current ) ) {
			if ( ! empty( $redirect ) ) {
				// we'll override this later if the plugin can be included without fatal error
				wp_redirect( add_query_arg(
					'_error_nonce'
					,wp_create_nonce( "plugin-activation-error_{$plugin}" )
					,$redirect
				) );
			}

			ob_start();
			include_once( $valid );

			if ( ! $silent ) {
				do_action( 'custom_activate_plugin', $plugin, $context );
				do_action( "custom_activate_{$plugin}", $context );
			}

			$current[] = $plugin;
			sort( $current );
			update_option( "active_plugins_{$context}", $current );

			if ( ! $silent ) {
				do_action( 'custom_activated_plugin', $plugin, $context );
			}

			if ( ob_get_length() > 0 ) {
				$output = ob_get_clean();
				return new WP_Error( 'unexpected_output', __( 'The plugin generated unexpected output.' ), $output );
			}

			ob_end_clean();
		}

		return true;
	}

	/**
	 * Deactivate custom plugins
	 *
	 * @since  0.1
	 * @return void
	 */
	public function deactivate_plugins( $plugins, $context, $silent = false ) {
		$current = get_option( "active_plugins_{$context}", array() );

		foreach ( (array) $plugins as $plugin ) {
			$plugin = trim( $plugin );

			if ( ! in_array( $plugin, $current ) ) {
				continue;
			}

			if ( ! $silent ) {
				do_action( 'custom_deactivate_plugin', $plugin, $context );
			}

			$key = array_search( $plugin, $current );

			if ( false !== $key ) {
				array_splice( $current, $key, 1 );
			}

			if ( ! $silent ) {
				do_action( "custom_deactivate_{$plugin}", $context );
				do_action( 'custom_deactivated_plugin', $plugin, $context );
			}
		}

		update_option( "active_plugins_{$context}", $current );
	}

	/**
	 * Checks to see whether the plugin and is valid and can be activated.
	 *
	 * @uses validate_file To make sure the plugin name is okay.
	 * @param  string $plugin
	 * @return array $context
	 * @return WP_Error|string WP_Error object on failure, the plugin to include on success.
	 */
	public function validate_plugin( $plugin, $context ) {
		$rv = true;

		if ( validate_file( $plugin ) ) {
			$rv = new WP_Error( 'plugin_invalid', __( 'Invalid plugin path.' ) );
		}

		global $wp_plugin_directories;

		if ( ! isset( $wp_plugin_directories[ $context ] ) ) {
			$rv = new WP_Error( 'invalid_context', __( 'The context for this plugin does not exist' ) );
		}

		$dir = $wp_plugin_directories[ $context ]['dir'];

		if ( ! file_exists( "{$dir}/{$plugin}" ) ) {
			$rv = new WP_Error( 'plugin_not_found', __( 'Plugin file does not exist.' ) );
		}

		$installed_plugins = $this->get_plugins_from_cache( $context );

		if ( ! isset( $installed_plugins[ $plugin ] ) ) {
			$rv = new WP_Error( 'no_plugin_header', __('The plugin does not have a valid header.') );
		}

		$rv = "{$dir}/{$plugin}";

		return $rv;
	}

	/**
	 * Registers a new plugin directory.
	 *
	 * @since  0.1
	 * @uses   _get_new_plugin_directory_root()
	 * @param  array  $args             An Array of arguments: 'dir' = Name of the directory, 'label' = What you read above the list table, 'case' = Where the dir resides.
	 * @return bool                     TRUE on success, FALSE in case the $key/$label is already in use.
	 */
	function register_plugin_directory( $args ) {
		// The call was too late (or too early in case of a MU-Plugin)
		if ( 'plugins_loaded' !== current_filter() )
		{
			_doing_it_wrong(
				__FUNCTION__
				,__( 'Registering a new plugin directory should be done during the `plugins_loaded` hook on priority `0`.', 'cd_apd_textdomain' )
				,'0.1'
			);
		}

		// Setup defaults
		$args = wp_parse_args( $args, array( 'root' => 'content' ) );

		global $wp_plugin_directories;

		empty( $wp_plugin_directories ) AND $wp_plugin_directories = array();

		$new_dir = $this->_get_new_plugin_directory_root( $args['root'] ).$args['dir'];

		if ( ! file_exists( $args['dir'] ) AND file_exists( $new_dir ) )
		{
			$args['dir'] = $new_dir;
		}

		// Build $key from $label
		$key = strtolower( preg_replace( "/[^a-zA-Z0-9\s]/", "", $args['label'] ) );

		// Return FALSE in case we already got the key
		if ( isset( $wp_plugin_directories[ $key ] ) )
			return false;

		// Assign the directory
		$wp_plugin_directories[ $key ] = array(
			'dir'   => $args['dir'],
			'label' => $args['label'],
			'root'  => $args['root']
		);

		return true;
	}

	/**
	 * Retrieves the root path for the new plugin directory.
	 *
	 * @internal Callback function for register_plugin_directory()
	 *
	 * @since    0.3
	 * @return   string $root The root path based on the WP filesystem constants.
	 */
	function _get_new_plugin_directory_root( $root ) {
		switch ( $root )
		{
			case 'plugins' :
				$root = WP_PLUGIN_DIR;
				break;

			case 'muplugins' :
				$root = WPMU_PLUGIN_DIR;
				break;

			// Experimental Edge Case:
			// Assuming that the WP_CONTENT_DIR is a direct child of the root directory
			// and directory separators are "/" above that.
			// Maybe needs enchancements later on. Wait for feedback in Issues.
			case 'root' :
				$root = explode( DIRECTORY_SEPARATOR, WP_CONTENT_DIR );
				$root = explode( '/', array_pop( $root ) );
				$root = array_pop( $root );
				break;

			case 'content' :
				$root = WP_CONTENT_DIR;
				break;

			default :
				$root = apply_filters( "adp_root_{$root}", WP_CONTENT_DIR );
				break;
		}

		return trailingslashit( $root );
	}
}