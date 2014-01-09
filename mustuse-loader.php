<?php
/**
 * The WordPress Must Use Plugins is an fine way to include without doings in back end
 * But WordPress include only files, check no subdirectories
 * This small plugin include all plugins in subdirectories from Must Use plugin folder
 *
 * @package Must-Use Loader
 * @author  Frank Bültge
 * @version 12/10/2013
 *
 * Plugin Name: Must-Use Loader
 * Plugin URI:  https://github.com/bueltge/Must-Use-Loader
 * Description: Load Must-Use Plugins inside subdirectories with caching. Delete the cache, if you view the Must Use plugin list in the network administration.
 * Version:     1.0.0
 * Author:      Frank Bültge
 * Author URI:  http://bueltge.de
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

// If this file is called directly, abort.
if ( ! function_exists( 'add_filter' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	echo "Hi there! I'm just a part of plugin, not much I can do when called directly.";
	exit();
}

add_action( 'muplugins_loaded',
	array( Must_Use_Plugins_Subdir_Loader::get_instance(), 'plugin_setup' )
);
/**
 * Class Must_Use_Plugins_Subdir_Loader
 *
 * @since   0.0.1
 * @package Must-Use Loader
 * @author  Frank Bültge
 */
class Must_Use_Plugins_Subdir_Loader {

	/**
	 * If the plugin fails to find your wpmu plugin directory,
	 * add this path via variable
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
	public $mustuse_total = 0;
	
	
	/**
	 * Handler for the action 'init'. Instantiates this class.
	 *
	 * @since  0.0.1
	 * @return Must_Use_Plugins_Subdir_Loader
	 */
	public static function get_instance() {

		static $instance;

		if ( NULL === $instance )
			$instance = new self();

		return $instance;
	}

	/**
	 * Used for the doing of the plugin
	 *
	 * @since  0.0.1
	 */
	public function plugin_setup() {

		// Include all plugins in subdirectories
		$this->include_subdir_plugins();
		
		// Delete transient cache, if active on the must use plugin list in network view
		add_action( 'load-plugins.php', array( $this, 'delete_subdir_mu_plugin_cache' ) );
		
		// Count must use plugins in subdirectory
		add_action( 'load-plugins.php', array( $this, 'count_subdir_plugins' ), 10 );
		
		// Change the plugin view value
		add_action( 'admin_footer-plugins.php', array( $this, 'change_view_values' ), 11 );
		
		// Add row and content for all plugins, there include via this plugin
		add_action( 'after_plugin_row_mustuse-loader.php', array( $this, 'view_subdir_mu_plugins' ) );
	}


	/**
	 * Get all plugins in subdirectories
	 * Write in a transient cache
	 *
	 * @since  0.0.1
	 * @return array|bool|mixed
	 */
	public function subdir_mu_plugins_files() {

		// Cache plugins
		$plugins = get_site_transient( 'subdir_wpmu_plugins' );

		// Deactivate caching on active debug
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG )
			$plugins = FALSE;

		if ( FALSE !== $plugins ) {

			foreach ( $plugins as $plugin_file ) {
				// Validate plugins still exist
				if ( ! is_readable( WPMU_PLUGIN_DIR . '/' . $plugin_file ) ) {
					$plugins = FALSE;
					break;
				}
			}
		}

		// No caching, then load
		if ( FALSE === $plugins) {

			// get_plugins is not included by default
			if ( ! function_exists( 'get_plugins') )
				require ABSPATH . 'wp-admin/includes/plugin.php';

			// Invalid cache
			$plugins = array();

			// Check for the optional defined var of the class
			if ( ! self::$wpmu_plugin_dir ) {
				// Relative path to single plugin directory
				$mu_plugins_folder = explode( '/', WPMU_PLUGIN_DIR );
				// Use last value
				self::$wpmu_plugin_dir = '/../' . end( $mu_plugins_folder );
			}

			// Get all plugins
			$mu_plugins = get_plugins( self::$wpmu_plugin_dir );

			foreach ( $mu_plugins as $plugin_file => $data ) {
				// skip files directly at root
				if ( '.' !== dirname( $plugin_file ) )
					$plugins[] = $plugin_file;
			}
			// Set cache for subdirectory plugins
			set_site_transient( 'subdir_wpmu_plugins', $plugins );
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
		foreach ( $this->subdir_mu_plugins_files() as $plugin_file )
			require_once( WPMU_PLUGIN_DIR . '/' . $plugin_file );
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

		// Delete cache when viewing plugins page in /wp-admin/
		if ( 'plugins-network' === $screen->id
			&& isset( $_SERVER['QUERY_STRING'] )
			&& 'plugin_status=mustuse' === $_SERVER['QUERY_STRING']
			)
			delete_site_transient( 'subdir_wpmu_plugins' );
	}
	
