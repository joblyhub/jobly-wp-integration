<?php
/**
 * Plugin Name:       Jobly.si HRM
 * Plugin URI:        https://github.com/joblyhub/jobly-wp-integration
 * Description:       Delovna mesta podjetja iz Jobly.si v WordPressu: seznam, podstrani oglasov, objava novih mest in vgradnja prijavnega obrazca s kratko kodo [jobly].
 * Version:           0.2.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Jobly.si
 * Author URI:        https://jobly.si
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       jobly-integration
 * Domain Path:       /languages
 *
 * @package jobly-integration
 */

defined( 'ABSPATH' ) || exit;

const JOBLY_INTEGRATION_FILE    = __FILE__;
const JOBLY_INTEGRATION_VERSION = '0.2.0';
const JOBLY_INTEGRATION_OPTION  = 'jobly_integration';

require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/api-client.php';
require_once __DIR__ . '/includes/demo.php';
require_once __DIR__ . '/includes/frontend.php';

if ( is_admin() ) {
	foreach ( array( 'menu', 'overview', 'jobs', 'job-new', 'applications', 'settings' ) as $jobly_integration_screen ) {
		require_once __DIR__ . '/includes/admin/' . $jobly_integration_screen . '.php';
	}
}

/**
 * Plugin translations (Slovenian is the source language).
 */
function jobly_integration_load_textdomain() {
	// Shipped languages/ folder; not hosted on wordpress.org (yet), so no automatic loading.
	// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound
	load_plugin_textdomain( 'jobly-integration', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'jobly_integration_load_textdomain' );

/**
 * Rewrite rules for /kariera/… need a flush once.
 */
function jobly_integration_activate() {
	jobly_integration_register_rewrites();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'jobly_integration_activate' );

/**
 * Flush rewrite rules on deactivation.
 */
function jobly_integration_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'jobly_integration_deactivate' );
