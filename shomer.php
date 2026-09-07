<?php
/**
 * Plugin Name:       Shomer
 * Plugin URI:        https://github.com/aroraank/shomer
 * Description:       Watchman for WordPress. Notices when your site's trust surface changes and asks whether a human allowed it.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Ankit Arora
 * Author URI:        https://www.linkedin.com/in/iamankitarora/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       shomer
 * Domain Path:       /languages
 *
 * @package Shomer
 */

defined( 'ABSPATH' ) || exit;

define( 'SHOMER_VERSION', '0.1.0' );
define( 'SHOMER_PLUGIN_FILE', __FILE__ );
define( 'SHOMER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SHOMER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SHOMER_PLUGIN_BASE', plugin_basename( __FILE__ ) );

if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	return;
}

require_once SHOMER_PLUGIN_DIR . 'includes/class-shomer-hash.php';
require_once SHOMER_PLUGIN_DIR . 'includes/class-shomer-findings.php';
require_once SHOMER_PLUGIN_DIR . 'includes/class-shomer-log.php';
require_once SHOMER_PLUGIN_DIR . 'includes/class-shomer-scan-engine.php';
require_once SHOMER_PLUGIN_DIR . 'includes/class-shomer-http.php';
require_once SHOMER_PLUGIN_DIR . 'includes/class-shomer-self-audit.php';
require_once SHOMER_PLUGIN_DIR . 'includes/hardening/class-shomer-probe-results.php';
require_once SHOMER_PLUGIN_DIR . 'includes/class-shomer-admin.php';
require_once SHOMER_PLUGIN_DIR . 'includes/class-shomer-activator.php';
require_once SHOMER_PLUGIN_DIR . 'includes/class-shomer-plugin.php';

register_activation_hook( __FILE__, array( 'Shomer_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Shomer_Activator', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'Shomer_Plugin', 'init' ) );
