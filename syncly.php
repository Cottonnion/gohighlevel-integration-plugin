<?php
/**
 * Plugin Name:       Syncly for GoHighLevel
 * Plugin URI:        https://highlevelsync.com/
 * Description:       WordPress integration plugin that connects WordPress, WooCommerce, BuddyBoss, and LearnDash with GoHighLevel CRM for real-time two-way sync and automation.
 * Version:           1.4.6
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            LabGenz Team
 * Author URI:        https://labgenz.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       syncly
 * Domain Path:       /languages
 *
 * @package Syncly_GHL
 */

declare(strict_types=1);

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
if ( ! defined( 'SYNCLY_PLUGIN_NAME' ) ) {
	define( 'SYNCLY_PLUGIN_NAME', 'Syncly for GoHighLevel' );
}

if ( ! defined( 'SYNCLY_VERSION' ) ) {
	define( 'SYNCLY_VERSION', '1.4.6' );
}

if ( ! defined( 'SYNCLY_PATH' ) ) {
	define( 'SYNCLY_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'SYNCLY_URL' ) ) {
	define( 'SYNCLY_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'SYNCLY_BASENAME' ) ) {
	define( 'SYNCLY_BASENAME', plugin_basename( __FILE__ ) );
}

if ( ! defined( 'SYNCLY_TEXTDOMAIN' ) ) {
	define( 'SYNCLY_TEXTDOMAIN', 'syncly' );
}

if ( ! defined( 'SYNCLY_BRIDGE_LOG' ) ) {
	define( 'SYNCLY_BRIDGE_LOG', true );
}

// Require Composer autoloader
if ( file_exists( SYNCLY_PATH . 'vendor/autoload.php' ) ) {
	require_once SYNCLY_PATH . 'vendor/autoload.php';
} else {
	// Show admin notice if autoloader is missing
	add_action(
		'admin_notices',
		function () {
			?>
		<div class="notice notice-error">
			<p>
				<?php
				printf(
					/* translators: %s: Plugin name */
					esc_html__( '%s: Composer autoloader not found. Please run "composer install" in the plugin directory.', 'syncly' ),
					esc_html( SYNCLY_PLUGIN_NAME )
				);
				?>
			</p>
		</div>
			<?php
		}
	);
	return;
}

// Initialize Action Scheduler
if ( file_exists( SYNCLY_PATH . 'vendor/woocommerce/action-scheduler/action-scheduler.php' ) ) {
	require_once SYNCLY_PATH . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
}

// Initialize the plugin
function syncly_init() {
	return \Syncly\Core\Loader::get_instance();
}

// Start the plugin
syncly_init();

require_once SYNCLY_PATH . 'functions.php';