<?php
/**
 * The WordPress Must Use Plugins is an fine way to
 *   include without doings in back end
 * But WordPress include only files, check no subdirectories
 * This small plugin include all plugins in subdirectories
 *   from Must Use plugin folder
 * Plugin Name: Must-Use Loader
 * Plugin URI:  https://github.com/bueltge/must-use-loader
 * Description: Load Must-Use Plugins inside subdirectories with caching. To delete the cache: Visit the Must Use plugins list in the network administration.
 * Version:     1.3.1
 * Text Domain: must_use_loader
 * Author:      Frank Bültge
 * Author URI:  https://bueltge.de
 * License:     MIT
 * License URI: https://opensource.org/licenses/MIT
 *
 * Php Version 5.6
 *
 * @package WordPress
 * @author  Frank Bültge <frank@bueltge.de>
 * @license https://opensource.org/licenses/MIT MIT
 * @version 2018-06-18
 */

// If this file is called directly, abort.
if ( ! function_exists( 'add_filter' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	echo "Hi there! I'm just a part of plugin, not much I can do when called directly.";
	exit();
}

if ( ! is_blog_installed() ) {
	return;
}

add_action(
	'muplugins_loaded',
	[ must_use_loader::get_instance(), 'plugin_setup' ]
);

/**
 * Class must_use_loader
 *
 * @since   0.0.1
 * @package Must-Use Loader
 * @author  Frank Bültge
 */
class must_use_loader {

	/**
	 * If the plugin fails to find your wpmu plugin directory,
	 * add this path via variable.
	 * Optional, Relative path to single plugin folder.
	 *
	 * @since  0.0.2
	 * @var    bool | string
	 */
	private static $wpmu_plugin_dir = FALSE;

	/**
	 * Store for the custom count to add this to the global of WP
	 *
	 * @since  01/09/2014
	 * @var    integer
	 */
	private $mustuse_total = 0;

	/**
	 * Sore the plugin list, there we should load.
	 *
	 * @since 2017-01-06
	 * @var   array
	 */
	private $plugins = [];

	/**
	 * Handler for the action 'init'. Instantiates this class.
	 *
	 * @since  0.0.1
	 * @return must_use_loader
	 */
	public static function get_instance() {

		static $instance;

		if ( NULL === $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Used for the doing of the plugin
	 *
	 * @since  0.0.1
	 * @return void
	 */
	public function plugin_setup() {

		// Delete transient cache, if active on the must use plugin list in network view
		add_action( 'load-plugins.php', [ $this, 'delete_subdir_mu_plugin_cache' ] );

		// Load plugins, count and store them.
		$this->subdir_mu_plugins_files();
		// Include all plugins in subdirectories
		$this->include_subdir_plugins();

		// Change the plugin view value
		add_action( 'admin_footer-plugins.php', [ $this, 'change_view_values' ], 11 );

		// Add row and content for all plugins, there include via this plugin.
		add_action( 'after_plugin_row_must_use_loader.php', [ $this, 'list_subdir_mu_plugins' ] );
	}

	/**
	 * Validate the plugins from cache, that still real exist
	 *
	 * @since  2014-10-15
	 *
	 * @param  bool|array $plugins List of plugins.
	 *
	 * @return bool
	 */
	private function validate_plugins( $plugins ) {

		foreach ( $plugins as $plugin_file ) {
			// Validate plugins still exist
			if ( ! is_readable( WPMU_PLUGIN_DIR . '/' . $plugin_file ) ) {
				$plugins = FALSE;
				break;
			}
		}

		return $plugins;
	}

	/**
	 * Retrieve the status of the WP Debug.
	 *
	 * @since  2017-01-04
	 * @return bool
	 */
	private function is_debug_status() {

		return defined( 'WP_DEBUG' ) && WP_DEBUG;
	}

	/**
	 * Get the data from transient.
	 *
	 * @return mixed
	 */
	private function get_transient() {

		return get_site_transient( 'subdir_wpmu_plugins' );
	}

	/**
	 * Get all plugins in subdirectories
	 * Write in a transient cache
	 *
	 * @since   0.0.1
	 * @version 2017-01-06
	 */
	public function subdir_mu_plugins_files() {

		// No caching, then load
		if ( $this->is_debug_status() ) {
			$plugins = $this->get_mu_plugins();

			// Set cache for subdirectory plugins
			set_site_transient( 'subdir_wpmu_plugins', $plugins );
		} else {
			// Get cached plugins.
			$plugins = $this->get_transient();

			// Refresh if the transient is wrong.
			if ( ! $plugins ) {
				$plugins = $this->get_mu_plugins();
			}

			// Debug is false, validate plugins from cache.
			$plugins = $this->validate_plugins( $plugins );
		}

		$this->plugins = $plugins;
		// Set counter for plugins.
		$this->mustuse_total = (int) count( $plugins );
	}

	/**
	 * Get all plugins from MU plugin directory.
	 *
	 * @return array
	 */
	public function get_mu_plugins() {

		// Invalid cache
		$plugins = [];

		// Check for the optional defined var of the class
		if ( ! self::$wpmu_plugin_dir ) {
			// Relative path to single plugin directory
			$mu_plugins_folder = explode( '/', WPMU_PLUGIN_DIR );
			// Use last value
			self::$wpmu_plugin_dir = '/../' . end( $mu_plugins_folder );
		}

		// get_plugins is not included by default
		if ( ! function_exists( 'get_plugins' ) ) {
			require ABSPATH . 'wp-admin/includes/plugin.php';
		}

		/**
		 * Get all plugins.
		 * You can remove plugins detected by the "Must Use Loader" from the loading process by use the filter hook.
		 */
		$mu_plugins = apply_filters( 'must_use_loader.mu_plugins', get_plugins( self::$wpmu_plugin_dir ) );

		// The function array_keys() is ugly and a performance impact.
		foreach ( $mu_plugins as $plugin_file => $not_used ) {
			// Skip files directly at root
			// And skip folders starting with an underscore as we want to turn those plugins off.
			if ( '.' !== dirname( $plugin_file ) && dirname( $plugin_file )[0] !== '_' ) {
				$plugins[] = $plugin_file;
			}
		}

		return $plugins;
	}

	/**
	 * Include all plugins from subdirectories
	 *
	 * @since   0.0.1
	 * @return  void
	 */
	public function include_subdir_plugins() {

		// Include all plugins in subdirectories
		foreach ( $this->plugins as $plugin_file ) {
			require_once WPMU_PLUGIN_DIR . '/' . $plugin_file;
			wp_register_plugin_realpath( WPMU_PLUGIN_DIR . '/' . $plugin_file );
			// Currently not possible, WP validate plugins, there are active only via path WP_PLUGIN_DIR.
			//$this->set_network_plugin_option( $plugin_file );
		}
	}

	/**
	 * Set network options for active plugins, necessary for checks of other plugin is network wide active.
	 *
	 * @param string $plugin File path.
	 */
	public function set_network_plugin_option( $plugin ) {

		$current            = get_site_option( 'active_sitewide_plugins', [] );
		$current[ $plugin ] = time();
		update_site_option( 'active_sitewide_plugins', $current );
	}

	/**
	 * Delete the transient cache, if on the Must Use plugin list on network view
	 *
	 * @since   0.0.1
	 * @return  void
	 */
	public function delete_subdir_mu_plugin_cache() {

		// get screen information
		$screen = get_current_screen();
		if ( null === $screen ) {
			return;
		}

		// Delete cache when viewing plugins page in /wp-admin/
		if ( 'plugins-network' === $screen->id ) {
			delete_site_transient( 'subdir_wpmu_plugins' );
		}
	}

	/**
	 * Change total count for must use values
	 *
	 * @since   01/09/2014
	 * @return  void
	 */
	public function change_view_values() {

		$current_screen = get_current_screen();
		if ( null === $current_screen || 'plugins-network' !== $current_screen->id ) {
			return;
		}

		$item = sprintf( _n( 'item', 'items', $this->mustuse_total ), number_format_i18n( $this->mustuse_total ) ); ?>
		<script type="text/javascript">
			jQuery( document ).ready( function($) {
				let text,
					value,
					mustuse,
					selector;

				// replace the brackets and set int value
				selector = '.mustuse span';
				text = $( selector ).text();
				value = text.replace( '(', '' );
				value = parseInt( value.replace( ')', '' ) );

				// replace and add strings
				mustuse = value + <?php echo $this->mustuse_total; ?>;
				$( selector ).replaceWith( '(' + mustuse + ')' );
				mustuse = mustuse + ' <?php echo esc_attr( $item ); ?>';
				if ( document.URL.search( /plugin_status=mustuse/ ) !== - 1 ) {
					$( '.tablenav .displaying-num' ).replaceWith( mustuse );
				}
			} );
		</script>
		<?php
	}

	/**
	 * Filter Plugin data for view on must use list.
	 *
	 * @since  2014-10-15
	 *
	 * @param  string $plugin_file Plugin file name.
	 *
	 * @return array
	 */
	public function filter_plugin_data( $plugin_file ) {

		$defaults = [
			'Name'        => '?',
			'Description' => '&nbsp;',
			'Version'     => '',
			'AuthorName'  => '',
			'Author'      => '',
			'PluginURI'   => '',
		];

		$data = get_plugin_data( WPMU_PLUGIN_DIR . '/' . $plugin_file );

		return wp_parse_args( $data, $defaults );
	}

	/**
	 * Format the plugin uri.
	 *
	 * @since  2014-10-15
	 *
	 * @param  string $data Url for each plugin link.
	 *
	 * @return string
	 */
	public function format_plugin_uri( $data ) {

		if ( '' === $data ) {
			return $data;
		}

		$plugin_uri = '| <a href="' . esc_url( $data ) . '">' . esc_html__( 'Visit plugin site' ) . '</a>';

		return $plugin_uri;
	}

	/**
	 * Add rows for each sub-plugin under this plugin when listing mu-plugins in wp-admin.
	 *
	 * @since   0.0.1
	 * @return  void
	 */
	public function list_subdir_mu_plugins() {

		foreach ( $this->plugins as $plugin_file ) {
			$plugin_data = $this->filter_plugin_data( $plugin_file );

			// Sanitize fields
			$allowed_tags = [
				'abbr'    => [ 'title' => TRUE ],
				'acronym' => [ 'title' => TRUE ],
				'code'    => TRUE,
				'em'      => TRUE,
				'strong'  => TRUE,
				'cite'    => TRUE,
				'a'       => [ 'href' => TRUE, 'title' => TRUE ],
			]; ?>

			<tr id="<?php echo sanitize_title( $plugin_file ); ?>" class="active">
				<th scope="row" class="check-column"></th>
				<td class="plugin-title">
					<strong title="<?php echo esc_attr( $plugin_file ); ?>">
						<?php echo wp_kses( $plugin_data[ 'Name' ], $allowed_tags ); ?>
					</strong>
				</td>
				<td class="column-description desc">
					<div class="plugin-description">
						<p><?php echo wp_kses( $plugin_data[ 'Description' ], $allowed_tags ); ?></p>
					</div>
					<div class="active second plugin-version-author-uri">
						<?php printf(
							esc_attr__( 'Version %1$s | By %2$s %3$s' ),
							wp_kses( $plugin_data[ 'Version' ], $allowed_tags ),
							wp_kses( $plugin_data[ 'Author' ], $allowed_tags ),
							esc_url( $this->format_plugin_uri( $plugin_data[ 'PluginURI' ] ) )
						); ?>
					</div>
				</td>
			</tr>

			<?php

		}
	}
}
