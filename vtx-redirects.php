<?php
/**
 * Plugin Name:       VTX Redirects
 * Description:       A lightweight redirect manager with bulk editing, usage scanning, activity logs, and safe internal or external redirects.
 * Version:           2.1.1
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            VTX Labs
 * Author URI:        https://youneed.dev/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       vtx-redirects
 * Domain Path:       /languages
 *
 * @package VTX_Redirects
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VTX_REDIRECTS_VERSION', '2.1.1' );
define( 'VTX_REDIRECTS_FILE', __FILE__ );
define( 'VTX_REDIRECTS_DIR', plugin_dir_path( __FILE__ ) );
define( 'VTX_REDIRECTS_URL', plugin_dir_url( __FILE__ ) );

require_once VTX_REDIRECTS_DIR . 'includes/class-normalizer.php';
require_once VTX_REDIRECTS_DIR . 'includes/class-repository.php';
require_once VTX_REDIRECTS_DIR . 'includes/class-logger.php';
require_once VTX_REDIRECTS_DIR . 'includes/class-usage-scanner.php';
require_once VTX_REDIRECTS_DIR . 'includes/class-admin.php';
require_once VTX_REDIRECTS_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'Vortex\\Vtx_Redirects\\Plugin', 'activate' ) );
add_action( 'plugins_loaded', array( 'Vortex\\Vtx_Redirects\\Plugin', 'instance' ) );