	/**
	 * Change total count for must use values
	 * 
	 * @since   01/09/2014
	 * @return  void
	 */
	public function change_view_values() {
		
		$current_screen = get_current_screen();
		if ( 'plugins-network' !== $current_screen->id )
			return;
		
		$item = sprintf( _n( 'item', 'items', $this->mustuse_total ), number_format_i18n( $this->mustuse_total ) );
		?>
		<script type="text/javascript">
			jQuery( document ).ready( function( $ ) {
				var text,
				    value,
				    mustuse;
				
				// replace the brackets and set int value
				text  = $( '.mustuse span' ).text();
				value = text.replace( '(', '' );
				value = parseInt( value.replace( ')', '' ) );
				
				// replace and add strings
				mustuse = value + <?php echo (int) $this->mustuse_total; ?>;
				$( '.mustuse span' ).replaceWith( '(' + mustuse + ')' );
				mustuse = mustuse + ' <?php echo $item; ?>';
				console.log( document.URL.search( /mustuse/ ) );
				if ( document.URL.search( /plugin_status=mustuse/ ) != -1 )
					$( '.tablenav .displaying-num' ).replaceWith( mustuse );
			} );
		</script>
		<?php
	}
	
	/**
	 * Count must use plugins in subdirectory
	 * 
	 * @since   01/09/2014
	 * @return  void
	 */
	public function count_subdir_plugins() {
		
		foreach ( $this->subdir_mu_plugins_files() as $plugin_file ) {
			
			$this->mustuse_total ++;
		}
	}
	
	/**
	 * Add rows for each sub-plugin under this plugin when listing mu-plugins in wp-admin
	 *
	 * @since   0.0.1
	 * @return  void
	 */
	public function view_subdir_mu_plugins() {
		
		foreach ( $this->subdir_mu_plugins_files() as $plugin_file ) {
			
			$data = get_plugin_data( WPMU_PLUGIN_DIR . '/' . $plugin_file );

			$name        = empty( $data['Name'] ) ? '?' : $data['Name'];
			$desc        = empty( $data['Description'] ) ? '&nbsp;' : $data['Description'];
			$version     = empty( $data['Version'] ) ? '' : $data['Version'];
			$author_name = empty( $data['AuthorName'] ) ? '' : $data['AuthorName'];
			$author      = empty( $data['Author'] ) ? $author_name : $data['Author'];
			$author_uri  = empty( $data['AuthorURI'] ) ? '' : $data['AuthorURI'];
			$plugin_site = empty( $data['PluginURI'] ) ? '' : '| <a href="' . $data['PluginURI'] . '">' . __( 'Visit plugin site' ) . '</a>';
			$id          = sanitize_title( $plugin_file );

			?>

			<tr id="<?php echo $id; ?>" class="active">
				<th scope="row" class="check-column"></th>
				<td class="plugin-title">
					<strong title="<?php echo $plugin_file; ?>"><?php echo $name; ?></strong>
				</td>
				<td class="column-description desc">
					<div class="plugin-description"><p><?php echo $desc; ?></p></div>
					<div class="active second plugin-version-author-uri">
						<?php printf( esc_attr__( 'Version %s | By %s %s' ), $version, $author, $plugin_site ) ; ?>
					</div>
				</td>
			</tr>

			<?php
		}
	}

}